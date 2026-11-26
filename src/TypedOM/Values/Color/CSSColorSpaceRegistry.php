<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

/**
 * Unified internal lookup tables for CSS Color 4 / Color 5 spaces, channels, and parsing specs.
 *
 * @internal
 */
final class CSSColorSpaceRegistry
{
	/**
	 * Map of canonical space name -> 3 analogous category bit flags (`CSSColorConversionMeta::BIT_*`).
	 *
	 * @var array<string, array{0: int, 1: int, 2: int}>
	 */
	public const array ANALOGOUS_BITS = [
		'rgb' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'srgb' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'srgb-linear' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'display-p3' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'a98-rgb' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'prophoto-rgb' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'rec2020' => [CSSColorConversionMeta::BIT_RED, CSSColorConversionMeta::BIT_GREEN, CSSColorConversionMeta::BIT_BLUE],
		'hsl' => [CSSColorConversionMeta::BIT_HUE, CSSColorConversionMeta::BIT_COLORFULNESS, CSSColorConversionMeta::BIT_LIGHTNESS],
		'hwb' => [CSSColorConversionMeta::BIT_HUE, 0, 0],
		'lab' => [CSSColorConversionMeta::BIT_LIGHTNESS, CSSColorConversionMeta::BIT_OPPONENT_A, CSSColorConversionMeta::BIT_OPPONENT_B],
		'lch' => [CSSColorConversionMeta::BIT_LIGHTNESS, CSSColorConversionMeta::BIT_COLORFULNESS, CSSColorConversionMeta::BIT_HUE],
		'oklab' => [CSSColorConversionMeta::BIT_LIGHTNESS, CSSColorConversionMeta::BIT_OPPONENT_A, CSSColorConversionMeta::BIT_OPPONENT_B],
		'oklch' => [CSSColorConversionMeta::BIT_LIGHTNESS, CSSColorConversionMeta::BIT_COLORFULNESS, CSSColorConversionMeta::BIT_HUE],
		'xyz' => [0, 0, 0],
		'xyz-d50' => [0, 0, 0],
		'xyz-d65' => [0, 0, 0],
	];

	/**
	 * Map of bounded CSS color space name -> canonical RGB gamut space.
	 *
	 * @var array<string, string>
	 */
	public const array BOUNDED_TO_GAMUT_SPACE = [
		'rgb' => 'srgb',
		'rgba' => 'srgb',
		'hsl' => 'srgb',
		'hsla' => 'srgb',
		'hwb' => 'srgb',
		'srgb' => 'srgb',
		'srgb-linear' => 'srgb-linear',
		'display-p3' => 'display-p3',
		'a98-rgb' => 'a98-rgb',
		'prophoto-rgb' => 'prophoto-rgb',
		'rec2020' => 'rec2020',
	];

	/** @var array<string, true> */
	public const array UNBOUNDED_SPACES = [
		'lab' => true,
		'lch' => true,
		'oklab' => true,
		'oklch' => true,
		'xyz' => true,
		'xyz-d50' => true,
		'xyz-d65' => true,
	];

	/**
	 * Color interpolation spaces; true = polar (has hue channel), false = rectangular.
	 *
	 * @var array<string, bool>
	 */
	public const array INTERPOLATION_SPACES = [
		'srgb' => false,
		'srgb-linear' => false,
		'display-p3' => false,
		'a98-rgb' => false,
		'prophoto-rgb' => false,
		'rec2020' => false,
		'lab' => false,
		'oklab' => false,
		'xyz' => false,
		'xyz-d50' => false,
		'xyz-d65' => false,
		'hsl' => true,
		'hwb' => true,
		'lch' => true,
		'oklch' => true,
	];

	/** @var array<string, true> */
	public const array RELATIVE_FUNCTIONS = [
		'rgb' => true,
		'rgba' => true,
		'hsl' => true,
		'hsla' => true,
		'hwb' => true,
		'lab' => true,
		'lch' => true,
		'oklab' => true,
		'oklch' => true,
		'color' => true,
	];

	/** @var array<string, array{0: string, 1: string, 2: string}> */
	public const array RELATIVE_CHANNEL_NAMES = [
		'rgb' => ['r', 'g', 'b'],
		'rgba' => ['r', 'g', 'b'],
		'hsl' => ['h', 's', 'l'],
		'hsla' => ['h', 's', 'l'],
		'hwb' => ['h', 'w', 'b'],
		'lab' => ['l', 'a', 'b'],
		'lch' => ['l', 'c', 'h'],
		'oklab' => ['l', 'a', 'b'],
		'oklch' => ['l', 'c', 'h'],
	];

	/**
	 * Descriptor table for standard space-separated 3-channel Color 4 functions.
	 *
	 * @var array<string, array{class: class-string<CSSColorValue>, ch0: string, ch1: string, ch2: string}>
	 */
	public const array LEAF_THREE_CHANNEL_SPEC = [
		'hwb' => ['class' => CSSHWB::class, 'ch0' => 'angle', 'ch1' => 'percent1', 'ch2' => 'percent1'],
		'lab' => ['class' => CSSLab::class, 'ch0' => 'percent1', 'ch1' => 'numberOrPercent', 'ch2' => 'numberOrPercent'],
		'lch' => ['class' => CSSLCH::class, 'ch0' => 'percent1', 'ch1' => 'numberOrPercent', 'ch2' => 'angle'],
		'oklab' => ['class' => CSSOKLab::class, 'ch0' => 'percent100', 'ch1' => 'numberOrPercent', 'ch2' => 'numberOrPercent'],
		'oklch' => ['class' => CSSOKLCH::class, 'ch0' => 'percent100', 'ch1' => 'numberOrPercent', 'ch2' => 'angle'],
	];
}
