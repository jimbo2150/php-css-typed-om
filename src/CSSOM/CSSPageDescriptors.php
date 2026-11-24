<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Strict descriptor declaration block for `@page` rules (CSS Paged Media Module Level 3).
 *
 * @property string $size
 * @property string $pageOrientation
 * @property string $marks
 * @property string $bleed
 * @property string $margin
 * @property string $marginTop
 * @property string $marginRight
 * @property string $marginBottom
 * @property string $marginLeft
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPageDescriptors
 */
final class CSSPageDescriptors extends CSSStyleDeclaration
{
	/** @var array<string, true> */
	private const array ALLOWED = [
		'size' => true,
		'page-orientation' => true,
		'marks' => true,
		'bleed' => true,
		'margin' => true,
		'margin-top' => true,
		'margin-right' => true,
		'margin-bottom' => true,
		'margin-left' => true,
		'margin-block' => true,
		'margin-block-start' => true,
		'margin-block-end' => true,
		'margin-inline' => true,
		'margin-inline-start' => true,
		'margin-inline-end' => true,
		'padding' => true,
		'padding-top' => true,
		'padding-right' => true,
		'padding-bottom' => true,
		'padding-left' => true,
		'padding-block' => true,
		'padding-block-start' => true,
		'padding-block-end' => true,
		'padding-inline' => true,
		'padding-inline-start' => true,
		'padding-inline-end' => true,
		'border' => true,
		'border-top' => true,
		'border-right' => true,
		'border-bottom' => true,
		'border-left' => true,
		'border-block' => true,
		'border-block-start' => true,
		'border-block-end' => true,
		'border-inline' => true,
		'border-inline-start' => true,
		'border-inline-end' => true,
		'border-width' => true,
		'border-style' => true,
		'border-color' => true,
		'border-top-width' => true,
		'border-right-width' => true,
		'border-bottom-width' => true,
		'border-left-width' => true,
		'border-top-style' => true,
		'border-right-style' => true,
		'border-bottom-style' => true,
		'border-left-style' => true,
		'border-top-color' => true,
		'border-right-color' => true,
		'border-bottom-color' => true,
		'border-left-color' => true,
		'border-block-width' => true,
		'border-block-start-width' => true,
		'border-block-end-width' => true,
		'border-block-style' => true,
		'border-block-start-style' => true,
		'border-block-end-style' => true,
		'border-block-color' => true,
		'border-block-start-color' => true,
		'border-block-end-color' => true,
		'border-inline-width' => true,
		'border-inline-start-width' => true,
		'border-inline-end-width' => true,
		'border-inline-style' => true,
		'border-inline-start-style' => true,
		'border-inline-end-style' => true,
		'border-inline-color' => true,
		'border-inline-start-color' => true,
		'border-inline-end-color' => true,
		'border-radius' => true,
		'border-top-left-radius' => true,
		'border-top-right-radius' => true,
		'border-bottom-right-radius' => true,
		'border-bottom-left-radius' => true,
		'border-start-start-radius' => true,
		'border-start-end-radius' => true,
		'border-end-start-radius' => true,
		'border-end-end-radius' => true,
		'border-image' => true,
		'border-image-source' => true,
		'border-image-slice' => true,
		'border-image-width' => true,
		'border-image-outset' => true,
		'border-image-repeat' => true,
		'background' => true,
		'background-color' => true,
		'background-image' => true,
		'background-position' => true,
		'background-repeat' => true,
		'background-size' => true,
		'background-origin' => true,
		'background-clip' => true,
		'background-attachment' => true,
		'color' => true,
		'font' => true,
		'font-family' => true,
		'font-size' => true,
		'font-style' => true,
		'font-weight' => true,
		'font-variant' => true,
		'font-stretch' => true,
		'line-height' => true,
		'direction' => true,
		'writing-mode' => true,
		'text-align' => true,
		'text-orientation' => true,
		'text-transform' => true,
		'letter-spacing' => true,
		'word-spacing' => true,
		'white-space' => true,
		'quotes' => true,
		'visibility' => true,
		'orphans' => true,
		'widows' => true,
		'counter-reset' => true,
		'counter-increment' => true,
		'counter-set' => true,
	];

	/** @return array<string, true> */
	protected static function allowedDescriptors(): array
	{
		return self::ALLOWED;
	}
}
