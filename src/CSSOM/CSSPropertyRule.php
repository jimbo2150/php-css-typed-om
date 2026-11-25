<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@property` custom property registration rule.
 *
 * @property-read string $name
 * @property-read string $syntax
 * @property-read bool $inherits
 * @property-read ?string $initialValue
 * @property-read CSSStyleDeclaration $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPropertyRule
 */
final class CSSPropertyRule extends CSSAtRule
{
	/**
	 * @throws InvalidArgumentException When $name is not a valid custom property identifier
	 */
	public function __construct(
		string $name,
		CSSStyleDeclaration $descriptors,
		private bool $hasEmptyInitialValue = false,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @property name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@property requires a custom property name starting with "--".');
		}
		parent::__construct('property', $normalized, $descriptors, true, $maxBytes);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	/** @internal Clears empty `initial-value: ;` tracking after explicit style mutations. */
	public function clearEmptyInitialValueInternal(): void
	{
		$this->hasEmptyInitialValue = false;
	}

	/** @internal Sets empty `initial-value: ;` tracking state. */
	public function setEmptyInitialValueInternal(bool $hasEmpty): void
	{
		$this->hasEmptyInitialValue = $hasEmpty;
	}

	public function getSyntax(): string
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($this->styleDeclaration?->getPropertyValue('syntax') ?? '');
		if (str_contains($raw, '/*')) {
			$raw = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw));
		}
		$len = strlen($raw);
		if ($len >= 2 && (($raw[0] === '"' && $raw[$len - 1] === '"') || ($raw[0] === "'" && $raw[$len - 1] === "'"))) {
			return substr($raw, 1, -1);
		}

		return $raw;
	}

	public function getInherits(): bool
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($this->styleDeclaration?->getPropertyValue('inherits') ?? '');
		if (str_contains($raw, '/*')) {
			$raw = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw));
		}

		return strtolower($raw) === 'true';
	}

	public function getInitialValue(): ?string
	{
		if ($this->styleDeclaration === null) {
			return $this->hasEmptyInitialValue ? '' : null;
		}
		if (!isset($this->styleDeclaration['initial-value'])) {
			return $this->hasEmptyInitialValue ? '' : null;
		}

		return $this->styleDeclaration->getPropertyValue('initial-value');
	}

	public function getCssText(): string
	{
		if (!$this->hasEmptyInitialValue || ($this->styleDeclaration !== null && isset($this->styleDeclaration['initial-value']))) {
			return parent::getCssText();
		}
		$body = $this->styleDeclaration?->getCssText() ?? '';
		$extra = 'initial-value: ;';
		$content = $body === '' ? $extra : $body . ' ' . $extra;

		return '@' . $this->atRuleName . ' ' . CSSSyntaxScanner::trimCssWhitespace($this->prelude) . ' { ' . $content . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'syntax' => $this->getSyntax(),
			'inherits' => $this->getInherits(),
			'initialValue' => $this->getInitialValue(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'syntax', 'inherits' => true,
			'initialValue' => $this->getInitialValue() !== null,
			default => parent::__isset($name),
		};
	}
}
