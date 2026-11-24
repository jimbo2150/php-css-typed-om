<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Style declaration block for CSS style rules, keyframe rules, and nested declarations.
 *
 * @property string $cssFloat
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleProperties
 */
final class CSSStyleProperties extends CSSStyleDeclaration
{
	public static function fromDeclaration(?CSSStyleDeclaration $style): self
	{
		if ($style instanceof self) {
			return $style->getParentRule() !== null ? clone $style : $style;
		}

		$properties = new self();
		if ($style !== null) {
			foreach ($style->getDeclarationsRaw() as $property => $declaration) {
				$properties->setValidatedProperty($property, $declaration['value'], $declaration['priority']);
			}
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
