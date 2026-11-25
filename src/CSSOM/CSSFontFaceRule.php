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
		if ($style instanceof CSSFontFaceDescriptors) {
			$descriptors = $style->getParentRule() !== null ? clone $style : $style;
		} else {
			$descriptors = new CSSFontFaceDescriptors();
			if ($style !== null) {
				foreach ($style->getDeclarationsRaw() as $property => $declaration) {
					$descriptors->setValidatedProperty($property, $declaration['value'], $declaration['priority']);
				}
			}
		}
		parent::__construct('font-face', '', $descriptors, true, $maxBytes);
	}

	public function getStyle(): CSSFontFaceDescriptors
	{
		assert($this->styleDeclaration instanceof CSSFontFaceDescriptors);
		return $this->styleDeclaration;
	}
}
