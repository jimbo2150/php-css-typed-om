<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Strict descriptor declaration block for `@font-face` rules (CSS Fonts Module Level 4/5).
 *
 * @property string $ascentOverride
 * @property string $descentOverride
 * @property string $fontDisplay
 * @property string $fontFamily
 * @property string $fontFeatureSettings
 * @property string $fontLanguageOverride
 * @property string $fontNamedInstance
 * @property string $fontStretch
 * @property string $fontStyle
 * @property string $fontVariationSettings
 * @property string $fontWeight
 * @property string $fontWidth
 * @property string $lineGapOverride
 * @property string $sizeAdjust
 * @property string $src
 * @property string $unicodeRange
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFaceDescriptors
 */
final class CSSFontFaceDescriptors extends CSSStyleDeclaration
{
	/** @var array<string, true> */
	private const array ALLOWED = [
		'ascent-override' => true,
		'descent-override' => true,
		'font-display' => true,
		'font-family' => true,
		'font-feature-settings' => true,
		'font-language-override' => true,
		'font-named-instance' => true,
		'font-stretch' => true,
		'font-style' => true,
		'font-variation-settings' => true,
		'font-weight' => true,
		'font-width' => true,
		'line-gap-override' => true,
		'size-adjust' => true,
		'src' => true,
		'unicode-range' => true,
	];

	/** @return array<string, true> */
	protected static function allowedDescriptors(): array
	{
		return self::ALLOWED;
	}

	protected static function allowsCustomProperties(): bool
	{
		return false;
	}

	protected static function allowsImportant(): bool
	{
		return false;
	}

	protected static function validatesPropertyValues(): bool
	{
		return false;
	}
}
