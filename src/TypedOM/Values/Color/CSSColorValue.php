<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Abstract base class for CSS Typed OM color values (CSS Color 4 / Color 5 Typed OM).
 *
 * @see https://drafts.css-houdini.org/css-typed-om-1/#colorvalue-objects
 */
abstract class CSSColorValue extends CSSStyleValue
{
	protected CSSNumericValue|CSSKeywordValue $alpha;

	/**
	 * Parses a CSS color value (`#hex`, `rgb()`, `hsl()`, `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `color()`).
	 *
	 * Accepts either a single CSS color string (`CSSColorValue::parse('#ff0000')`) or
	 * a `(property, cssText)` pair matching `CSSStyleValue::parse()`.
	 *
	 * @throws InvalidArgumentException When the color syntax is invalid, relative, uses `var()`, or exceeds limits
	 */
	public static function parse(
		string $propertyOrCssText,
		?string $cssText = null,
		?CSSParserOptions $options = null,
	): self {
		$options ??= new CSSParserOptions();
		$source = trim($cssText ?? $propertyOrCssText);
		if ($source === '') {
			throw new InvalidArgumentException('CSS color value cannot be empty.');
		}
		if ($options->maxBytes !== null && strlen($source) > $options->maxBytes) {
			throw new InvalidArgumentException(sprintf('CSS color value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		CSSSyntaxScanner::assertValidFragment($source, 'CSS color', [';', '{', '}']);

		if ($source[0] === '#') {
			return self::parseHex($source);
		}

		if (!preg_match('/^([a-zA-Z][a-zA-Z0-9-]*)\s*\(/', $source, $match)) {
			throw new InvalidArgumentException('Unsupported CSS color value: ' . $source);
		}
		$function = strtolower($match[1]);
		$open = strpos($source, '(');
		$close = $open === false ? null : CSSSyntaxScanner::findMatchingDelimiter($source, $open);
		if ($close !== strlen($source) - 1) {
			throw new InvalidArgumentException('Invalid CSS color function syntax: ' . $source);
		}

		$body = trim(substr($source, $open + 1, $close - $open - 1));
		if ($body === '' || preg_match('/^from\s+/i', $body) === 1) {
			throw new InvalidArgumentException('Relative or empty CSS color syntax is not supported as a typed color.');
		}

		return match ($function) {
			'rgb', 'rgba' => self::parseRgbFunction($body, $options),
			'hsl', 'hsla' => self::parseHslFunction($body, $options),
			'hwb' => self::parseHwbFunction($body, $options),
			'lab' => self::parseLabFunction($body, $options),
			'lch' => self::parseLchFunction($body, $options),
			'oklab' => self::parseOklabFunction($body, $options),
			'oklch' => self::parseOklchFunction($body, $options),
			'color' => self::parseColorFunction($body, $options),
			default => throw new InvalidArgumentException('Unsupported CSS color function: ' . $function),
		};
	}

	public function getAlpha(): CSSNumericValue|CSSKeywordValue
	{
		return $this->alpha;
	}

	public function setAlpha(CSSNumericValue|CSSKeywordValue|int|float $alpha): void
	{
		$this->alpha = self::normalizePercentChannel($alpha, 100.0);
	}

	public function toRGB(): CSSRGB
	{
		[$r, $g, $b, $alpha] = $this->toSrgbChannels();
		return new CSSRGB(
			new CSSUnitValue($r * 100.0, 'percent'),
			new CSSUnitValue($g * 100.0, 'percent'),
			new CSSUnitValue($b * 100.0, 'percent'),
			$alpha,
		);
	}

	public function toHSL(): CSSHSL
	{
		[$r, $g, $b, $alpha] = $this->toSrgbChannels();
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$delta = $max - $min;
		$l = ($max + $min) / 2.0;

		if ($delta === 0.0) {
			$h = 0.0;
			$s = 0.0;
		} else {
			$s = ($l === 0.0 || $l === 1.0) ? 0.0 : ($max - $l) / min($l, 1.0 - $l);
			if ($max === $r) {
				$h = 60.0 * fmod(($g - $b) / $delta, 6.0);
			} elseif ($max === $g) {
				$h = 60.0 * ((($b - $r) / $delta) + 2.0);
			} else {
				$h = 60.0 * ((($r - $g) / $delta) + 4.0);
			}
			if ($h < 0.0) {
				$h += 360.0;
			}
		}

		return new CSSHSL(
			new CSSUnitValue($h, 'deg'),
			new CSSUnitValue($s * 100.0, 'percent'),
			new CSSUnitValue($l * 100.0, 'percent'),
			$alpha,
		);
	}

	public function toHWB(): CSSHWB
	{
		[$r, $g, $b, $alpha] = $this->toSrgbChannels();
		$max = max($r, $g, $b);
		$min = min($r, $g, $b);
		$delta = $max - $min;
		$w = $min;
		$bk = 1.0 - $max;

		if ($delta === 0.0) {
			$h = 0.0;
		} else {
			if ($max === $r) {
				$h = 60.0 * fmod(($g - $b) / $delta, 6.0);
			} elseif ($max === $g) {
				$h = 60.0 * ((($b - $r) / $delta) + 2.0);
			} else {
				$h = 60.0 * ((($r - $g) / $delta) + 4.0);
			}
			if ($h < 0.0) {
				$h += 360.0;
			}
		}

		return new CSSHWB(
			new CSSUnitValue($h, 'deg'),
			new CSSUnitValue($w * 100.0, 'percent'),
			new CSSUnitValue($bk * 100.0, 'percent'),
			$alpha,
		);
	}

	public function toLab(): CSSLab
	{
		[$x, $y, $z, $alpha] = $this->toXyzD65();
		[$x50, $y50, $z50] = self::xyzD65ToD50($x, $y, $z);
		[$l, $a, $b] = self::xyzD50ToLab($x50, $y50, $z50);

		return new CSSLab(
			new CSSUnitValue($l, 'percent'),
			new CSSUnitValue($a, 'number'),
			new CSSUnitValue($b, 'number'),
			$alpha,
		);
	}

	public function toLCH(): CSSLCH
	{
		$lab = $this->toLab();
		$a = self::resolveLinearChannel($lab->getA(), 125.0);
		$b = self::resolveLinearChannel($lab->getB(), 125.0);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		return new CSSLCH(
			$lab->getL()->clone(),
			new CSSUnitValue($c, 'number'),
			new CSSUnitValue($h, 'deg'),
			$lab->getAlpha()->clone(),
		);
	}

	public function toOKLab(): CSSOKLab
	{
		[$x, $y, $z, $alpha] = $this->toXyzD65();
		[$l, $a, $b] = self::xyzD65ToOklab($x, $y, $z);

		return new CSSOKLab(
			new CSSUnitValue($l * 100.0, 'percent'),
			new CSSUnitValue($a, 'number'),
			new CSSUnitValue($b, 'number'),
			$alpha,
		);
	}

	public function toOKLCH(): CSSOKLCH
	{
		$oklab = $this->toOKLab();
		$a = self::resolveLinearChannel($oklab->getA(), 0.4);
		$b = self::resolveLinearChannel($oklab->getB(), 0.4);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		return new CSSOKLCH(
			$oklab->getL()->clone(),
			new CSSUnitValue($c, 'number'),
			new CSSUnitValue($h, 'deg'),
			$oklab->getAlpha()->clone(),
		);
	}

	public function to(string $colorSpace): self
	{
		$space = strtolower(trim($colorSpace));
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

	public function __get(string $name): mixed
	{
		if ($name === 'alpha') {
			return $this->alpha;
		}

		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'alpha' && ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value))) {
			$this->setAlpha($value);
			return;
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	/**
	 * Converts this color to CIE XYZ (D65) plus cloned alpha component.
	 *
	 * @return array{float, float, float, CSSNumericValue|CSSKeywordValue}
	 */
	abstract protected function toXyzD65(): array;

	/**
	 * @return array{float, float, float, CSSNumericValue|CSSKeywordValue}
	 */
	protected function toSrgbChannels(): array
	{
		[$x, $y, $z, $alpha] = $this->toXyzD65();
		[$lr, $lg, $lb] = self::xyzD65ToLinearSrgb($x, $y, $z);

		return [
			self::linearToSrgb($lr),
			self::linearToSrgb($lg),
			self::linearToSrgb($lb),
			$alpha,
		];
	}

	protected function toPredefinedColor(string $space): CSSColor
	{
		[$x, $y, $z, $alpha] = $this->toXyzD65();
		$channels = match ($space) {
			'xyz', 'xyz-d65' => [$x, $y, $z],
			'xyz-d50' => self::xyzD65ToD50($x, $y, $z),
			'srgb-linear' => self::xyzD65ToLinearSrgb($x, $y, $z),
			'srgb' => array_map(self::linearToSrgb(...), self::xyzD65ToLinearSrgb($x, $y, $z)),
			'display-p3' => array_map(self::linearToSrgb(...), self::xyzD65ToLinearDisplayP3($x, $y, $z)),
			'a98-rgb' => array_map(self::linearToA98(...), self::xyzD65ToLinearA98($x, $y, $z)),
			'prophoto-rgb' => array_map(self::linearToProphoto(...), self::xyzD50ToLinearProphoto(...self::xyzD65ToD50($x, $y, $z))),
			'rec2020' => array_map(self::linearToRec2020(...), self::xyzD65ToLinearRec2020($x, $y, $z)),
			default => throw new InvalidArgumentException('Unsupported predefined color space: ' . $space),
		};

		return new CSSColor(
			$space,
			array_map(static fn (float $v): CSSUnitValue => new CSSUnitValue($v, 'number'), $channels),
			$alpha,
		);
	}

	protected function formatAlphaSuffix(): string
	{
		if ($this->alpha instanceof CSSUnitValue && $this->alpha->unit === 'percent' && abs($this->alpha->value - 100.0) < 1e-9) {
			return '';
		}

		return ' / ' . (string) $this->alpha;
	}

	protected static function normalizeNoneKeyword(CSSKeywordValue $keyword): CSSKeywordValue
	{
		if (strcasecmp($keyword->getValue(), 'none') !== 0) {
			throw new InvalidArgumentException('Only the "none" keyword is permitted as a missing color component.');
		}

		return new CSSKeywordValue('none');
	}

	/**
	 * Normalizes a percentage-based color channel (e.g. CSSRGB r/g/b or alpha).
	 * Bare numeric values are multiplied by $numberScale (e.g. 100.0 for 0..1 -> 0%..100%, or 1.0 for 0..100 -> 0%..100%).
	 */
	protected static function normalizePercentChannel(
		CSSNumericValue|CSSKeywordValue|int|float $value,
		float $numberScale = 100.0,
	): CSSNumericValue|CSSKeywordValue {
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			if (!is_finite((float) $value)) {
				throw new InvalidArgumentException('Color channel value must be finite.');
			}
			return new CSSUnitValue((float) $value * $numberScale, 'percent');
		}
		if ($value instanceof CSSUnitValue) {
			if ($value->unit === 'percent') {
				return $value->clone();
			}
			if ($value->unit === 'number') {
				return new CSSUnitValue($value->value * $numberScale, 'percent');
			}
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}

		$category = $value->getNumericCategory();
		if (!in_array($category, ['number', 'percent'], true)) {
			throw new InvalidArgumentException('Color channel math expression must be a number or percentage.');
		}

		return $value->clone();
	}

	protected static function normalizeAngleChannel(CSSNumericValue|CSSKeywordValue|int|float $value): CSSNumericValue|CSSKeywordValue
	{
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			if (!is_finite((float) $value)) {
				throw new InvalidArgumentException('Hue angle must be finite.');
			}
			return new CSSUnitValue((float) $value, 'deg');
		}
		if ($value instanceof CSSUnitValue) {
			if ($value->unit === 'number') {
				return new CSSUnitValue($value->value, 'deg');
			}
			if ($value->getNumericCategory() === 'angle') {
				return $value->clone();
			}
			throw new InvalidArgumentException('Hue channel must be an angle, number, or "none".');
		}

		$category = $value->getNumericCategory();
		if (!in_array($category, ['angle', 'number'], true)) {
			throw new InvalidArgumentException('Hue channel math expression must be an angle or number.');
		}

		return $value->clone();
	}

	protected static function normalizeNumberOrPercentChannel(CSSNumericValue|CSSKeywordValue|int|float $value): CSSNumericValue|CSSKeywordValue
	{
		if ($value instanceof CSSKeywordValue) {
			return self::normalizeNoneKeyword($value);
		}
		if (is_int($value) || is_float($value)) {
			if (!is_finite((float) $value)) {
				throw new InvalidArgumentException('Color channel value must be finite.');
			}
			return new CSSUnitValue((float) $value, 'number');
		}

		$category = $value->getNumericCategory();
		if (!in_array($category, ['number', 'percent'], true)) {
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}

		return $value->clone();
	}

	protected static function resolvePercentUnit(CSSNumericValue|CSSKeywordValue $component): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return $component->unit === 'percent' ? $component->value / 100.0 : $component->value;
	}

