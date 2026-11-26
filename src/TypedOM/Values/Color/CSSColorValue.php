<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;

/**
 * Abstract base class for CSS Typed OM color values (CSS Color 4 / Color 5 Typed OM).
 *
 * @see https://drafts.css-houdini.org/css-typed-om-1/#colorvalue-objects
 */
abstract class CSSColorValue extends CSSStyleValue
{
	private const float D50_X = 0.3457 / 0.3585;
	private const float D50_Z = (1.0 - 0.3457 - 0.3585) / 0.3585;
	private const float LAB_EPSILON = 216.0 / 24389.0;
	private const float LAB_KAPPA = 24389.0 / 27.0;
	private const float THIRD = 1.0 / 3.0;

	/** @var array<string, int> */
	private const array NAMED_COLORS = [
		'aliceblue' => 0xF0F8FF,
		'antiquewhite' => 0xFAEBD7,
		'aqua' => 0x00FFFF,
		'aquamarine' => 0x7FFFD4,
		'azure' => 0xF0FFFF,
		'beige' => 0xF5F5DC,
		'bisque' => 0xFFE4C4,
		'black' => 0x000000,
		'blanchedalmond' => 0xFFEBCD,
		'blue' => 0x0000FF,
		'blueviolet' => 0x8A2BE2,
		'brown' => 0xA52A2A,
		'burlywood' => 0xDEB887,
		'cadetblue' => 0x5F9EA0,
		'chartreuse' => 0x7FFF00,
		'chocolate' => 0xD2691E,
		'coral' => 0xFF7F50,
		'cornflowerblue' => 0x6495ED,
		'cornsilk' => 0xFFF8DC,
		'crimson' => 0xDC143C,
		'cyan' => 0x00FFFF,
		'darkblue' => 0x00008B,
		'darkcyan' => 0x008B8B,
		'darkgoldenrod' => 0xB8860B,
		'darkgray' => 0xA9A9A9,
		'darkgreen' => 0x006400,
		'darkgrey' => 0xA9A9A9,
		'darkkhaki' => 0xBDB76B,
		'darkmagenta' => 0x8B008B,
		'darkolivegreen' => 0x556B2F,
		'darkorange' => 0xFF8C00,
		'darkorchid' => 0x9932CC,
		'darkred' => 0x8B0000,
		'darksalmon' => 0xE9967A,
		'darkseagreen' => 0x8FBC8F,
		'darkslateblue' => 0x483D8B,
		'darkslategray' => 0x2F4F4F,
		'darkslategrey' => 0x2F4F4F,
		'darkturquoise' => 0x00CED1,
		'darkviolet' => 0x9400D3,
		'deeppink' => 0xFF1493,
		'deepskyblue' => 0x00BFFF,
		'dimgray' => 0x696969,
		'dimgrey' => 0x696969,
		'dodgerblue' => 0x1E90FF,
		'firebrick' => 0xB22222,
		'floralwhite' => 0xFFFAF0,
		'forestgreen' => 0x228B22,
		'fuchsia' => 0xFF00FF,
		'gainsboro' => 0xDCDCDC,
		'ghostwhite' => 0xF8F8FF,
		'gold' => 0xFFD700,
		'goldenrod' => 0xDAA520,
		'gray' => 0x808080,
		'green' => 0x008000,
		'greenyellow' => 0xADFF2F,
		'grey' => 0x808080,
		'honeydew' => 0xF0FFF0,
		'hotpink' => 0xFF69B4,
		'indianred' => 0xCD5C5C,
		'indigo' => 0x4B0082,
		'ivory' => 0xFFFFF0,
		'khaki' => 0xF0E68C,
		'lavender' => 0xE6E6FA,
		'lavenderblush' => 0xFFF0F5,
		'lawngreen' => 0x7CFC00,
		'lemonchiffon' => 0xFFFACD,
		'lightblue' => 0xADD8E6,
		'lightcoral' => 0xF08080,
		'lightcyan' => 0xE0FFFF,
		'lightgoldenrodyellow' => 0xFAFAD2,
		'lightgray' => 0xD3D3D3,
		'lightgreen' => 0x90EE90,
		'lightgrey' => 0xD3D3D3,
		'lightpink' => 0xFFB6C1,
		'lightsalmon' => 0xFFA07A,
		'lightseagreen' => 0x20B2AA,
		'lightskyblue' => 0x87CEFA,
		'lightslategray' => 0x778899,
		'lightslategrey' => 0x778899,
		'lightsteelblue' => 0xB0C4DE,
		'lightyellow' => 0xFFFFE0,
		'lime' => 0x00FF00,
		'limegreen' => 0x32CD32,
		'linen' => 0xFAF0E6,
		'magenta' => 0xFF00FF,
		'maroon' => 0x800000,
		'mediumaquamarine' => 0x66CDAA,
		'mediumblue' => 0x0000CD,
		'mediumorchid' => 0xBA55D3,
		'mediumpurple' => 0x9370DB,
		'mediumseagreen' => 0x3CB371,
		'mediumslateblue' => 0x7B68EE,
		'mediumspringgreen' => 0x00FA9A,
		'mediumturquoise' => 0x48D1CC,
		'mediumvioletred' => 0xC71585,
		'midnightblue' => 0x191970,
		'mintcream' => 0xF5FFFA,
		'mistyrose' => 0xFFE4E1,
		'moccasin' => 0xFFE4B5,
		'navajowhite' => 0xFFDEAD,
		'navy' => 0x000080,
		'oldlace' => 0xFDF5E6,
		'olive' => 0x808000,
		'olivedrab' => 0x6B8E23,
		'orange' => 0xFFA500,
		'orangered' => 0xFF4500,
		'orchid' => 0xDA70D6,
		'palegoldenrod' => 0xEEE8AA,
		'palegreen' => 0x98FB98,
		'paleturquoise' => 0xAFEEEE,
		'palevioletred' => 0xDB7093,
		'papayawhip' => 0xFFEFD5,
		'peachpuff' => 0xFFDAB9,
		'peru' => 0xCD853F,
		'pink' => 0xFFC0CB,
		'plum' => 0xDDA0DD,
		'powderblue' => 0xB0E0E6,
		'purple' => 0x800080,
		'rebeccapurple' => 0x663399,
		'red' => 0xFF0000,
		'rosybrown' => 0xBC8F8F,
		'royalblue' => 0x4169E1,
		'saddlebrown' => 0x8B4513,
		'salmon' => 0xFA8072,
		'sandybrown' => 0xF4A460,
		'seagreen' => 0x2E8B57,
		'seashell' => 0xFFF5EE,
		'sienna' => 0xA0522D,
		'silver' => 0xC0C0C0,
		'skyblue' => 0x87CEEB,
		'slateblue' => 0x6A5ACD,
		'slategray' => 0x708090,
		'slategrey' => 0x708090,
		'snow' => 0xFFFAFA,
		'springgreen' => 0x00FF7F,
		'steelblue' => 0x4682B4,
		'tan' => 0xD2B48C,
		'teal' => 0x008080,
		'thistle' => 0xD8BFD8,
		'tomato' => 0xFF6347,
		'turquoise' => 0x40E0D0,
		'violet' => 0xEE82EE,
		'wheat' => 0xF5DEB3,
		'white' => 0xFFFFFF,
		'whitesmoke' => 0xF5F5F5,
		'yellow' => 0xFFFF00,
		'yellowgreen' => 0x9ACD32,
	];

	protected CSSNumericValue|CSSKeywordValue $alphaValue;

