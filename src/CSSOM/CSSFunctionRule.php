<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@function` rule, including its parameter signatures and return type.
 *
 * @property-read string $name
 * @property-read string $returnType
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFunctionRule
 */
final class CSSFunctionRule extends CSSGroupingRule
{
	private readonly string $functionName;

	/** @var list<array{name: string, type: string, defaultValue: ?string}> */
	private readonly array $parameters;

	private readonly string $functionReturnType;

	/** @param list<array{name: string, type: string, defaultValue: ?string}> $parameters */
	public function __construct(
		string $functionName,
		string $prelude = '',
		array $parameters = [],
		string $functionReturnType = '*',
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		if ($maxBytes !== null && strlen($functionName) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @function name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$validatedName = CSSStyleDeclaration::normalizePropertyName($functionName);
		if (!str_starts_with($validatedName, '--')) {
			throw new InvalidArgumentException('@function name must begin with "--".');
		}
		if ($maxBytes !== null && strlen($functionReturnType) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @function return type exceeds maximum size of %d bytes.', $maxBytes));
		}
		$normalizedReturnType = CSSSyntaxScanner::trimCssWhitespace($functionReturnType);
		if ($normalizedReturnType === '') {
			throw new InvalidArgumentException('@function return type cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($normalizedReturnType, '@function return type', [';', '{', '}']);

		$normalizedParameters = [];
		foreach ($parameters as $parameter) {
			if (!is_array($parameter)
				|| !isset($parameter['name'], $parameter['type'])
				|| !is_string($parameter['name'])
				|| !is_string($parameter['type'])
				|| (array_key_exists('defaultValue', $parameter) && $parameter['defaultValue'] !== null && !is_string($parameter['defaultValue']))
			) {
				throw new InvalidArgumentException('Malformed @function parameter definition.');
			}
			if ($maxBytes !== null && strlen($parameter['name']) > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS @function parameter name exceeds maximum size of %d bytes.', $maxBytes));
			}
			$paramName = CSSStyleDeclaration::normalizePropertyName($parameter['name']);
			if (!str_starts_with($paramName, '--')) {
				throw new InvalidArgumentException('@function parameter name must begin with "--".');
			}
			if ($maxBytes !== null && strlen($parameter['type']) > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS @function parameter type exceeds maximum size of %d bytes.', $maxBytes));
			}
			$paramType = CSSSyntaxScanner::trimCssWhitespace($parameter['type']);
			if ($paramType === '') {
				throw new InvalidArgumentException('@function parameter type cannot be empty.');
			}
			CSSSyntaxScanner::assertValidFragment($paramType, '@function parameter type', [';', '{', '}']);

			$defaultValue = $parameter['defaultValue'] ?? null;
			if ($defaultValue !== null) {
				if ($maxBytes !== null && strlen($defaultValue) > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS @function parameter default value exceeds maximum size of %d bytes.', $maxBytes));
				}
				CSSSyntaxScanner::assertValidFragment($defaultValue, '@function parameter default value', [';', '{', '}']);
			}

			$normalizedParameters[] = [
				'name' => $paramName,
				'type' => $paramType,
				'defaultValue' => $defaultValue,
			];
		}

		$this->functionName = $validatedName;
		$this->parameters = $normalizedParameters;
		$this->functionReturnType = $normalizedReturnType;
		parent::__construct('function', $prelude === '' ? $validatedName : $prelude, $maxBytes);
	}

	/**
	 * Parses a `@function` prelude into a CSSFunctionRule instance.
	 *
	 * @throws InvalidArgumentException When the function name, parameters, defaults, or return type are malformed
	 */
	public static function fromPrelude(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): self
	{
		if ($maxBytes !== null && strlen($prelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @function prelude exceeds maximum size of %d bytes.', $maxBytes));
		}
		$prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		$open = strpos($prelude, '(');
		if ($open === false) {
			throw new InvalidArgumentException('Malformed @function name or parameter list.');
		}
		$name = CSSSyntaxScanner::trimCssWhitespace(substr($prelude, 0, $open));
		$normalizedName = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalizedName, '--')) {
			throw new InvalidArgumentException('Malformed @function name or parameter list.');
		}

		$close = CSSSyntaxScanner::findMatchingDelimiter($prelude, $open);
		if ($close === null) {
			throw new InvalidArgumentException('Unclosed @function parameter list.');
		}

		$parameters = self::parseParameters(substr($prelude, $open + 1, $close - $open - 1));
		$returnTail = CSSSyntaxScanner::trimCssWhitespace(substr($prelude, $close + 1));
		$returnType = '*';
		if ($returnTail !== '') {
			if (!preg_match('/^returns\s+(<[^>]+>|\*)$/iD', $returnTail, $match)) {
				throw new InvalidArgumentException('Malformed @function return type.');
			}
			CSSSyntaxScanner::assertValidFragment($match[1], '@function return type', [';', '{', '}']);
			$returnType = $match[1];
		}

		return new self($normalizedName, $prelude, $parameters, $returnType, $maxBytes);
	}

	public function getType(): int
	{
		return self::FUNCTION_RULE;
	}

	public function getNameText(): string
	{
		return $this->functionName;
	}

	public function getReturnType(): string
	{
		return $this->functionReturnType;
	}

	/** @return list<array{name: string, type: string, defaultValue: ?string}> */
	public function getParameters(): array
	{
		return $this->parameters;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'returnType' => $this->getReturnType(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'returnType' => true,
			default => parent::__isset($name),
		};
	}

	/** @return list<array{name: string, type: string, defaultValue: ?string}> */
	private static function parseParameters(string $source): array
	{
		if (CSSSyntaxScanner::trimCssWhitespace($source) === '') {
			return [];
		}

		$parameters = [];
		foreach (CSSSyntaxScanner::splitTopLevel($source, ',') as $parameter) {
			$colon = CSSSyntaxScanner::findTopLevel($parameter, ':');
			$declaration = $colon === null ? CSSSyntaxScanner::trimCssWhitespace($parameter) : CSSSyntaxScanner::trimCssWhitespace(substr($parameter, 0, $colon));
			$defaultValue = $colon === null ? null : CSSSyntaxScanner::trimCssWhitespace(substr($parameter, $colon + 1));
			if (!preg_match('/^(--[a-zA-Z0-9_-]+)(?:\s+(<[^>]+>|\*))?$/D', $declaration, $match)) {
				throw new InvalidArgumentException('Malformed @function parameter: ' . CSSSyntaxScanner::trimCssWhitespace($parameter));
			}
			if ($defaultValue !== null) {
				if ($defaultValue === '') {
					throw new InvalidArgumentException('Empty default value for @function parameter: ' . $match[1]);
				}
				CSSSyntaxScanner::assertValidFragment($defaultValue, '@function parameter default value', [';', '{', '}']);
			}
			$parameters[] = [
				'name' => $match[1],
				'type' => $match[2] ?? '*',
				'defaultValue' => $defaultValue,
			];
		}

		return $parameters;
	}
}
