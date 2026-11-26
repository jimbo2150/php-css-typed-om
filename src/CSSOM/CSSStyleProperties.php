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
		return self::adoptOrConvert($style);
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
