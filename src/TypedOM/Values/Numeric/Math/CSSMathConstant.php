<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;

/**
 * Represents a CSS Values Level 4 `<calc-keyword>` math constant (`e`, `pi`, `infinity`, `-infinity`, `NaN`).
 *
 * Keeps `CSSUnitValue::$value` strictly finite per WebIDL `double` semantics while representing
 * constants and degenerate non-finite calculation outcomes inside math expression trees.
 *
 * @see https://drafts.csswg.org/css-values-4/#calc-constants
 */
final class CSSMathConstant extends CSSMathValue
{
	public const ?string operator = 'constant';

	public readonly CSSMathConstantEnum $constant;

	/**
	 * @throws InvalidArgumentException When $constant is not a recognized CSS math constant keyword
	 */
	public function __construct(CSSMathConstantEnum|string $constant)
	{
		if (is_string($constant)) {
			$resolved = CSSMathConstantEnum::tryFromKeyword($constant);
			if ($resolved === null) {
				throw new InvalidArgumentException('Unsupported CSS math constant: ' . $constant);
			}
			$constant = $resolved;
		}
		$this->constant = $constant;
		parent::__construct([]);
		$this->cachedNumericType = CSSNumericType::fromValidatedDimensions([]);
	}

	public static function e(): self
	{
		return new self(CSSMathConstantEnum::E);
	}

	public static function pi(): self
	{
		return new self(CSSMathConstantEnum::PI);
	}

	public static function infinity(): self
	{
		return new self(CSSMathConstantEnum::INFINITY);
	}

	public static function negInfinity(): self
	{
		return new self(CSSMathConstantEnum::NEG_INFINITY);
	}

	public static function nan(): self
	{
		return new self(CSSMathConstantEnum::NAN);
	}

	public function getConstant(): CSSMathConstantEnum
	{
		return $this->constant;
	}

	public function getFloatValue(): float
	{
		return $this->constant->toFloat();
	}

	public function isFinite(): bool
	{
		return $this->constant === CSSMathConstantEnum::E || $this->constant === CSSMathConstantEnum::PI;
	}

	public function isInfinite(): bool
	{
		return $this->constant === CSSMathConstantEnum::INFINITY || $this->constant === CSSMathConstantEnum::NEG_INFINITY;
	}

	public function isNaN(): bool
	{
		return $this->constant === CSSMathConstantEnum::NAN;
	}
}
