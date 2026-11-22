<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Style declaration block for CSS style rules, keyframe rules, and nested declarations.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleProperties
 */
final class CSSStyleProperties extends CSSStyleDeclaration
{
	public static function fromDeclaration(?CSSStyleDeclaration $style): self
	{
		if ($style instanceof self) {
			return $style;
		}

		$properties = new self();
		foreach ($style ?? [] as $property => $declaration) {
			$properties->setProperty($property, $declaration['value'], $declaration['priority']);
		}

		return $properties;
	}

	public function getCssFloat(): string
	{
		return $this->getPropertyValue('float');
	}

	public function setCssFloat(string $value): void
	{
		$this->setProperty('float', $value);
	}
}
