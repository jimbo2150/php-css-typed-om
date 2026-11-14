<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

final class CSSFunctionRule extends CSSGroupingRule
{
	/** @param list<array{name: string, type: string, defaultValue: ?string}> $parameters */
	public function __construct(
		private readonly string $functionName,
		string $prelude = '',
		private readonly array $parameters = [],
		private readonly string $functionReturnType = '*',
	) {
		parent::__construct('function', $prelude === '' ? $functionName : $prelude);
	}

	public static function fromPrelude(string $prelude): self
	{
		$prelude = trim($prelude);
		$open = strpos($prelude, '(');
		if ($open === false || !preg_match('/^--[a-zA-Z0-9_-]+$/D', trim(substr($prelude, 0, $open)))) {
			throw new InvalidArgumentException('Malformed @function name or parameter list.');
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($prelude, $open);
		if ($close === null) {
			throw new InvalidArgumentException('Unclosed @function parameter list.');
		}

		$name = trim(substr($prelude, 0, $open));
		$parameters = self::parseParameters(substr($prelude, $open + 1, $close - $open - 1));
		$returnTail = trim(substr($prelude, $close + 1));
		$returnType = '*';
		if ($returnTail !== '') {
			if (!preg_match('/^returns\s+(<[^>]+>|\*)$/iD', $returnTail, $match)) {
				throw new InvalidArgumentException('Malformed @function return type.');
			}
			$returnType = $match[1];
		}

		return new self($name, $prelude, $parameters, $returnType);
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

	/** @return list<array{name: string, type: string, defaultValue: ?string}> */
	private static function parseParameters(string $source): array
	{
		if (trim($source) === '') {
			return [];
		}

		$parameters = [];
		foreach (CSSSyntaxScanner::splitTopLevel($source, ',') as $parameter) {
			$colon = CSSSyntaxScanner::findTopLevel($parameter, ':');
			$declaration = $colon === null ? trim($parameter) : trim(substr($parameter, 0, $colon));
			$defaultValue = $colon === null ? null : trim(substr($parameter, $colon + 1));
			if (!preg_match('/^(--[a-zA-Z0-9_-]+)(?:\s+(<[^>]+>|\*))?$/D', $declaration, $match)) {
				throw new InvalidArgumentException('Malformed @function parameter: ' . trim($parameter));
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
