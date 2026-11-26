<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents a CSS `@font-face` descriptor rule.
 *
 * @property-read CSSFontFaceDescriptors $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFaceRule
 */
final class CSSFontFaceRule extends CSSAtRule
{
	public function __construct(?CSSStyleDeclaration $style = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->initAtRuleInternal('font-face', '', CSSFontFaceDescriptors::adoptOrConvert($style, $maxBytes), true, $maxBytes);
	}

	public function validatesDescriptorPropertyValuesInternal(): bool
	{
		return false;
	}

	public function getStyle(): CSSFontFaceDescriptors
	{
		assert($this->styleDeclaration instanceof CSSFontFaceDescriptors);
		return $this->styleDeclaration;
	}
}