	public CSSNumericValue|CSSKeywordValue $alpha {
		get => self::exposeComponent($this->alphaValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->alphaValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	/**
	 * Parses a CSS color value (`#hex`, named colors, `currentcolor`, `rgb()`, `hsl()`, `hwb()`,
	 * `lab()`, `lch()`, `oklab()`, `oklch()`, `color()`, `color-mix()`, `light-dark()`, and relative colors).
	 *
	 * Accepts either a single CSS color string (`CSSColorValue::parse('#ff0000')`) or
	 * a `(property, cssText)` pair matching `CSSStyleValue::parse()`.
	 *
	 * @throws InvalidArgumentException When the color syntax is invalid, deferred, uses `var()`, or exceeds limits
	 */
	public static function parse(
		string $propertyOrCssText,
		?string $cssText = null,
		?CSSParserOptions $options = null,
		?CSSContext $context = null,
	): self|CSSKeywordValue {
		return self::parseInternal($propertyOrCssText, $cssText, $options, $context, false);
	}

	/**
	 * @internal Entry point allowing `CSSStyleValue` to defer `light-dark()` when no `CSSContext` is supplied.
	 * @throws CSSDeferredColorException When syntactically valid but requiring runtime context
	 * @throws CSSParserLimitException When resource limits are exceeded
	 * @throws InvalidArgumentException When the color syntax is invalid
	 */
	public static function parseInternal(
		string $propertyOrCssText,
		?string $cssText = null,
		?CSSParserOptions $options = null,
		?CSSContext $context = null,
		bool $deferLightDark = false,
	): self|CSSKeywordValue {
		$options ??= CSSParserOptions::default();
		if ($cssText !== null) {
			if ($options->maxBytes !== null && strlen($propertyOrCssText) > $options->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $options->maxBytes));
			}
			CSSStyleDeclaration::normalizePropertyName($propertyOrCssText);
		}
		$rawSource = $cssText ?? $propertyOrCssText;
		if ($options->maxBytes !== null && strlen($rawSource) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		$source = CSSSyntaxScanner::trimCssWhitespace($rawSource);
		if ($source === '') {
			throw new InvalidArgumentException('CSS color value cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($source, 'CSS color', [';', '{', '}']);

		if ($source[0] === '#') {
			return self::parseHex($source);
		}

		$open = strpos($source, '(');
		if ($open === false) {
			$current = self::tryParseCurrentColorInternal($source, $context);
			if ($current !== null) {
				return $current;
			}
			$named = self::tryParseNamedColor($source);
			if ($named !== null) {
				return $named;
			}
			throw new InvalidArgumentException('Unsupported CSS color value: ' . $source);
		}
		$function = CSSSyntaxScanner::decodeLeadingFunctionName($source, $open);
		if ($function === null) {
			throw new InvalidArgumentException('Unsupported CSS color value: ' . $source);
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($source, $open);
		if ($close !== strlen($source) - 1) {
			throw new InvalidArgumentException('Invalid CSS color function syntax: ' . $source);
		}

		if ($function === 'color-mix'
			|| $function === 'light-dark'
			|| self::hasLeadingFromKeywordInternal($source, $open + 1, $close)
		) {
			return CSSColor5Evaluator::evaluate($source, $options, $context, $deferLightDark);
		}

		$body = CSSSyntaxScanner::trimCssWhitespace(self::stripTopLevelComments(substr($source, $open + 1, $close - $open - 1)));
		if ($body === '') {
			throw new InvalidArgumentException('Empty CSS color function body.');
		}

		$mathBudget = 0;

		return self::parseLeafFunctionBody($function, $body, $options, $mathBudget);
	}

	/**
	 * @internal Parses a leaf CSS Color 4 function (`rgb`, `hsl`, `hwb`, `lab`, `lch`, `oklab`, `oklch`, `color`) with shared math budget.
	 */
	public static function parseLeafFunctionInternal(
		string $function,
		string $source,
		CSSParserOptions $options,
		int &$mathBudget,
		?int $open = null,
		?int $close = null,
	): self {
		$open ??= strpos($source, '(');
		if ($open === false) {
			throw new InvalidArgumentException('Unsupported CSS color function: ' . $function);
		}
		$close ??= strlen($source) - 1;
		$body = CSSSyntaxScanner::trimCssWhitespace(self::stripTopLevelComments(substr($source, $open + 1, $close - $open - 1)));
		if ($body === '') {
			throw new InvalidArgumentException('Empty CSS color function body.');
		}

		return self::parseLeafFunctionBody($function, $body, $options, $mathBudget);
	}

	private static function parseLeafFunctionBody(
		string $function,
		string $body,
		CSSParserOptions $options,
		int &$mathBudget,
	): self {
		return match ($function) {
			'rgb', 'rgba' => self::parseRgbFunction($body, $options, $mathBudget),
			'hsl', 'hsla' => self::parseHslFunction($body, $options, $mathBudget),
			'hwb', 'lab', 'lch', 'oklab', 'oklch' => self::parseStandardThreeChannelFunction($function, $body, $options, $mathBudget),
			'color' => self::parseColorFunction($body, $options, $mathBudget),
			default => throw new InvalidArgumentException('Unsupported CSS color function: ' . $function),
		};
	}

	/** @internal */
	public static function hasLeadingFromKeywordInternal(string $source, int $start, int $end): bool
	{
		while ($start < $end) {
			$start += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $start, $end - $start);
			if ($start + 1 < $end && $source[$start] === '/' && $source[$start + 1] === '*') {
				$cEnd = strpos($source, '*/', $start + 2);
				if ($cEnd === false || $cEnd + 1 >= $end) {
					return false;
				}
				$start = $cEnd + 2;
				continue;
			}
			break;
		}
		if ($start >= $end) {
			return false;
		}
		$ident = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
		if ($ident === null || strcasecmp($ident['ident'], 'from') !== 0) {
			return false;
		}
		$after = $ident['end'];
		if ($after >= $end) {
			return false;
		}
		$next = $source[$after];

		return str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $next)
			|| ($next === '/' && $after + 1 < $end && $source[$after + 1] === '*');
	}

	public function getAlpha(): CSSNumericValue|CSSKeywordValue
	{
		return self::exposeComponent($this->alphaValue);
	}

	public function setAlpha(CSSNumericValue|CSSKeywordValue|int|float $alpha): void
	{
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function inGamut(?string $colorSpace = null): bool
	{
		return CSSColorGamutMapper::inGamut($this, $colorSpace);
	}

	public function toGamut(?string $colorSpace = null): self
	{
		return CSSColorGamutMapper::map($this, $colorSpace);
	}

	public function toRGB(bool $gamutMap = false): CSSRGB
	{
		if ($gamutMap) {
			/** @var CSSRGB */
			return CSSColorGamutMapper::map($this, 'rgb');
		}

		[$r, $g, $b] = $this->toSrgbChannels();

		/** @var CSSRGB */
		return self::buildFromFloatsInternal('rgb', $r, $g, $b, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	/**
	 * Converts this color to HSL.
	 *
	 * Per CSS Color 4 §7.1, the HSL coordinate system has a singularity at exact lightness `l === 0.0`
	 * or `l === 1.0` (`min(l, 1 - l) === 0`), so saturation evaluates to `0%` there even for extended sRGB,
	 * and hue is powerless (`none`) when `s` is ~0.
	 */
	public function toHSL(bool $gamutMap = false): CSSHSL
	{
		if ($gamutMap) {
			/** @var CSSHSL */
			return CSSColorGamutMapper::map($this, 'hsl');
		}

		[$r, $g, $b] = $this->toSrgbChannels();
		[$h, $s, $l] = self::srgbFloatsToHslInternal($r, $g, $b);

		/** @var CSSHSL */
		return self::buildFromFloatsInternal('hsl', $h, $s, $l, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function toHWB(bool $gamutMap = false): CSSHWB
	{
		if ($gamutMap) {
			/** @var CSSHWB */
			return CSSColorGamutMapper::map($this, 'hwb');
		}

		[$r, $g, $b] = $this->toSrgbChannels();
		[$h, $w, $bVal] = self::srgbFloatsToHwbInternal($r, $g, $b);

		/** @var CSSHWB */
		return self::buildFromFloatsInternal('hwb', $h, $w, $bVal, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function toLab(): CSSLab
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$x50, $y50, $z50] = self::xyzD65ToD50($x, $y, $z);
		[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);

		/** @var CSSLab */
		return self::buildFromFloatsInternal('lab', $l, $a, $b, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function toLCH(): CSSLCH
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$x50, $y50, $z50] = self::xyzD65ToD50($x, $y, $z);
		[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		/** @var CSSLCH */
		return self::buildFromFloatsInternal('lch', $l, $c, $h, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function toOKLab(): CSSOKLab
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);

		/** @var CSSOKLab */
		return self::buildFromFloatsInternal('oklab', $l, $a, $b, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function toOKLCH(): CSSOKLCH
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		/** @var CSSOKLCH */
		return self::buildFromFloatsInternal('oklch', $l, $c, $h, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	public function to(string $colorSpace, bool $gamutMap = false): self
	{
		$space = strtolower(CSSSyntaxScanner::trimCssWhitespace($colorSpace));
		if ($gamutMap && CSSColorGamutMapper::isSupportedTargetSpace($space)) {
			return CSSColorGamutMapper::map($this, $space);
		}

		return match ($space) {
			'rgb', 'rgba' => $this->toRGB(),
			'hsl', 'hsla' => $this->toHSL(),
			'hwb' => $this->toHWB(),
			'lab' => $this->toLab(),
			'lch' => $this->toLCH(),
			'oklab' => $this->toOKLab(),
			'oklch' => $this->toOKLCH(),
			'srgb', 'srgb-linear', 'display-p3', 'a98-rgb', 'prophoto-rgb', 'rec2020', 'xyz', 'xyz-d50', 'xyz-d65' => $this->toPredefinedColor($space),
			default => throw new InvalidArgumentException('Unsupported target color space: ' . $colorSpace),
		};
	}

	public function clone(): static
	{
		return clone $this;
	}

	/**
	 * @internal Constructs a CSSColorValue in $targetSpace from canonical float values with §4.4.1/§12.2 handling.
	 */
	public static function buildFromFloatsInternal(
		string $targetSpace,
		float $c0,
		float $c1,
		float $c2,
		CSSNumericValue|CSSKeywordValue $alpha,
		int $missingMask = 0,
	): self {
		if ($targetSpace === 'rgb' || $targetSpace === 'rgba') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'rgb',
				$c0 * 100.0,
				CSSUnitEnum::PERCENT,
				$c1 * 100.0,
				CSSUnitEnum::PERCENT,
				$c2 * 100.0,
				CSSUnitEnum::PERCENT,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSRGB::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'hsl' || $targetSpace === 'hsla') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'hsl',
				$c0,
				CSSUnitEnum::ANGLE_deg,
				$c1 * 100.0,
				CSSUnitEnum::PERCENT,
				$c2 * 100.0,
				CSSUnitEnum::PERCENT,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSHSL::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'hwb') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'hwb',
				$c0,
				CSSUnitEnum::ANGLE_deg,
				$c1 * 100.0,
				CSSUnitEnum::PERCENT,
				$c2 * 100.0,
				CSSUnitEnum::PERCENT,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSHWB::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'lab') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'lab',
				$c0,
				CSSUnitEnum::PERCENT,
				$c1,
				CSSUnitEnum::NUMBER,
				$c2,
				CSSUnitEnum::NUMBER,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSLab::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'lch') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'lch',
				$c0,
				CSSUnitEnum::PERCENT,
				$c1,
				CSSUnitEnum::NUMBER,
				$c2,
				CSSUnitEnum::ANGLE_deg,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSLCH::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'oklab') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'oklab',
				$c0 * 100.0,
				CSSUnitEnum::PERCENT,
				$c1,
				CSSUnitEnum::NUMBER,
				$c2,
				CSSUnitEnum::NUMBER,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSOKLab::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}
		if ($targetSpace === 'oklch') {
			$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
				'oklch',
				$c0 * 100.0,
				CSSUnitEnum::PERCENT,
				$c1,
				CSSUnitEnum::NUMBER,
				$c2,
				CSSUnitEnum::ANGLE_deg,
				$c0,
				$c1,
				$c2,
				$missingMask,
			);

			return CSSOKLCH::fromValidated($comps[0], $comps[1], $comps[2], $alpha);
		}

		$comps = CSSColorConversionMeta::applyMissingAndPowerlessFloats(
			$targetSpace,
			$c0,
			CSSUnitEnum::NUMBER,
			$c1,
			CSSUnitEnum::NUMBER,
			$c2,
			CSSUnitEnum::NUMBER,
			$c0,
			$c1,
			$c2,
			$missingMask,
		);

		return CSSColor::fromValidated(
			CSSKeywordValue::fromValidated($targetSpace),
			[$comps[0], $comps[1], $comps[2]],
			$alpha,
		);
	}

	/**
	 * Converts this color to CIE XYZ (D65).
	 *
	 * @return array{0: float, 1: float, 2: float}
	 */
	abstract protected function toXyzD65(): array;

	/**
	 * @return array{0: float, 1: float, 2: float}
	 */
	protected function toSrgbChannels(): array
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$lr, $lg, $lb] = self::xyzD65ToLinearSrgb($x, $y, $z);

		return [
			self::linearToSrgb($lr),
			self::linearToSrgb($lg),
			self::linearToSrgb($lb),
		];
	}

	/** @internal */
	public function cloneAlphaInternal(): CSSNumericValue|CSSKeywordValue
	{
		return $this->alphaValue->clone();
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function xyzD65ToSpaceFloatsInternal(string $space, float $x, float $y, float $z): array
	{
		return match ($space) {
			'xyz', 'xyz-d65' => [$x, $y, $z],
			'xyz-d50' => self::xyzD65ToD50($x, $y, $z),
			'srgb-linear' => self::xyzD65ToLinearSrgb($x, $y, $z),
			'srgb' => self::applySrgbTransfer(self::xyzD65ToLinearSrgb($x, $y, $z)),
			'display-p3' => self::applySrgbTransfer(self::xyzD65ToLinearDisplayP3($x, $y, $z)),
			'a98-rgb' => self::applyA98Transfer(self::xyzD65ToLinearA98($x, $y, $z)),
			'prophoto-rgb' => self::applyProphotoTransfer(self::xyzD50ToLinearProphoto(...self::xyzD65ToD50($x, $y, $z))),
			'rec2020' => self::applyRec2020Transfer(self::xyzD65ToLinearRec2020($x, $y, $z)),
			default => throw new InvalidArgumentException('Unsupported predefined color space: ' . $space),
		};
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function spaceFloatsToXyzD65Internal(string $space, float $c0, float $c1, float $c2): array
	{
		return match ($space) {
			'xyz', 'xyz-d65' => [$c0, $c1, $c2],
			'xyz-d50' => self::xyzD50ToD65($c0, $c1, $c2),
			'srgb-linear' => self::linearSrgbToXyzD65($c0, $c1, $c2),
			'srgb' => self::linearSrgbToXyzD65(self::srgbToLinear($c0), self::srgbToLinear($c1), self::srgbToLinear($c2)),
			'display-p3' => self::linearDisplayP3ToXyzD65(self::srgbToLinear($c0), self::srgbToLinear($c1), self::srgbToLinear($c2)),
			'a98-rgb' => self::linearA98ToXyzD65(self::a98ToLinear($c0), self::a98ToLinear($c1), self::a98ToLinear($c2)),
			'prophoto-rgb' => self::xyzD50ToD65(...self::linearProphotoToXyzD50(self::prophotoToLinear($c0), self::prophotoToLinear($c1), self::prophotoToLinear($c2))),
			'rec2020' => self::linearRec2020ToXyzD65(self::rec2020ToLinear($c0), self::rec2020ToLinear($c1), self::rec2020ToLinear($c2)),
			default => throw new InvalidArgumentException('Unsupported RGB gamut space: ' . $space),
		};
	}

	protected function toPredefinedColor(string $space, bool $gamutMap = false): CSSColor
	{
		if ($gamutMap) {
			/** @var CSSColor */
			return CSSColorGamutMapper::map($this, $space);
		}
		$thisSpace = $this->getSpaceNameInternal();
		$isXyzAlias = ($thisSpace === 'xyz' || $thisSpace === 'xyz-d65') && ($space === 'xyz' || $space === 'xyz-d65');
		if ($this instanceof CSSColor && ($thisSpace === $space || $isXyzAlias)) {
			if ($thisSpace === $space) {
				return $this->clone();
			}

			return CSSColor::fromValidated(
				CSSKeywordValue::fromValidated($space),
				$this->channels,
				$this->alphaValue->clone(),
			);
		}

		$missing = CSSColorConversionMeta::extractMissingMask($this);
		if ($space === 'srgb'
			&& ($this instanceof CSSRGB || $this instanceof CSSHSL || $this instanceof CSSHWB || $thisSpace === 'srgb-linear')
		) {
			$channels = $this->toSrgbChannels();
		} else {
			[$x, $y, $z] = $this->toXyzD65();
			$channels = self::xyzD65ToSpaceFloatsInternal($space, $x, $y, $z);
		}

		/** @var CSSColor */
		return self::buildFromFloatsInternal($space, $channels[0], $channels[1], $channels[2], $this->alphaValue->clone(), $missing);
	}

	/**
	 * @internal Converts this color to normalized interpolation floats + alpha without allocating intermediate CSSColorValue objects.
	 * @return array{0: array{0: ?float, 1: ?float, 2: ?float}, 1: ?float}
	 */
	public function toSpaceFloatsInternal(string $space): array
	{
		$alpha = $this->alphaValue instanceof CSSKeywordValue ? null : self::resolvePercentUnit($this->alphaValue);
		$thisSpace = $this->getSpaceNameInternal();
		$canonThis = $thisSpace === 'xyz' ? 'xyz-d65' : $thisSpace;
		$canonTarget = match ($space) {
			'rgba' => 'rgb',
			'hsla' => 'hsl',
			'xyz' => 'xyz-d65',
			default => $space,
		};

		if ($canonThis === $canonTarget) {
			$comps = $this->getComponentValuesInternal();

			return [match ($canonTarget) {
				'lab' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[0], 100.0),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 125.0),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[2], 125.0),
				],
				'lch' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[0], 100.0),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 150.0),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveAngleDegrees($comps[2]),
				],
				'oklab' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolvePercentUnit($comps[0]),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 0.4),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[2], 0.4),
				],
				'oklch' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolvePercentUnit($comps[0]),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 0.4),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveAngleDegrees($comps[2]),
				],
				'hsl', 'hwb' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolveAngleDegrees($comps[0]),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 100.0),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[2], 100.0),
				],
				'rgb' => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolvePercentUnit($comps[0]),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolvePercentUnit($comps[1]),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolvePercentUnit($comps[2]),
				],
				default => [
					$comps[0] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[0], 1.0),
					$comps[1] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[1], 1.0),
					$comps[2] instanceof CSSKeywordValue ? null : self::resolveLinearChannel($comps[2], 1.0),
				],
			}, $alpha];
		}

		$missing = CSSColorConversionMeta::extractMissingMask($this);
		if ($canonTarget === 'rgb' || $canonTarget === 'srgb') {
			[$r, $g, $b] = $this->toSrgbChannels();

			return [CSSColorConversionMeta::maskFloats($canonTarget, $r, $g, $b, $r, $g, $b, $missing), $alpha];
		}
		if ($canonTarget === 'hsl') {
			[$r, $g, $b] = $this->toSrgbChannels();
			[$h, $s, $l] = self::srgbFloatsToHslInternal($r, $g, $b);

			return [CSSColorConversionMeta::maskFloats('hsl', $h, $s * 100.0, $l * 100.0, $h, $s, $l, $missing), $alpha];
		}
		if ($canonTarget === 'hwb') {
			[$r, $g, $b] = $this->toSrgbChannels();
			[$h, $w, $bl] = self::srgbFloatsToHwbInternal($r, $g, $b);

			return [CSSColorConversionMeta::maskFloats('hwb', $h, $w * 100.0, $bl * 100.0, $h, $w, $bl, $missing), $alpha];
		}
		if ($canonTarget === 'lch' && $this instanceof CSSLab) {
			$comps = $this->getComponentValuesInternal();
			$l = self::resolveLinearChannel($comps[0], 100.0);
			$a = self::resolveLinearChannel($comps[1], 125.0);
			$b = self::resolveLinearChannel($comps[2], 125.0);
			$c = hypot($a, $b);
			$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

			return [CSSColorConversionMeta::maskFloats('lch', $l, $c, $h, $l, $c, $h, $missing), $alpha];
		}
		if ($canonTarget === 'oklch' && $this instanceof CSSOKLab) {
			[$l, $c, $h] = $this->toOklchFloatsInternal();

			return [CSSColorConversionMeta::maskFloats('oklch', $l, $c, $h, $l, $c, $h, $missing), $alpha];
		}
		if (($canonTarget === 'lab' || $canonTarget === 'lch') && $thisSpace === 'xyz-d50') {
			$comps = $this->getComponentValuesInternal();
			$x50 = self::resolveLinearChannel($comps[0], 1.0);
			$y50 = self::resolveLinearChannel($comps[1], 1.0);
			$z50 = self::resolveLinearChannel($comps[2], 1.0);
			[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);
			if ($canonTarget === 'lab') {
				return [CSSColorConversionMeta::maskFloats('lab', $l, $a, $b, $l, $a, $b, $missing), $alpha];
			}
			$c = hypot($a, $b);
			$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

			return [CSSColorConversionMeta::maskFloats('lch', $l, $c, $h, $l, $c, $h, $missing), $alpha];
		}

		[$x, $y, $z] = $this->toXyzD65();
		if ($canonTarget === 'lab') {
			[$x50, $y50, $z50] = self::xyzD65ToD50($x, $y, $z);
			[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);

			return [CSSColorConversionMeta::maskFloats('lab', $l, $a, $b, $l, $a, $b, $missing), $alpha];
		}
		if ($canonTarget === 'lch') {
			[$x50, $y50, $z50] = self::xyzD65ToD50($x, $y, $z);
			[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);
			$c = hypot($a, $b);
			$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

			return [CSSColorConversionMeta::maskFloats('lch', $l, $c, $h, $l, $c, $h, $missing), $alpha];
		}
		if ($canonTarget === 'oklab') {
			[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);

			return [CSSColorConversionMeta::maskFloats('oklab', $l, $a, $b, $l, $a, $b, $missing), $alpha];
		}
		if ($canonTarget === 'oklch') {
			[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);
			$c = hypot($a, $b);
			$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

			return [CSSColorConversionMeta::maskFloats('oklch', $l, $c, $h, $l, $c, $h, $missing), $alpha];
		}

		$channels = self::xyzD65ToSpaceFloatsInternal($canonTarget, $x, $y, $z);

		return [CSSColorConversionMeta::maskFloats($canonTarget, $channels[0], $channels[1], $channels[2], $channels[0], $channels[1], $channels[2], $missing), $alpha];
	}

	/** @internal */
	abstract public function getSpaceNameInternal(): string;

	/** @internal */
	public function hasConcreteChannelsInternal(): bool
	{
		foreach ($this->getComponentValuesInternal() as $comp) {
			if ($comp instanceof CSSMathValue) {
				return false;
			}
		}

		return true;
	}

	/** @internal */
	public function assertConvertibleInternal(): void
	{
		$this->toXyzD65();
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public function toXyzD65Internal(): array
	{
		return $this->toXyzD65();
	}

	/**
	 * @internal
	 * @return array{float, float, float}
	 */
	public static function xyzD65ToOklabInternal(float $x, float $y, float $z): array
	{
		return self::xyzD65ToOklab($x, $y, $z);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public function toRgbSpaceFloatsInternal(string $gamutSpace): array
	{
		if ($gamutSpace === 'srgb') {
			return $this->toSrgbChannels();
		}

		[$x, $y, $z] = $this->toXyzD65();

		return self::xyzD65ToSpaceFloatsInternal($gamutSpace, $x, $y, $z);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function xyzD65ToRgbSpaceFloatsInternal(string $gamutSpace, float $x, float $y, float $z): array
	{
		return self::xyzD65ToSpaceFloatsInternal($gamutSpace, $x, $y, $z);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public function toOklchFloatsInternal(): array
	{
		[$x, $y, $z] = $this->toXyzD65();
		[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		return [$l, $c, $h];
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function oklchToOklabFloatsInternal(float $l, float $c, float $hDeg): array
	{
		$hRad = deg2rad($hDeg);

		return [$l, $c * cos($hRad), $c * sin($hRad)];
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function oklabToRgbSpaceFloatsInternal(string $gamutSpace, float $l, float $a, float $b): array
	{
		[$x, $y, $z] = self::oklabToXyzD65($l, $a, $b);

		return self::xyzD65ToSpaceFloatsInternal($gamutSpace, $x, $y, $z);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function oklchToRgbSpaceFloatsInternal(string $gamutSpace, float $l, float $c, float $hDeg): array
	{
		$hRad = deg2rad($hDeg);

		return self::oklabToRgbSpaceFloatsInternal($gamutSpace, $l, $c * cos($hRad), $c * sin($hRad));
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function rgbSpaceToOklabFloatsInternal(string $gamutSpace, float $c0, float $c1, float $c2): array
	{
		[$x, $y, $z] = self::spaceFloatsToXyzD65Internal($gamutSpace, $c0, $c1, $c2);

		return self::xyzD65ToOklab($x, $y, $z);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function srgbFloatsToHslInternal(float $r, float $g, float $b): array
	{
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$delta = $max - $min;
		$l = ($max + $min) / 2.0;

		if ($delta === 0.0) {
			return [0.0, 0.0, $l];
		}

		$s = ($l === 0.0 || $l === 1.0) ? 0.0 : ($max - $l) / min($l, 1.0 - $l);
		$h = self::rgbToHue($r, $g, $b, $max, $delta);

		return [$h, $s, $l];
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function srgbFloatsToHwbInternal(float $r, float $g, float $b): array
	{
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$delta = $max - $min;
		$h = $delta === 0.0 ? 0.0 : self::rgbToHue($r, $g, $b, $max, $delta);

		return [$h, $min, 1.0 - $max];
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $linear
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function applySrgbTransfer(array $linear): array
	{
		return [self::linearToSrgb($linear[0]), self::linearToSrgb($linear[1]), self::linearToSrgb($linear[2])];
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $linear
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function applyA98Transfer(array $linear): array
	{
		return [self::linearToA98($linear[0]), self::linearToA98($linear[1]), self::linearToA98($linear[2])];
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $linear
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function applyProphotoTransfer(array $linear): array
	{
		return [self::linearToProphoto($linear[0]), self::linearToProphoto($linear[1]), self::linearToProphoto($linear[2])];
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $linear
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function applyRec2020Transfer(array $linear): array
	{
		return [self::linearToRec2020($linear[0]), self::linearToRec2020($linear[1]), self::linearToRec2020($linear[2])];
	}

	/** @internal */
	abstract public function toBoundedCssTextInternal(?int $maxBytes): string;

	/**
	 * @internal
	 * @return list<CSSNumericValue|CSSKeywordValue>
	 */
	abstract public function getComponentValuesInternal(): array;

	public function __toString(): string
	{
		return $this->toBoundedCssTextInternal(CSSParserOptions::DEFAULT_MAX_BYTES);
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function hwbToSrgbFloatsInternal(float $h, float $w, float $b): array
	{
		if ($w + $b >= 1.0) {
			$gray = $w / ($w + $b);

			return [$gray, $gray, $gray];
		}

		[$r, $g, $bl] = self::hslToSrgbFloats($h, 1.0, 0.5);
		$factor = 1.0 - $w - $b;

		return [
			$r * $factor + $w,
			$g * $factor + $w,
			$bl * $factor + $w,
		];
	}

	/** @param list<CSSNumericValue|CSSKeywordValue> $channels */
	protected function serializeFunctionChannelsBounded(
		string $function,
		array $channels,
		?int $maxBytes,
		?string $spacePrefix = null,
	): string {
		$out = $spacePrefix !== null ? $function . '(' . $spacePrefix : $function . '(';
		$used = strlen($out) + 1;
		if ($maxBytes !== null && $used > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
		}
		$prev = $spacePrefix ?? '';
		foreach ($channels as $i => $channel) {
			if ($spacePrefix !== null) {
				$sep = str_contains($prev, '\\') && CSSSyntaxScanner::endsWithBareHexEscape($prev) ? '  ' : ' ';
				$used += strlen($sep);
				if ($maxBytes !== null && $used > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
				}
				$out .= $sep;
			} elseif ($i > 0) {
				$used++;
				if ($maxBytes !== null && $used > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
				}
				$out .= ' ';
			}
			$remaining = $maxBytes !== null ? $maxBytes - $used : null;
			$part = $channel instanceof CSSMathValue
				? $channel->toBoundedCssTextInternal($remaining)
				: (string) $channel;
			$used += strlen($part);
			if ($maxBytes !== null && $used > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $maxBytes));
			}
			$out .= $part;
			$prev = $part;
		}
		$remainingForAlpha = $maxBytes !== null ? $maxBytes - $used : null;

		return $out . $this->formatAlphaSuffixBounded($remainingForAlpha, $maxBytes) . ')';
	}

	protected function formatAlphaSuffixBounded(?int $remainingBytes, ?int $maxBytes = null): string
	{
		if ($this->alphaValue instanceof CSSUnitValue
			&& $this->alphaValue->getUnitObjInternal() === CSSUnitEnum::PERCENT
			&& abs($this->alphaValue->value - 100.0) < 1e-9
		) {
			return '';
		}
		$limitForMessage = $maxBytes ?? $remainingBytes ?? 0;
		if ($remainingBytes !== null && $remainingBytes < 3) {
			throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $limitForMessage));
		}
		$alphaBudget = $remainingBytes !== null ? $remainingBytes - 3 : null;
		$alphaText = $this->alphaValue instanceof CSSMathValue
			? $this->alphaValue->toBoundedCssTextInternal($alphaBudget)
			: (string) $this->alphaValue;
		if ($alphaBudget !== null && strlen($alphaText) > $alphaBudget) {
			throw new CSSParserLimitException(sprintf('CSS color value exceeds maximum size of %d bytes.', $limitForMessage));
		}

		return ' / ' . $alphaText;
	}

	protected static function exposeComponent(CSSNumericValue|CSSKeywordValue $component): CSSNumericValue|CSSKeywordValue
	{
		return $component instanceof CSSKeywordValue ? $component->clone() : $component;
	}

	protected static function normalizeNoneKeyword(CSSKeywordValue $keyword): CSSKeywordValue
	{
		if (strcasecmp($keyword->getValue(), 'none') !== 0) {
			throw new InvalidArgumentException('Only the "none" keyword is permitted as a missing color component.');
		}

		return CSSKeywordValue::fromValidated('none');
	}

	/**
	 * @internal Normalizes a percentage-based color channel (e.g. CSSRGB r/g/b or alpha).
	 * Bare numeric values are multiplied by $numberScale (e.g. 100.0 for 0..1 -> 0%..100%, or 1.0 for 0..100 -> 0%..100%).
	 */
	public static function normalizePercentChannel(
		CSSNumericValue|CSSKeywordValue|int|float $value,
		float $numberScale = 100.0,
		bool $clone = true,
	): CSSNumericValue|CSSKeywordValue {
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			$scaled = (float) $value * $numberScale;
			if (!is_finite($scaled)) {
				throw new InvalidArgumentException('Non-finite value produced for color channel.');
			}
			return new CSSUnitValue($scaled, CSSUnitEnum::PERCENT);
		}
		if ($value instanceof CSSUnitValue) {
			$unitObj = $value->getUnitObjInternal();
			if ($unitObj === CSSUnitEnum::PERCENT) {
				return $clone ? $value->clone() : $value;
			}
			if ($unitObj === CSSUnitEnum::NUMBER) {
				$scaled = $value->value * $numberScale;
				if (!is_finite($scaled)) {
					throw new InvalidArgumentException('Non-finite value produced for color channel.');
				}
				return new CSSUnitValue($scaled, CSSUnitEnum::PERCENT);
			}
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}

		$category = $value->getNumericCategory();
		if ($value->getNumericTypeInternal()->percentHint !== null || ($category !== 'number' && $category !== 'percent')) {
			throw new InvalidArgumentException('Color channel math expression must be a number or percentage.');
		}
		$target = $clone ? $value->clone() : $value;
		if ($category === 'number' && $numberScale !== 1.0) {
			return $target->mul(new CSSUnitValue($numberScale, CSSUnitEnum::PERCENT));
		}

		return $target;
	}

	/** @internal */
	public static function normalizeAngleChannel(
		CSSNumericValue|CSSKeywordValue|int|float $value,
		bool $clone = true,
	): CSSNumericValue|CSSKeywordValue {
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			if (!is_finite((float) $value)) {
				throw new InvalidArgumentException('Hue angle must be finite.');
			}
			return new CSSUnitValue((float) $value, CSSUnitEnum::ANGLE_deg);
		}
		if ($value instanceof CSSUnitValue) {
			$unitObj = $value->getUnitObjInternal();
			if ($unitObj === CSSUnitEnum::NUMBER) {
				return new CSSUnitValue($value->value, CSSUnitEnum::ANGLE_deg);
			}
			if ($value->getNumericCategory() === 'angle') {
				return $clone ? $value->clone() : $value;
			}
			throw new InvalidArgumentException('Hue channel must be an angle, number, or "none".');
		}

		$category = $value->getNumericCategory();
		if ($value->getNumericTypeInternal()->percentHint !== null || ($category !== 'angle' && $category !== 'number')) {
			throw new InvalidArgumentException('Hue channel math expression must be an angle or number.');
		}

		return $clone ? $value->clone() : $value;
	}

	/** @internal */
	public static function normalizeNumberOrPercentChannel(
		CSSNumericValue|CSSKeywordValue|int|float $value,
		bool $clone = true,
	): CSSNumericValue|CSSKeywordValue {
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			if (!is_finite((float) $value)) {
				throw new InvalidArgumentException('Color channel value must be finite.');
			}
			return new CSSUnitValue((float) $value, CSSUnitEnum::NUMBER);
		}
		if ($value instanceof CSSUnitValue) {
			$unitObj = $value->getUnitObjInternal();
			if ($unitObj === CSSUnitEnum::NUMBER || $unitObj === CSSUnitEnum::PERCENT) {
				return $clone ? $value->clone() : $value;
			}
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}

		$category = $value->getNumericCategory();
		if ($value->getNumericTypeInternal()->percentHint !== null || ($category !== 'number' && $category !== 'percent')) {
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}

		return $clone ? $value->clone() : $value;
	}

	/** @internal */
	public static function resolvePercentUnit(CSSNumericValue|CSSKeywordValue $component): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return $component->getUnitObjInternal() === CSSUnitEnum::PERCENT ? $component->value / 100.0 : $component->value;
	}

	/** @internal */
	public static function resolveAngleDegrees(CSSNumericValue|CSSKeywordValue $component): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return match ($component->getUnitObjInternal()) {
			CSSUnitEnum::NUMBER, CSSUnitEnum::ANGLE_deg => $component->value,
			CSSUnitEnum::ANGLE_rad => $component->value * (180.0 / M_PI),
			CSSUnitEnum::ANGLE_grad => $component->value * 0.9,
			CSSUnitEnum::ANGLE_turn => $component->value * 360.0,
			default => $component->to('deg')->value,
		};
	}

	/** @internal */
	public static function resolveLinearChannel(CSSNumericValue|CSSKeywordValue $component, float $percentReference): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return $component->getUnitObjInternal() === CSSUnitEnum::PERCENT ? ($component->value / 100.0) * $percentReference : $component->value;
	}

	protected static function srgbToLinear(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs <= 0.04045) {
			return $value / 12.92;
		}

		return $sign * (($abs + 0.055) / 1.055) ** 2.4;
	}

	protected static function linearToSrgb(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs <= 0.0031308) {
			return 12.92 * $value;
		}

		return $sign * (1.055 * ($abs ** (1.0 / 2.4)) - 0.055);
	}

	protected static function a98ToLinear(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		return $sign * (abs($value) ** (563.0 / 256.0));
	}

	protected static function linearToA98(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		return $sign * (abs($value) ** (256.0 / 563.0));
	}

	protected static function prophotoToLinear(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs <= 16.0 / 512.0) {
			return $value / 16.0;
		}

		return $sign * ($abs ** 1.8);
	}

	protected static function linearToProphoto(float $value): float
	{
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs >= 1.0 / 512.0) {
			return $sign * ($abs ** (1.0 / 1.8));
		}

		return 16.0 * $value;
	}

	protected static function rec2020ToLinear(float $value): float
	{
		$alpha = 1.09929682680944;
		$beta = 0.018053968510807;
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs < $beta * 4.5) {
			return $value / 4.5;
		}

		return $sign * ((($abs + $alpha - 1.0) / $alpha) ** (1.0 / 0.45));
	}

	protected static function linearToRec2020(float $value): float
	{
		$alpha = 1.09929682680944;
		$beta = 0.018053968510807;
		$sign = $value < 0.0 ? -1.0 : 1.0;
		$abs = abs($value);
		if ($abs > $beta) {
			return $sign * ($alpha * ($abs ** 0.45) - ($alpha - 1.0));
		}

		return 4.5 * $value;
	}

	/** @return array{float, float, float} */
	protected static function linearSrgbToXyzD65(float $r, float $g, float $b): array
	{
		return [
			0.4123907992659595 * $r + 0.357584339383878 * $g + 0.1804807884018343 * $b,
			0.21263900587151036 * $r + 0.715168678767756 * $g + 0.07219231536073371 * $b,
			0.01933081871559185 * $r + 0.11919477979462599 * $g + 0.9505321522496607 * $b,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToLinearSrgb(float $x, float $y, float $z): array
	{
		return [
			3.2409699419045213 * $x - 1.5373831775700935 * $y - 0.4986107602930033 * $z,
			-0.9692436362808798 * $x + 1.8759675015077206 * $y + 0.04155505740717561 * $z,
			0.05563007969699361 * $x - 0.20397695888897657 * $y + 1.0569715142428786 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function linearDisplayP3ToXyzD65(float $r, float $g, float $b): array
	{
		return [
			0.4865709486482162 * $r + 0.26566769316909306 * $g + 0.1982172852343625 * $b,
			0.2289745640697488 * $r + 0.6917385218365064 * $g + 0.079286914093745 * $b,
			0.0 * $r + 0.04511338185890264 * $g + 1.043944368900976 * $b,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToLinearDisplayP3(float $x, float $y, float $z): array
	{
		return [
			2.493496911941425 * $x - 0.9313836179191239 * $y - 0.40271078445071684 * $z,
			-0.8294889695615747 * $x + 1.7626640603183463 * $y + 0.023624685841943577 * $z,
			0.03584583024378447 * $x - 0.07617238926804182 * $y + 0.9568845240076872 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function linearA98ToXyzD65(float $r, float $g, float $b): array
	{
		return [
			0.5766690429101305 * $r + 0.1855582379065463 * $g + 0.1882286462349947 * $b,
			0.29734497525053605 * $r + 0.6273635662554661 * $g + 0.07529145849399788 * $b,
			0.02703136138641234 * $r + 0.07068885253582723 * $g + 0.9913375368376388 * $b,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToLinearA98(float $x, float $y, float $z): array
	{
		return [
			2.0415879038107465 * $x - 0.5650069742788596 * $y - 0.34473135077832956 * $z,
			-0.9692436362808795 * $x + 1.8759675015077202 * $y + 0.04155505740717557 * $z,
			0.013444280632031142 * $x - 0.11836239223101838 * $y + 1.0151749943912054 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function linearProphotoToXyzD50(float $r, float $g, float $b): array
	{
		return [
			0.7977604896723027 * $r + 0.13518583717574031 * $g + 0.0313493495815248 * $b,
			0.2880711282292934 * $r + 0.7118432178101014 * $g + 0.00008565396060525902 * $b,
			0.0 * $r + 0.0 * $g + 0.8251046025104601 * $b,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD50ToLinearProphoto(float $x, float $y, float $z): array
	{
		return [
			1.3457989731028281 * $x - 0.25558010007997534 * $y - 0.05110628506753401 * $z,
			-0.5446224939028347 * $x + 1.5082327413132781 * $y + 0.02053603239147973 * $z,
			0.0 * $x + 0.0 * $y + 1.2119675456389454 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function linearRec2020ToXyzD65(float $r, float $g, float $b): array
	{
		return [
			0.6369580483012914 * $r + 0.14461690358620832 * $g + 0.1688809751641721 * $b,
			0.2627002120112671 * $r + 0.6779980715188708 * $g + 0.05930171646986196 * $b,
			0.0 * $r + 0.028072693049087428 * $g + 1.060985057710791 * $b,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToLinearRec2020(float $x, float $y, float $z): array
	{
		return [
			1.7166511879712674 * $x - 0.35567078377639233 * $y - 0.25336628137365974 * $z,
			-0.6666843518324892 * $x + 1.6164812366349395 * $y + 0.01576854581391113 * $z,
			0.017639857445310783 * $x - 0.042770613257808524 * $y + 0.9421031212354738 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToD50(float $x, float $y, float $z): array
	{
		return [
			1.0479297925449969 * $x + 0.022946870601609708 * $y - 0.05019226628920527 * $z,
			0.029627808770055993 * $x + 0.99043442675388005 * $y - 0.017073799063418826 * $z,
			-0.0092430406462045145 * $x + 0.015055191490298145 * $y + 0.75187428142813717 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD50ToD65(float $x, float $y, float $z): array
	{
		return [
			0.95547342148807513 * $x - 0.023098454948764682 * $y + 0.063259243200570692 * $z,
			-0.028369709333863756 * $x + 1.0099953980813043 * $y + 0.021041441191917323 * $z,
			0.012314014864481991 * $x - 0.020507649298898964 * $y + 1.3303659262421241 * $z,
		];
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public static function hslToSrgbFloats(float $h, float $s, float $l): array
	{
		$h = fmod(fmod($h, 360.0) + 360.0, 360.0);
		$hOver30 = $h / 30.0;
		$a = $s * min($l, 1.0 - $l);

		$k0 = fmod($hOver30, 12.0);
		$k8 = fmod(8.0 + $hOver30, 12.0);
		$k4 = fmod(4.0 + $hOver30, 12.0);

		return [
			$l - $a * max(-1.0, min($k0 - 3.0, 9.0 - $k0, 1.0)),
			$l - $a * max(-1.0, min($k8 - 3.0, 9.0 - $k8, 1.0)),
			$l - $a * max(-1.0, min($k4 - 3.0, 9.0 - $k4, 1.0)),
		];
	}

	private static function rgbToHue(float $r, float $g, float $b, float $max, float $delta): float
	{
		if ($max === $r) {
			$h = 60.0 * fmod(($g - $b) / $delta, 6.0);
		} elseif ($max === $g) {
			$h = 60.0 * ((($b - $r) / $delta) + 2.0);
		} else {
			$h = 60.0 * ((($r - $g) / $delta) + 4.0);
		}

		return $h < 0.0 ? $h + 360.0 : $h;
	}

	/** @return array{float, float, float} */
	protected static function xyzD50ToLab(float $x, float $y, float $z): array
	{
		$fx = self::labF($x / self::D50_X);
		$fy = self::labF($y);
		$fz = self::labF($z / self::D50_Z);

		return [
			116.0 * $fy - 16.0,
			500.0 * ($fx - $fy),
			200.0 * ($fy - $fz),
		];
	}

	/** @return array{float, float, float} */
	protected static function labToXyzD50(float $l, float $a, float $b): array
	{
		$fy = ($l + 16.0) / 116.0;
		$fx = $a / 500.0 + $fy;
		$fz = $fy - $b / 200.0;

		$fx3 = $fx * $fx * $fx;
		$fz3 = $fz * $fz * $fz;
		$xr = $fx3 > self::LAB_EPSILON ? $fx3 : (116.0 * $fx - 16.0) / self::LAB_KAPPA;
		$yr = $l > (self::LAB_KAPPA * self::LAB_EPSILON) ? $fy * $fy * $fy : $l / self::LAB_KAPPA;
		$zr = $fz3 > self::LAB_EPSILON ? $fz3 : (116.0 * $fz - 16.0) / self::LAB_KAPPA;

		return [$xr * self::D50_X, $yr, $zr * self::D50_Z];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToOklab(float $x, float $y, float $z): array
	{
		$l = 0.8190224379967030 * $x + 0.3619062600528904 * $y - 0.1288737815209879 * $z;
		$m = 0.0329836539323885 * $x + 0.9292868615863434 * $y + 0.0361446663506424 * $z;
		$s = 0.0481771893596242 * $x + 0.2642395317527308 * $y + 0.6335478284694309 * $z;

		$l_ = self::cbrtFloat($l);
		$m_ = self::cbrtFloat($m);
		$s_ = self::cbrtFloat($s);

		return [
			0.2104542683093140 * $l_ + 0.7936177747023054 * $m_ - 0.0040720430116193 * $s_,
			1.9779985324311684 * $l_ - 2.4285922420485799 * $m_ + 0.4505937096174110 * $s_,
			0.0259040424655478 * $l_ + 0.7827717124575296 * $m_ - 0.8086757549230774 * $s_,
		];
	}

	private static function labF(float $v): float
	{
		return $v > self::LAB_EPSILON ? ($v ** self::THIRD) : (self::LAB_KAPPA * $v + 16.0) / 116.0;
	}

	private static function cbrtFloat(float $v): float
	{
		return $v < 0.0 ? -((-$v) ** self::THIRD) : ($v ** self::THIRD);
	}

	/** @return array{float, float, float} */
	protected static function oklabToXyzD65(float $l, float $a, float $b): array
	{
		$l_ = $l + 0.3963377773761749 * $a + 0.2158037573099136 * $b;
		$m_ = $l - 0.1055613458156586 * $a - 0.0638541728258133 * $b;
		$s_ = $l - 0.0894841775298119 * $a - 1.2914855480194092 * $b;

		$lc = $l_ * $l_ * $l_;
		$mc = $m_ * $m_ * $m_;
		$sc = $s_ * $s_ * $s_;

		return [
			1.2268798758459243 * $lc - 0.5578149944602171 * $mc + 0.2813910456659647 * $sc,
			-0.0405757452148008 * $lc + 1.1122868032803170 * $mc - 0.0717110580655164 * $sc,
			-0.0763729366746601 * $lc - 0.4214933324022432 * $mc + 1.5869240198367816 * $sc,
		];
	}

	private static function decodeKeywordColorIdentInternal(string $source): ?string
	{
		$clean = str_contains($source, '/*')
			? CSSSyntaxScanner::trimCssWhitespace(self::stripTopLevelComments($source))
			: $source;
		if ($clean === '') {
			return null;
		}
		if (str_contains($clean, '\\')) {
			$decoded = CSSSyntaxScanner::decodeIdentAt($clean, 0, strlen($clean));
			if ($decoded === null || $decoded['end'] !== strlen($clean)) {
				return null;
			}
			$clean = $decoded['ident'];
		}

		return strtolower($clean);
	}

	/** @internal */
	public static function tryParseCurrentColorInternal(string $source, ?CSSContext $context = null): CSSColorValue|CSSKeywordValue|null
	{
		if (self::decodeKeywordColorIdentInternal($source) !== 'currentcolor') {
			return null;
		}
		$resolved = $context?->getCurrentColor();

		return $resolved !== null ? $resolved : CSSKeywordValue::fromValidated('currentcolor');
	}

	/** @internal Zero-allocation check for named CSS color / `transparent`. */
	public static function isNamedColorIdentInternal(string $lowerIdent): bool
	{
		return $lowerIdent === 'transparent' || isset(self::NAMED_COLORS[$lowerIdent]);
	}

	/** @internal Zero-allocation check for 3/4/6/8-digit CSS hex digits (without leading `#`). */
	public static function isValidHexInternal(string $digits): bool
	{
		$len = strlen($digits);

		return ($len === 3 || $len === 4 || $len === 6 || $len === 8) && ctype_xdigit($digits);
	}

	/** @internal */
	public static function tryParseNamedColorIdentInternal(string $lowerIdent): ?CSSRGB
	{
		if ($lowerIdent === 'transparent') {
			return CSSRGB::fromValidated(
				new CSSUnitValue(0.0, CSSUnitEnum::PERCENT),
				new CSSUnitValue(0.0, CSSUnitEnum::PERCENT),
				new CSSUnitValue(0.0, CSSUnitEnum::PERCENT),
				new CSSUnitValue(0.0, CSSUnitEnum::PERCENT),
			);
		}
		$rgb = self::NAMED_COLORS[$lowerIdent] ?? null;
		if ($rgb === null) {
			return null;
		}
		$scale = 100.0 / 255.0;

		return CSSRGB::fromValidated(
			new CSSUnitValue((($rgb >> 16) & 0xFF) * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue((($rgb >> 8) & 0xFF) * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue(($rgb & 0xFF) * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue(100.0, CSSUnitEnum::PERCENT),
		);
	}

	/** @internal */
	public static function parseHexInternal(string $hex): CSSRGB
	{
		return self::parseHex($hex);
	}

	public static function tryParseNamedColor(string $source): ?CSSRGB
	{
		$lower = self::decodeKeywordColorIdentInternal($source);

		return $lower !== null ? self::tryParseNamedColorIdentInternal($lower) : null;
	}

	private static function parseHex(string $hex): CSSRGB
	{
		$digits = substr($hex, 1);
		$len = strlen($digits);
		if (($len !== 3 && $len !== 4 && $len !== 6 && $len !== 8) || !ctype_xdigit($digits)) {
			throw new InvalidArgumentException('Invalid CSS hex color: ' . $hex);
		}

		$val = (int) hexdec($digits);
		if ($len === 3) {
			$r = (($val >> 8) & 0xF) * 17;
			$g = (($val >> 4) & 0xF) * 17;
			$b = ($val & 0xF) * 17;
			$a = 255;
		} elseif ($len === 4) {
			$r = (($val >> 12) & 0xF) * 17;
			$g = (($val >> 8) & 0xF) * 17;
			$b = (($val >> 4) & 0xF) * 17;
			$a = ($val & 0xF) * 17;
		} elseif ($len === 6) {
			$r = ($val >> 16) & 0xFF;
			$g = ($val >> 8) & 0xFF;
			$b = $val & 0xFF;
			$a = 255;
		} else {
			$r = ($val >> 24) & 0xFF;
			$g = ($val >> 16) & 0xFF;
			$b = ($val >> 8) & 0xFF;
			$a = $val & 0xFF;
		}
		$scale = 100.0 / 255.0;

		return CSSRGB::fromValidated(
			new CSSUnitValue($r * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue($g * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue($b * $scale, CSSUnitEnum::PERCENT),
			new CSSUnitValue($a * $scale, CSSUnitEnum::PERCENT),
		);
	}

	/**
	 * Strips top-level CSS comments outside nested `(...)`/`[...]` so inner invalid comments remain intact for downstream parsers.
	 */
	private static function stripTopLevelComments(string $body): string
	{
		if (!str_contains($body, '/*')) {
			return $body;
		}

		$len = strlen($body);
		$depth = 0;
		$quote = '';
		$out = '';
		$chunkStart = 0;
		$i = 0;
		$lastEscapeEnd = -1;
		$lastEscapeWasBareHex = false;

		while ($i < $len) {
			$ch = $body[$i];
			if ($quote !== '') {
				if ($ch === '\\') {
					$i += 2;
					continue;
				}
				if ($ch === $quote) {
					$quote = '';
				}
				$i++;
				continue;
			}

			if ($ch === '\\') {
				if ($i + 1 >= $len) {
					$lastEscapeEnd = $len;
					$lastEscapeWasBareHex = false;
					$i = $len;
				} else {
					$hexLen = strspn($body, '0123456789abcdefABCDEF', $i + 1, min(6, $len - ($i + 1)));
					if ($hexLen > 0) {
						$endEsc = $i + 1 + $hexLen;
						$consumedWs = false;
						if ($endEsc < $len) {
							$ws = $body[$endEsc];
							if ($ws === "\r" && $endEsc + 1 < $len && $body[$endEsc + 1] === "\n") {
								$endEsc += 2;
								$consumedWs = true;
							} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
								$endEsc++;
								$consumedWs = true;
							}
						}
						$lastEscapeEnd = $endEsc;
						$lastEscapeWasBareHex = !$consumedWs;
						$i = $endEsc;
					} else {
						$escapedByte = $body[$i + 1];
						if ($escapedByte === "\n" || $escapedByte === "\r" || $escapedByte === "\f") {
							$lastEscapeWasBareHex = false;
							$i++;
						} else {
							$lastEscapeEnd = $i + 2;
							$lastEscapeWasBareHex = false;
							$i = $lastEscapeEnd;
						}
					}
				}
				continue;
			}

			if ($ch === '"' || $ch === "'") {
				$quote = $ch;
				$i++;
				continue;
			}
			if ($ch === '(' || $ch === '[') {
				$depth++;
				$i++;
				continue;
			}
			if ($ch === ')' || $ch === ']') {
				if ($depth > 0) {
					$depth--;
				}
				$i++;
				continue;
			}
			if ($ch === '/' && $i + 1 < $len && $body[$i + 1] === '*') {
				$prevWasBareHex = $lastEscapeEnd === $i && $lastEscapeWasBareHex;
				$end = strpos($body, '*/', $i + 2);
				$commentEnd = $end === false ? $len : $end + 2;
				if ($depth === 0) {
					$sep = $prevWasBareHex ? '  ' : ' ';
					$out .= substr($body, $chunkStart, $i - $chunkStart) . $sep;
					$chunkStart = $commentEnd;
				}
				$i = $commentEnd;
				continue;
			}
			$i++;
		}

		if ($chunkStart === 0) {
			return $body;
		}

		return $out . substr($body, $chunkStart);
	}

	/**
	 * Splits a color function body into [channels, alphaToken, isLegacyComma].
	 *
	 * @return array{list<string>, ?string, bool}
	 */
	private static function splitColorArguments(string $body, bool $allowLegacyComma): array
	{
		if (str_contains($body, '/*')) {
			return self::splitColorArgumentsSlow($body, $allowLegacyComma);
		}

		$len = strlen($body);
		$maxParts = CSSColor::MAX_CUSTOM_CHANNELS + 4;
		$channels = [];
		$alpha = null;
		$sawSlash = false;
		$offset = 0;
		$depth = 0;
		$tokenStart = -1;
		$mask = " \t\n\r\f,()/\\'\"[{";

		while ($offset < $len) {
			$span = strcspn($body, $mask, $offset, $len - $offset);
			if ($tokenStart < 0 && $span > 0) {
				$tokenStart = $offset;
			}
			$offset += $span;
			if ($offset >= $len) {
				break;
			}
			$ch = $body[$offset];
			if ($ch === '\\' || $ch === '\'' || $ch === '"' || $ch === '[' || $ch === '{') {
				return self::splitColorArgumentsSlow($body, $allowLegacyComma);
			}
			if ($ch === ',') {
				if ($depth === 0) {
					return self::splitColorArgumentsSlow($body, $allowLegacyComma);
				}
				$offset++;
				continue;
			}
			if ($ch === '(') {
				if ($tokenStart < 0) {
					$tokenStart = $offset;
				}
				$depth++;
				$offset++;
				continue;
			}
			if ($ch === ')') {
				if ($depth > 0) {
					$depth--;
				}
				$offset++;
				continue;
			}
			if ($depth > 0) {
				$offset++;
				continue;
			}
			if ($tokenStart >= 0) {
				$token = substr($body, $tokenStart, $offset - $tokenStart);
				$tokenStart = -1;
				if ($sawSlash) {
					if ($alpha !== null) {
						throw new InvalidArgumentException('Invalid alpha component in CSS color function.');
					}
					$alpha = $token;
				} else {
					if (count($channels) >= $maxParts) {
						throw new CSSParserLimitException(sprintf('CSS list exceeds maximum limit of %d items.', $maxParts));
					}
					$channels[] = $token;
				}
			}
			if ($ch === '/') {
				if ($sawSlash) {
					throw new InvalidArgumentException('Too many "/" separators in CSS color function.');
				}
				$sawSlash = true;
				$offset++;
				continue;
			}
			$offset += strspn($body, CSSSyntaxScanner::CSS_WHITESPACE, $offset, $len - $offset);
		}

		if ($tokenStart >= 0) {
			$token = substr($body, $tokenStart, $len - $tokenStart);
			if ($sawSlash) {
				if ($alpha !== null) {
					throw new InvalidArgumentException('Invalid alpha component in CSS color function.');
				}
				$alpha = $token;
			} else {
				if (count($channels) >= $maxParts) {
					throw new CSSParserLimitException(sprintf('CSS list exceeds maximum limit of %d items.', $maxParts));
				}
				$channels[] = $token;
			}
		}

		if ($sawSlash && $alpha === null) {
			throw new InvalidArgumentException('Invalid alpha component in CSS color function.');
		}

		return [$channels, $alpha, false];
	}

	/**
	 * @return array{list<string>, ?string, bool}
	 */
	private static function splitColorArgumentsSlow(string $body, bool $allowLegacyComma): array
	{
		$maxParts = CSSColor::MAX_CUSTOM_CHANNELS + 4;
		$commaParts = str_contains($body, ',') ? CSSSyntaxScanner::splitTopLevel($body, ',', 0, null, $maxParts) : [$body];

		if (count($commaParts) > 1) {
			if (!$allowLegacyComma || (str_contains($body, '/') && count(CSSSyntaxScanner::splitTopLevel($body, '/', 0, null, $maxParts)) > 1)) {
				throw new InvalidArgumentException('Invalid comma syntax in CSS color function.');
			}
			$trimmed = [];
			foreach ($commaParts as $part) {
				$clean = CSSSyntaxScanner::trimCssWhitespace($part);
				if ($clean === '') {
					throw new InvalidArgumentException('Empty component in CSS color function.');
				}
				$trimmed[] = $clean;
			}
			if (count($trimmed) === 3) {
				return [$trimmed, null, true];
			}
			if (count($trimmed) === 4) {
				return [[$trimmed[0], $trimmed[1], $trimmed[2]], $trimmed[3], true];
			}
			throw new InvalidArgumentException('Invalid argument count in legacy CSS color function.');
		}

		$slashParts = str_contains($body, '/') ? CSSSyntaxScanner::splitTopLevel($body, '/', 0, null, $maxParts) : [$body];
		if (count($slashParts) > 2) {
			throw new InvalidArgumentException('Too many "/" separators in CSS color function.');
		}

		$channels = CSSSyntaxScanner::splitTopLevelWhitespace(CSSSyntaxScanner::trimCssWhitespace($slashParts[0]), 0, null, $maxParts);
		$alpha = null;
		if (count($slashParts) === 2) {
			$alphaTokens = CSSSyntaxScanner::splitTopLevelWhitespace(CSSSyntaxScanner::trimCssWhitespace($slashParts[1]), 0, null, $maxParts);
			if (count($alphaTokens) !== 1) {
				throw new InvalidArgumentException('Invalid alpha component in CSS color function.');
			}
			$alpha = $alphaTokens[0];
		}

		return [$channels, $alpha, false];
	}

	private static function parseComponentToken(
		string $token,
		bool $allowNone,
		CSSParserOptions $options,
		int &$mathBudget = 0,
	): CSSNumericValue|CSSKeywordValue {
		$token = CSSSyntaxScanner::trimCssWhitespace($token);
		if ($token === '') {
			throw new InvalidArgumentException('Empty color component.');
		}
		$decodedNone = false;
		if (strcasecmp($token, 'none') === 0) {
			$decodedNone = true;
		} elseif (str_contains($token, '\\')) {
			$ident = CSSSyntaxScanner::decodeIdentAt($token, 0, strlen($token));
			$decodedNone = $ident !== null && $ident['end'] === strlen($token) && strcasecmp($ident['ident'], 'none') === 0;
		}
		if ($decodedNone) {
			if (!$allowNone) {
				throw new InvalidArgumentException('"none" keyword is not allowed in legacy comma-separated color syntax.');
			}
			return CSSKeywordValue::fromValidated('none');
		}

		$c0 = $token[0];
		if ($c0 === '+' || $c0 === '-' || $c0 === '.' || ($c0 >= '0' && $c0 <= '9')) {
			$tLen = strlen($token);
			$hasPct = $token[$tLen - 1] === '%';
			$numLen = $hasPct ? $tLen - 1 : $tLen;
			$numStr = $hasPct ? substr($token, 0, $numLen) : $token;
			if ($numLen > 0 && strspn($numStr, '0123456789.+-eE') === $numLen && is_numeric($numStr)) {
				$dotPos = strpos($numStr, '.');
				if ($dotPos === false || ($dotPos + 1 < $numLen && $numStr[$dotPos + 1] >= '0' && $numStr[$dotPos + 1] <= '9')) {
					$numVal = (float) $numStr;
					if (is_finite($numVal)) {
						return new CSSUnitValue($numVal, $hasPct ? CSSUnitEnum::PERCENT : CSSUnitEnum::NUMBER);
					}
				}
			}
		}

		$numeric = CSSNumericValue::tryParseValidated($token, $options, 0, $mathBudget);
		if ($numeric === null) {
			throw new InvalidArgumentException('Invalid CSS numeric value: ' . $token);
		}

		return $numeric;
	}

	private static function parseRgbFunction(string $body, CSSParserOptions $options, int &$mathBudget = 0): CSSRGB
	{
		[$channels, $alphaToken, $legacy] = self::splitColorArguments($body, true);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('rgb() requires 3 color channels.');
		}

		$parsed = [];
		$seenPercent = false;
		$seenNumber = false;
		foreach ($channels as $token) {
			$component = self::parseComponentToken($token, !$legacy, $options, $mathBudget);
			if ($component instanceof CSSUnitValue) {
				$unitObj = $component->getUnitObjInternal();
				if ($unitObj === CSSUnitEnum::PERCENT) {
					$seenPercent = true;
					$parsed[] = $component;
				} elseif ($unitObj === CSSUnitEnum::NUMBER) {
					$seenNumber = true;
					$parsed[] = new CSSUnitValue(($component->value / 255.0) * 100.0, CSSUnitEnum::PERCENT);
				} else {
					throw new InvalidArgumentException('rgb() channels must be numbers, percentages, or "none".');
				}
			} elseif ($component instanceof CSSKeywordValue) {
				$parsed[] = $component;
			} else {
				$category = $component->getNumericCategory();
				if ($component->getNumericTypeInternal()->percentHint !== null || ($category !== 'number' && $category !== 'percent')) {
					throw new InvalidArgumentException('rgb() math channel must be a number or percentage.');
				}
				if ($category === 'percent') {
					$seenPercent = true;
					$parsed[] = $component;
				} else {
					$seenNumber = true;
					$parsed[] = $component->mul(new CSSUnitValue(100.0 / 255.0, CSSUnitEnum::PERCENT));
				}
			}
		}

		if ($legacy && $seenPercent && $seenNumber) {
			throw new InvalidArgumentException('Legacy comma-separated rgb() cannot mix numbers and percentages.');
		}

		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, CSSUnitEnum::PERCENT)
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, !$legacy, $options, $mathBudget), 100.0, false);

		return CSSRGB::fromValidated($parsed[0], $parsed[1], $parsed[2], $alpha);
	}

	private static function parseHslFunction(string $body, CSSParserOptions $options, int &$mathBudget = 0): CSSHSL
	{
		[$channels, $alphaToken, $legacy] = self::splitColorArguments($body, true);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('hsl() requires 3 color channels.');
		}

		$h = self::normalizeAngleChannel(self::parseComponentToken($channels[0], !$legacy, $options, $mathBudget), false);
		$sRaw = self::parseComponentToken($channels[1], !$legacy, $options, $mathBudget);
		$lRaw = self::parseComponentToken($channels[2], !$legacy, $options, $mathBudget);
		if ($legacy) {
			if (!($sRaw instanceof CSSNumericValue && $sRaw->getNumericCategory() === 'percent')
				|| !($lRaw instanceof CSSNumericValue && $lRaw->getNumericCategory() === 'percent')) {
				throw new InvalidArgumentException('Legacy comma-separated hsl() requires percentage saturation and lightness.');
			}
		}
		$s = self::normalizePercentChannel($sRaw, 1.0, false);
		$l = self::normalizePercentChannel($lRaw, 1.0, false);
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, CSSUnitEnum::PERCENT)
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, !$legacy, $options, $mathBudget), 100.0, false);

		return CSSHSL::fromValidated($h, $s, $l, $alpha);
	}

	private static function normalizeStandardLeafChannel(
		string $kind,
		string $token,
		CSSParserOptions $options,
		int &$mathBudget,
	): CSSNumericValue|CSSKeywordValue {
		$comp = self::parseComponentToken($token, true, $options, $mathBudget);

		return match ($kind) {
			'angle' => self::normalizeAngleChannel($comp, false),
			'percent1' => self::normalizePercentChannel($comp, 1.0, false),
			'percent100' => self::normalizePercentChannel($comp, 100.0, false),
			default => self::normalizeNumberOrPercentChannel($comp, false),
		};
	}

	private static function parseStandardThreeChannelFunction(
		string $function,
		string $body,
		CSSParserOptions $options,
		int &$mathBudget = 0,
	): CSSColorValue {
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException($function . '() requires 3 color channels.');
		}

		$spec = CSSColorSpaceRegistry::LEAF_THREE_CHANNEL_SPEC[$function];
		$c0 = self::normalizeStandardLeafChannel($spec['ch0'], $channels[0], $options, $mathBudget);
		$c1 = self::normalizeStandardLeafChannel($spec['ch1'], $channels[1], $options, $mathBudget);
		$c2 = self::normalizeStandardLeafChannel($spec['ch2'], $channels[2], $options, $mathBudget);
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, CSSUnitEnum::PERCENT)
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options, $mathBudget), 100.0, false);

		/** @var class-string<CSSHWB|CSSLab|CSSLCH|CSSOKLab|CSSOKLCH> $class */
		$class = $spec['class'];

		return $class::fromValidated($c0, $c1, $c2, $alpha);
	}

	private static function parseColorFunction(string $body, CSSParserOptions $options, int &$mathBudget = 0): CSSColor
	{
		[$tokens, $alphaToken] = self::splitColorArguments($body, false);
		if (count($tokens) < 2) {
			throw new InvalidArgumentException('color() requires a color space and at least one channel.');
		}

		[$normalizedSpace, $isPredefined] = CSSColor::normalizeColorSpaceInternal(array_shift($tokens));
		if ($isPredefined && count($tokens) !== 3) {
			throw new InvalidArgumentException(sprintf('Predefined color space "%s" requires exactly 3 channels.', $normalizedSpace));
		}
		$space = CSSKeywordValue::fromValidated($normalizedSpace);
		if (count($tokens) > CSSColor::MAX_CUSTOM_CHANNELS) {
			throw new CSSParserLimitException(sprintf('CSS color() channel count exceeds maximum limit of %d.', CSSColor::MAX_CUSTOM_CHANNELS));
		}
		$channels = [];
		foreach ($tokens as $token) {
			$channels[] = self::normalizeNumberOrPercentChannel(self::parseComponentToken($token, true, $options, $mathBudget), false);
		}
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, CSSUnitEnum::PERCENT)
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options, $mathBudget), 100.0, false);

		return CSSColor::fromValidated($space, $channels, $alpha);
	}
}
