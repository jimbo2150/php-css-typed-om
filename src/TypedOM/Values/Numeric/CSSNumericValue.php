<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
abstract class CSSNumericValue extends CSSStyleValue
{
	use SimpleValueTrait, TypeableUnitTrait, LengthTrait;

	/**
	 * CSSNumericValue constructor.
	 */
	public function __construct() {
	}

	/**
	 * The parse() method of the CSSNumericValue interface creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue/parse
	 */
	public static function parse(string $propertyOrCssText, ?string $cssText = null): static
	{
		if ($cssText !== null) {
			$value = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue::parse($propertyOrCssText, $cssText);
			if (!$value instanceof self) {
				throw new \InvalidArgumentException('CSS property value is not numeric.');
			}
			return $value;
		}

		$cssText = trim($propertyOrCssText);

		if (preg_match('/^(min|max|clamp)\(/i', $cssText, $functionMatch)) {
			$open = strpos($cssText, '(');
			$close = CSSSyntaxScanner::findMatchingDelimiter($cssText, $open);
			if ($close !== strlen($cssText) - 1) {
				throw new \InvalidArgumentException('Invalid CSS math function: ' . $cssText);
			}

			$arguments = array_map(
				static fn (string $argument): CSSNumericValue => self::parse(trim($argument)),
				CSSSyntaxScanner::splitTopLevel(substr($cssText, $open + 1, $close - $open - 1), ','),
			);
			$function = strtolower($functionMatch[1]);
			if (($function === 'clamp' && count($arguments) !== 3) || ($function !== 'clamp' && $arguments === [])) {
				throw new \InvalidArgumentException('Invalid argument count for ' . $function . '().');
			}

			return match ($function) {
				'min' => new CSSMathMin($arguments),
				'max' => new CSSMathMax($arguments),
				'clamp' => new CSSMathClamp($arguments),
			};
		}

		// Handle calc() expressions
		if (preg_match('/^calc\(/i', $cssText) && str_ends_with($cssText, ')')) {
			return CSSCalcParser::parse($cssText);
		}

		// This is a simplified parser. A real one would need a proper tokenizer
		// and handle calc() expressions.
		if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)([a-zA-Z%]*)$/D', $cssText, $matches)) {
			$value = (float) $matches[1];
			$unit = $matches[2];

			if (empty($unit)) {
				$unit = 'number';
			}

