<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS `@position-try` fallback rule for anchor positioning.
 *
 * @property-read string $name
 * @property-read CSSPositionTryDescriptors $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionTryRule
 */
final class CSSPositionTryRule extends CSSAtRule
{
	public function __construct(string $name, ?CSSStyleDeclaration $style = null)
	{
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('@position-try requires a dashed identifier starting with "--".');
		}
		if ($style instanceof CSSPositionTryDescriptors) {
			$descriptors = $style->getParentRule() !== null ? clone $style : $style;
		} else {
			$descriptors = new CSSPositionTryDescriptors();
			foreach ($style ?? [] as $property => $declaration) {
				$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
			}
		}
		parent::__construct('position-try', $normalized, $descriptors, true);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getStyle(): CSSPositionTryDescriptors
	{
		assert($this->styleDeclaration instanceof CSSPositionTryDescriptors);
		return $this->styleDeclaration;
	}

	public function getType(): int
	{
		return self::POSITION_TRY_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'name' ? $this->getNameText() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'name' || parent::__isset($name);
	}
}