	protected static function resolveAngleDegrees(CSSNumericValue|CSSKeywordValue $component): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return $component->unit === 'number' ? $component->value : $component->to('deg')->value;
	}

	protected static function resolveLinearChannel(CSSNumericValue|CSSKeywordValue $component, float $percentReference): float
	{
		if ($component instanceof CSSKeywordValue) {
			return 0.0;
		}
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric channels (unresolved math values cannot be converted).');
		}

		return $component->unit === 'percent' ? ($component->value / 100.0) * $percentReference : $component->value;
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
			1.0479298208405488 * $x + 0.022946793341019088 * $y - 0.05019222954313557 * $z,
			0.029627815688159344 * $x + 0.990434484573249 * $y - 0.01707382502938514 * $z,
			-0.009243058152591178 * $x + 0.015055144896577895 * $y + 0.7518742899580008 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD50ToD65(float $x, float $y, float $z): array
	{
		return [
			0.9554734527042182 * $x - 0.023098536874261423 * $y + 0.0632593086610217 * $z,
			-0.028369706963208136 * $x + 1.0099954580058226 * $y + 0.021041398966943008 * $z,
			0.012314001688319899 * $x - 0.020507696433477912 * $y + 1.3303659366080753 * $z,
		];
	}

	/** @return array{float, float, float} */
	protected static function xyzD50ToLab(float $x, float $y, float $z): array
	{
		$d50 = [0.3457 / 0.3585, 1.0, (1.0 - 0.3457 - 0.3585) / 0.3585];
		$epsilon = 216.0 / 24389.0;
		$kappa = 24389.0 / 27.0;

		$f = static fn (float $v): float => $v > $epsilon ? ($v ** (1.0 / 3.0)) : ($kappa * $v + 16.0) / 116.0;
		$fx = $f($x / $d50[0]);
		$fy = $f($y / $d50[1]);
		$fz = $f($z / $d50[2]);

		return [
			116.0 * $fy - 16.0,
			500.0 * ($fx - $fy),
			200.0 * ($fy - $fz),
		];
	}

	/** @return array{float, float, float} */
	protected static function labToXyzD50(float $l, float $a, float $b): array
	{
		$d50 = [0.3457 / 0.3585, 1.0, (1.0 - 0.3457 - 0.3585) / 0.3585];
		$epsilon = 216.0 / 24389.0;
		$kappa = 24389.0 / 27.0;

		$fy = ($l + 16.0) / 116.0;
		$fx = $a / 500.0 + $fy;
		$fz = $fy - $b / 200.0;

		$xr = ($fx ** 3) > $epsilon ? ($fx ** 3) : (116.0 * $fx - 16.0) / $kappa;
		$yr = $l > $kappa * $epsilon ? ((($l + 16.0) / 116.0) ** 3) : $l / $kappa;
		$zr = ($fz ** 3) > $epsilon ? ($fz ** 3) : (116.0 * $fz - 16.0) / $kappa;

		return [$xr * $d50[0], $yr * $d50[1], $zr * $d50[2]];
	}

	/** @return array{float, float, float} */
	protected static function xyzD65ToOklab(float $x, float $y, float $z): array
	{
		$l = 0.8190224379967030 * $x + 0.3619062600528904 * $y - 0.1288737815209879 * $z;
		$m = 0.0329836539323885 * $x + 0.9292868615863434 * $y + 0.0361446663506424 * $z;
		$s = 0.0481771893596242 * $x + 0.2642395317527308 * $y + 0.6335478284694309 * $z;

		$cbrt = static fn (float $v): float => $v < 0.0 ? -((-$v) ** (1.0 / 3.0)) : ($v ** (1.0 / 3.0));
		$l_ = $cbrt($l);
		$m_ = $cbrt($m);
		$s_ = $cbrt($s);

		return [
			0.2104542683093140 * $l_ + 0.7936177747023054 * $m_ - 0.0040720430116193 * $s_,
			1.9779985324311684 * $l_ - 2.4285922420485799 * $m_ + 0.4505937096174110 * $s_,
			0.0259040424655478 * $l_ + 0.7827717124575296 * $m_ - 0.8086757549230774 * $s_,
		];
	}

	/** @return array{float, float, float} */
	protected static function oklabToXyzD65(float $l, float $a, float $b): array
	{
		$l_ = $l + 0.3963377773761749 * $a + 0.2158037573099136 * $b;
		$m_ = $l - 0.1055613458156586 * $a - 0.0638541728258133 * $b;
		$s_ = $l - 0.0894841775298119 * $a - 1.2914855480194092 * $b;

		$lc = $l_ ** 3;
		$mc = $m_ ** 3;
		$sc = $s_ ** 3;

		return [
			1.2268798758459243 * $lc - 0.5578149944602171 * $mc + 0.2813910456659647 * $sc,
			-0.0405757452148008 * $lc + 1.1122868032803170 * $mc - 0.0717110580655164 * $sc,
			-0.0763729366746601 * $lc - 0.4214933324022432 * $mc + 1.5869240198367816 * $sc,
		];
	}

	private static function parseHex(string $hex): CSSRGB
	{
		$digits = substr($hex, 1);
		$len = strlen($digits);
		if (!in_array($len, [3, 4, 6, 8], true) || !ctype_xdigit($digits)) {
			throw new InvalidArgumentException('Invalid CSS hex color: ' . $hex);
		}

		if ($len === 3 || $len === 4) {
			$r = hexdec($digits[0] . $digits[0]);
			$g = hexdec($digits[1] . $digits[1]);
			$b = hexdec($digits[2] . $digits[2]);
			$a = $len === 4 ? hexdec($digits[3] . $digits[3]) : 255;
		} else {
			$r = hexdec(substr($digits, 0, 2));
			$g = hexdec(substr($digits, 2, 2));
			$b = hexdec(substr($digits, 4, 2));
			$a = $len === 8 ? hexdec(substr($digits, 6, 2)) : 255;
		}

		return new CSSRGB(
			new CSSUnitValue(($r / 255.0) * 100.0, 'percent'),
			new CSSUnitValue(($g / 255.0) * 100.0, 'percent'),
			new CSSUnitValue(($b / 255.0) * 100.0, 'percent'),
			new CSSUnitValue(($a / 255.0) * 100.0, 'percent'),
		);
	}

	/**
	 * Splits a color function body into [channels, alphaToken, isLegacyComma].
	 *
	 * @return array{list<string>, ?string, bool}
	 */
	private static function splitColorArguments(string $body, bool $allowLegacyComma): array
	{
		$commaParts = CSSSyntaxScanner::splitTopLevel($body, ',');
		$slashParts = CSSSyntaxScanner::splitTopLevel($body, '/');

		if (count($commaParts) > 1) {
			if (!$allowLegacyComma || count($slashParts) > 1) {
				throw new InvalidArgumentException('Invalid comma syntax in CSS color function.');
			}
			$trimmed = array_map('trim', $commaParts);
			foreach ($trimmed as $part) {
				if ($part === '') {
					throw new InvalidArgumentException('Empty component in CSS color function.');
				}
			}
			if (count($trimmed) === 3) {
				return [$trimmed, null, true];
			}
			if (count($trimmed) === 4) {
				return [array_slice($trimmed, 0, 3), $trimmed[3], true];
			}
			throw new InvalidArgumentException('Invalid argument count in legacy CSS color function.');
		}

		if (count($slashParts) > 2) {
			throw new InvalidArgumentException('Too many "/" separators in CSS color function.');
		}

		$channels = CSSSyntaxScanner::splitTopLevelWhitespace(trim($slashParts[0]));
		$alpha = null;
		if (count($slashParts) === 2) {
			$alphaTokens = CSSSyntaxScanner::splitTopLevelWhitespace(trim($slashParts[1]));
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
	): CSSNumericValue|CSSKeywordValue {
		$token = trim($token);
		if ($token === '') {
			throw new InvalidArgumentException('Empty color component.');
		}
		if (strcasecmp($token, 'none') === 0) {
			if (!$allowNone) {
				throw new InvalidArgumentException('"none" keyword is not allowed in legacy comma-separated color syntax.');
			}
			return new CSSKeywordValue('none');
		}

		return CSSNumericValue::parse($token, null, $options);
	}

	private static function parseRgbFunction(string $body, CSSParserOptions $options): CSSRGB
	{
		[$channels, $alphaToken, $legacy] = self::splitColorArguments($body, true);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('rgb() requires 3 color channels.');
		}

		$parsed = [];
		$seenPercent = false;
		$seenNumber = false;
		foreach ($channels as $token) {
			$component = self::parseComponentToken($token, !$legacy, $options);
			if ($component instanceof CSSUnitValue) {
				if ($component->unit === 'percent') {
					$seenPercent = true;
					$parsed[] = $component;
				} elseif ($component->unit === 'number') {
					$seenNumber = true;
					$parsed[] = new CSSUnitValue(($component->value / 255.0) * 100.0, 'percent');
				} else {
					throw new InvalidArgumentException('rgb() channels must be numbers, percentages, or "none".');
				}
			} elseif ($component instanceof CSSKeywordValue) {
				$parsed[] = $component;
			} else {
				$category = $component->getNumericCategory();
				if (!in_array($category, ['number', 'percent'], true)) {
					throw new InvalidArgumentException('rgb() math channel must be a number or percentage.');
				}
				if ($category === 'percent') {
					$seenPercent = true;
				} else {
					$seenNumber = true;
				}
				$parsed[] = $component;
			}
		}

		if ($legacy && $seenPercent && $seenNumber) {
			throw new InvalidArgumentException('Legacy comma-separated rgb() cannot mix numbers and percentages.');
		}

		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, !$legacy, $options), 100.0);

		return new CSSRGB($parsed[0], $parsed[1], $parsed[2], $alpha);
	}

	private static function parseHslFunction(string $body, CSSParserOptions $options): CSSHSL
	{
		[$channels, $alphaToken, $legacy] = self::splitColorArguments($body, true);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('hsl() requires 3 color channels.');
		}

		$h = self::normalizeAngleChannel(self::parseComponentToken($channels[0], !$legacy, $options));
		$sRaw = self::parseComponentToken($channels[1], !$legacy, $options);
		$lRaw = self::parseComponentToken($channels[2], !$legacy, $options);
		if ($legacy) {
			if (!($sRaw instanceof CSSNumericValue && $sRaw->getNumericCategory() === 'percent')
				|| !($lRaw instanceof CSSNumericValue && $lRaw->getNumericCategory() === 'percent')) {
				throw new InvalidArgumentException('Legacy comma-separated hsl() requires percentage saturation and lightness.');
			}
		}
		$s = self::normalizePercentChannel($sRaw, 1.0);
		$l = self::normalizePercentChannel($lRaw, 1.0);
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, !$legacy, $options), 100.0);

		return new CSSHSL($h, $s, $l, $alpha);
	}

	private static function parseHwbFunction(string $body, CSSParserOptions $options): CSSHWB
	{
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('hwb() requires 3 color channels.');
		}

		$h = self::normalizeAngleChannel(self::parseComponentToken($channels[0], true, $options));
		$w = self::normalizePercentChannel(self::parseComponentToken($channels[1], true, $options), 1.0);
		$b = self::normalizePercentChannel(self::parseComponentToken($channels[2], true, $options), 1.0);
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSHWB($h, $w, $b, $alpha);
	}

	private static function parseLabFunction(string $body, CSSParserOptions $options): CSSLab
	{
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('lab() requires 3 color channels.');
		}

		$l = self::normalizePercentChannel(self::parseComponentToken($channels[0], true, $options), 1.0);
		$a = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[1], true, $options));
		$b = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[2], true, $options));
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSLab($l, $a, $b, $alpha);
	}

	private static function parseLchFunction(string $body, CSSParserOptions $options): CSSLCH
	{
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('lch() requires 3 color channels.');
		}

		$l = self::normalizePercentChannel(self::parseComponentToken($channels[0], true, $options), 1.0);
		$c = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[1], true, $options));
		$h = self::normalizeAngleChannel(self::parseComponentToken($channels[2], true, $options));
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSLCH($l, $c, $h, $alpha);
	}

	private static function parseOklabFunction(string $body, CSSParserOptions $options): CSSOKLab
	{
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('oklab() requires 3 color channels.');
		}

		$l = self::normalizePercentChannel(self::parseComponentToken($channels[0], true, $options), 100.0);
		$a = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[1], true, $options));
		$b = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[2], true, $options));
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSOKLab($l, $a, $b, $alpha);
	}

	private static function parseOklchFunction(string $body, CSSParserOptions $options): CSSOKLCH
	{
		[$channels, $alphaToken] = self::splitColorArguments($body, false);
		if (count($channels) !== 3) {
			throw new InvalidArgumentException('oklch() requires 3 color channels.');
		}

		$l = self::normalizePercentChannel(self::parseComponentToken($channels[0], true, $options), 100.0);
		$c = self::normalizeNumberOrPercentChannel(self::parseComponentToken($channels[1], true, $options));
		$h = self::normalizeAngleChannel(self::parseComponentToken($channels[2], true, $options));
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSOKLCH($l, $c, $h, $alpha);
	}

	private static function parseColorFunction(string $body, CSSParserOptions $options): CSSColor
	{
		[$tokens, $alphaToken] = self::splitColorArguments($body, false);
		if (count($tokens) < 2) {
			throw new InvalidArgumentException('color() requires a color space and at least one channel.');
		}

		$space = array_shift($tokens);
		$channels = array_map(
			static fn (string $token): CSSNumericValue|CSSKeywordValue => self::normalizeNumberOrPercentChannel(self::parseComponentToken($token, true, $options)),
			$tokens,
		);
		$alpha = $alphaToken === null
			? new CSSUnitValue(100.0, 'percent')
			: self::normalizePercentChannel(self::parseComponentToken($alphaToken, true, $options), 100.0);

		return new CSSColor($space, $channels, $alpha);
	}
}