			return new CSSUnitValue($value, $unit);
		}

		throw new \InvalidArgumentException('Invalid CSS numeric value: '.$cssText);
	}

	/**
	 * Creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 */
	public function from(string $cssText): static
	{
		return static::parse($cssText);
	}

	/**
	 * Converts the numeric value to the specified unit.
	 *
	 * @param string $unit The target unit
	 * @param CSSContext|null $context Optional context for relative unit conversions (default: new CSSContext())
	 * @return CSSUnitValue The converted value
	 */
	public function to(string $unit, ?CSSContext $context = null): CSSNumericValue
	{
		$context = $context ?? new CSSContext();
		$targetUnit = self::resolveUnit($unit);
		$currentCategory = self::numericCategory($this);
		$targetCategory = $targetUnit->type()?->value ?? 'number';
		if ($currentCategory !== $targetCategory) {
			throw new \InvalidArgumentException(sprintf('Cannot convert %s to %s.', $currentCategory, $targetCategory));
		}

		return self::convertNumericValue($this, $targetUnit, $context);
	}

	/**
	 * Converts a value from the given unit to pixels.
	 *
	 * @param float $value The value to convert
	 * @param string $unit The source unit
	 * @return float The value in pixels
	 */
	private static function convertToPx(float $value, string $unit, CSSContext $context): float
	{
		$factor = $context->getToPxFactor($unit);
		return $value * $factor;
	}

	/**
	 * Converts a pixel value to the specified unit.
	 *
	 * @param float $pxValue The value in pixels
	 * @param string $unit The target unit
	 * @return float The converted value
	 */
	private static function convertFromPx(float $pxValue, string $unit, CSSContext $context): float
	{
		$factor = $context->getFromPxFactor($unit);
		return $pxValue * $factor;
	}

	private static function resolveUnit(string $unit): CSSUnitEnum
	{
		$symbol = \Jimbo2150\PhpCssTypedOm\CSS::translateUnit(strtolower($unit));
		$resolved = CSSUnitEnum::tryFrom($symbol);
		if ($resolved !== null) {
			return $resolved;
		}
		foreach (CSSUnitEnum::cases() as $candidate) {
			if (strcasecmp($candidate->value, $symbol) === 0) {
				return $candidate;
			}
		}
		throw new \InvalidArgumentException('Unsupported CSS unit: ' . $unit);
	}

	private static function numericCategory(CSSNumericValue $value): string
	{
		if ($value instanceof CSSUnitValue) {
			return $value->unitObj?->type()?->value ?? 'number';
		}
		if (!$value instanceof CSSMathValue) {
			return 'unknown';
		}

		$values = $value->values->values;
		if ($value instanceof CSSMathNegate || $value instanceof CSSMathInvert) {
			$category = self::numericCategory($values[0] ?? $value);
			return $value instanceof CSSMathInvert && $category !== 'number' ? 'inverse-' . $category : $category;
		}
		$categories = array_map(self::numericCategory(...), $values);
		if ($value instanceof CSSMathProduct) {
			$dimensional = array_values(array_filter($categories, static fn (string $category): bool => $category !== 'number'));
			return count($dimensional) === 1 ? $dimensional[0] : (count($dimensional) === 0 ? 'number' : 'derived');
		}

		$categories = array_unique($categories);
		return count($categories) === 1 ? $categories[0] : 'derived';
	}

	public function getNumericCategory(): string
	{
		return self::numericCategory($this);
	}

	public function getNumericType(): CSSNumericType
	{
		if ($this instanceof CSSUnitValue) {
			$unitType = $this->unitObj?->type();
			return CSSNumericType::fromDimensions($unitType === null ? [] : [$unitType->value => 1]);
		}
		if (!$this instanceof CSSMathValue) {
			return CSSNumericType::fromDimensions([]);
		}

		$values = $this->values->values;
		if ($this instanceof CSSMathNegate) {
			if (!isset($values[0])) {
				throw new \InvalidArgumentException('Negation requires a numeric operand.');
			}
			return $values[0]->getNumericType();
		}
		if ($this instanceof CSSMathInvert) {
			if (!isset($values[0])) {
				throw new \InvalidArgumentException('Inversion requires a numeric operand.');
			}
			return $values[0]->getNumericType()->invert();
		}
		if ($this instanceof CSSMathProduct) {
			$type = CSSNumericType::fromDimensions([]);
			foreach ($values as $value) {
				$type = $type->multiply($value->getNumericType());
			}
			return $type;
		}

		$first = $values[0] ?? null;
		if ($first === null) {
			return CSSNumericType::fromDimensions([]);
		}
		$type = $first->getNumericType();
		foreach (array_slice($values, 1) as $value) {
			if (!$type->equals($value->getNumericType())) {
				throw new \InvalidArgumentException('CSS math operands have incompatible numeric types.');
			}
		}
		return $type;
	}

	public function getType(): CSSNumericType
	{
		return $this->getNumericType();
	}

	public function __get(string $name): mixed
	{
		if ($name === 'type') {
			return $this->getNumericType();
		}
		throw new \Error('Undefined property: ' . static::class . '::$' . $name);
	}

	private static function convertNumericValue(CSSNumericValue $value, CSSUnitEnum $targetUnit, CSSContext $context): CSSNumericValue
	{
		if ($value instanceof CSSUnitValue) {
			$sourceUnit = $value->unitObj;
			if ($sourceUnit === null) {
				throw new \InvalidArgumentException('The numeric value has no unit.');
			}
			if ($sourceUnit === $targetUnit) {
				return new CSSUnitValue($value->value, $targetUnit);
			}
			$category = $sourceUnit->type()?->value;
			if ($category === CSSUnitTypeEnum::LENGTH->value) {
				$base = self::convertToPx($value->value, $sourceUnit->toString(), $context);
				return new CSSUnitValue(self::convertFromPx($base, $targetUnit->toString(), $context), $targetUnit);
			}
			$factors = [
				'angle' => ['deg' => 1.0, 'rad' => 180 / M_PI, 'grad' => 0.9, 'turn' => 360.0],
				'time' => ['s' => 1.0, 'ms' => 0.001],
				'frequency' => ['hz' => 1.0, 'khz' => 1000.0],
				'resolution' => ['dpi' => 1.0, 'dpcm' => 2.54, 'dppx' => 96.0],
				'flex' => ['fr' => 1.0],
			];
			$categoryFactors = $factors[$category ?? ''] ?? [];
			$from = $categoryFactors[strtolower($sourceUnit->toString())] ?? null;
			$to = $categoryFactors[strtolower($targetUnit->toString())] ?? null;
			if ($from === null || $to === null) {
				throw new \InvalidArgumentException('Unit conversion is not supported for these units.');
			}
			return new CSSUnitValue($value->value * $from / $to, $targetUnit);
		}

		if ($value instanceof CSSMathNegate) {
			return new CSSMathNegate([self::convertNumericValue($value->values->values[0], $targetUnit, $context)]);
		}
		if ($value instanceof CSSMathInvert) {
			throw new \InvalidArgumentException('Cannot convert an inverted numeric value to a direct unit.');
		}
		if ($value instanceof CSSMathProduct) {
			$values = $value->values->values;
			$dimensionalIndexes = [];
			foreach ($values as $index => $operand) {
				if (self::numericCategory($operand) !== 'number') {
					$dimensionalIndexes[] = $index;
				}
			}
			if (count($dimensionalIndexes) !== 1) {
				throw new \InvalidArgumentException('Only products with a single unit-bearing operand can be converted.');
			}
			$index = $dimensionalIndexes[0];
			$values[$index] = self::convertNumericValue($values[$index], $targetUnit, $context);
			return new CSSMathProduct($values);
		}

		$converted = array_map(static fn (CSSNumericValue $operand): CSSNumericValue => self::convertNumericValue($operand, $targetUnit, $context), $value->values->values);
		return match (true) {
			$value instanceof CSSMathSum => new CSSMathSum($converted),
			$value instanceof CSSMathMin => new CSSMathMin($converted),
			$value instanceof CSSMathMax => new CSSMathMax($converted),
			$value instanceof CSSMathClamp => new CSSMathClamp($converted),
			default => throw new \InvalidArgumentException('Unsupported numeric expression conversion.'),
		};
	}

	/**
	 * Converts the current value to each specified unit and returns their sum.
	 *
	 * @param string ...$units The units to convert to and sum
	 * @return CSSMathSum A CSSMathSum containing the sum of all converted values
	 */
	public function toSum(string ...$units): CSSMathSum
	{
		if ($units === []) {
			return new CSSMathSum([$this]);
		}
		$convertedValues = [];
		$currentValues = $this->_getCurrentValues();

		// Convert each current value to the corresponding unit
		foreach ($units as $i => $unit) {
			$value = $currentValues[$i] ?? $this;
			$convertedValues[] = $value->to($unit);
		}

		// Return CSSMathSum with all converted values
		return new CSSMathSum($convertedValues);
	}


	/**
	 * Adds the given value to this numeric value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The value to add
	 * @return CSSNumericValue The result of the addition
	 */
	public function add(CSSNumericValue ...$values): CSSNumericValue
	{
		self::assertHasOperands($values);
		$this->assertCompatibleAddends($values);
		return new CSSMathSum([...self::sumOperands($this), ...$values]);
	}

	/**
	 * Subtracts the given value from this numeric value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The value to subtract
	 * @return CSSNumericValue The result of the subtraction
	 */
	public function sub(CSSNumericValue ...$values): CSSNumericValue
	{
		self::assertHasOperands($values);
		$this->assertCompatibleAddends($values);
		return new CSSMathSum([...self::sumOperands($this), ...array_map(static fn (CSSNumericValue $value): CSSMathNegate => new CSSMathNegate([$value]), $values)]);
	}

	/**
	 * Multiplies this numeric value by the given value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The multiplier
	 * @return CSSMathProduct The result of the multiplication
	 */
	public function mul(CSSNumericValue ...$values): CSSMathProduct
	{
		self::assertHasOperands($values);
		return new CSSMathProduct([$this, ...$values]);
	}

	/**
	 * Divides this numeric value by the given value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The divisor
	 * @return CSSMathProduct The result of the division
	 */
	public function div(CSSNumericValue ...$values): CSSMathProduct
	{
		self::assertHasOperands($values);
		return new CSSMathProduct([$this, ...array_map(static fn (CSSNumericValue $value): CSSMathInvert => new CSSMathInvert([$value]), $values)]);
	}

	/**
	 * Returns the minimum value between this and the given values.
	 *
	 * @param CSSNumericArray $values The values to compare
	 * @return CSSMathMin The minimum value
	 */
	public function min(CSSNumericArray|CSSNumericValue ...$values): CSSMathMin
	{
		$operands = self::flattenNumericArguments($values);
		self::assertHasOperands($operands);
		$this->assertCompatibleAddends($operands);
		return new CSSMathMin([$this, ...$operands]);
	}

	/**
	 * Returns the maximum value between this and the given values.
	 *
	 * @param CSSNumericArray $values The values to compare
	 * @return CSSMathMax The maximum value
	 */
	public function max(CSSNumericArray|CSSNumericValue ...$values): CSSMathMax
	{
		$operands = self::flattenNumericArguments($values);
		self::assertHasOperands($operands);
		$this->assertCompatibleAddends($operands);
		return new CSSMathMax([$this, ...$operands]);
	}

	/**
	 * Checks if this numeric value is equal to another.
	 *
	 * @param self $numericValue The value to compare
	 * @return bool True if equal, false otherwise
	 */
	public function equals(self ...$numericValues): bool
	{
		foreach ($numericValues as $numericValue) {
			if (!$this->equalsOne($numericValue)) {
				return false;
			}
		}
		return true;
	}

	private function equalsOne(self $numericValue): bool
	{
		if ($this instanceof CSSUnitValue && $numericValue instanceof CSSUnitValue) {
			if ($this->unit === $numericValue->unit) {
				return abs($this->value - $numericValue->value) < 0.0001;
			} else {
				// Check if units are of the same type (e.g., both length)
				if ($this->type() === $numericValue->type()) {
					try {
						$converted = $numericValue->to($this->unit);
						return abs($this->value - $converted->value) < 0.0001;
					} catch (\Exception $e) {
						return false;
					}
				} else {
					return false;
				}
			}
		} else {
			// For other cases, compare recursively if same type
			if (get_class($this) === get_class($numericValue)) {
				$thisValues = $this->_getCurrentValues();
				$otherValues = $numericValue->_getCurrentValues();
				if (count($thisValues) === count($otherValues)) {
					foreach ($thisValues as $i => $value) {
						if (!$value->equals($otherValues[$i])) {
							return false;
						}
					}
					return true;
				}
			}
			return false;
		}
	}

	/**
	 * Gets the current values for this numeric value.
	 *
	 * @return array The current values
	 */
	protected function _getCurrentValues(): array {
		return property_exists($this, 'values') && $this->values instanceof \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray
			? $this->values->values
			: [$this];
	}

	/** @param list<CSSNumericValue> $values */
	private static function assertHasOperands(array $values): void
	{
		if ($values === []) {
			throw new \InvalidArgumentException('At least one numeric operand is required.');
		}
	}

	/** @param list<CSSNumericArray|CSSNumericValue> $values @return list<CSSNumericValue> */
	private static function flattenNumericArguments(array $values): array
	{
		$result = [];
		foreach ($values as $value) {
			if ($value instanceof CSSNumericArray) {
				array_push($result, ...$value->values);
			} else {
				$result[] = $value;
			}
		}
		return $result;
	}

	/** @param list<CSSNumericValue> $values */
	private function assertCompatibleAddends(array $values): void
	{
		$expectedType = $this->getNumericType();
		foreach ($values as $value) {
			if (!$expectedType->equals($value->getNumericType())) {
				throw new \InvalidArgumentException('CSS numeric addends must have compatible types.');
			}
		}
	}

	/** @return list<CSSNumericValue> */
	private static function sumOperands(CSSNumericValue $value): array
	{
		return $value instanceof CSSMathSum ? $value->values->values : [$value];
	}
}
