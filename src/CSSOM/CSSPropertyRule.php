<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@property` custom property registration rule.
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
	) {
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@property requires a custom property name starting with "--".');
		}
		parent::__construct('property', $normalized, $descriptors, true);
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

	public function getSyntax(): string
	{
		return trim($this->style?->getPropertyValue('syntax') ?? '', " \t\n\r\f\"'");
	}

	public function getInherits(): bool
	{
		return strtolower($this->style?->getPropertyValue('inherits') ?? '') === 'true';
	}

	public function getInitialValue(): ?string
	{
		if ($this->style === null) {
			return $this->hasEmptyInitialValue ? '' : null;
		}
		if (!isset($this->style['initial-value'])) {
			return $this->hasEmptyInitialValue ? '' : null;
		}

		return $this->style->getPropertyValue('initial-value');
	}

	public function getCssText(): string
	{
		if (!$this->hasEmptyInitialValue || ($this->style !== null && isset($this->style['initial-value']))) {
			return parent::getCssText();
		}
		$body = $this->style?->getCssText() ?? '';
		$extra = 'initial-value: ;';
		$content = $body === '' ? $extra : $body . ' ' . $extra;

		return '@' . $this->name . ' ' . CSSSyntaxScanner::trimCssWhitespace($this->prelude) . ' { ' . $content . ' }';
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
