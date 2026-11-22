<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

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
	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
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

	public function getSyntax(): string
	{
		return trim($this->style?->getPropertyValue('syntax') ?? '', " \t\n\r\0\x0B\"'");
	}

	public function getInherits(): bool
	{
		return strtolower($this->style?->getPropertyValue('inherits') ?? '') === 'true';
	}

	public function getInitialValue(): ?string
	{
		if ($this->style === null || !isset($this->style['initial-value'])) {
			return null;
		}

		return $this->style->getPropertyValue('initial-value');
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
}
