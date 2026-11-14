<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents a CSS `@font-face` descriptor rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFaceRule
 */
final class CSSFontFaceRule extends CSSAtRule
{
	public function __construct(CSSStyleDeclaration $style)
	{
		$descriptors = new CSSFontFaceDescriptors();
		foreach ($style as $property => $declaration) {
			$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
		}
		parent::__construct('font-face', '', $descriptors, true);
	}

	public function getStyle(): CSSFontFaceDescriptors
	{
		return $this->style;
	}
}
