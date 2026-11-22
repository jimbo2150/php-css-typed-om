<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Strict descriptor declaration block for `@position-try` rules (CSS Anchor Positioning Module Level 1).
 *
 * @property string $positionAnchor
 * @property string $positionArea
 * @property string $inset
 * @property string $top
 * @property string $right
 * @property string $bottom
 * @property string $left
 * @property string $margin
 * @property string $width
 * @property string $height
 * @property string $alignSelf
 * @property string $justifySelf
 * @property string $placeSelf
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionTryDescriptors
 */
final class CSSPositionTryDescriptors extends CSSStyleDeclaration
{
	/** @var array<string, true> */
	private const array ALLOWED = [
		'top' => true,
		'right' => true,
		'bottom' => true,
		'left' => true,
		'inset' => true,
		'inset-block' => true,
		'inset-block-start' => true,
		'inset-block-end' => true,
		'inset-inline' => true,
		'inset-inline-start' => true,
		'inset-inline-end' => true,
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
		'width' => true,
		'height' => true,
		'min-width' => true,
		'min-height' => true,
		'max-width' => true,
		'max-height' => true,
		'block-size' => true,
		'inline-size' => true,
		'min-block-size' => true,
		'min-inline-size' => true,
		'max-block-size' => true,
		'max-inline-size' => true,
		'align-self' => true,
		'justify-self' => true,
		'place-self' => true,
		'position-anchor' => true,
		'position-area' => true,
	];

	protected static function allowedDescriptors(): ?array
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
}
