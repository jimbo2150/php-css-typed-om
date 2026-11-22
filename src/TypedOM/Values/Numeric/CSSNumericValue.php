<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;

/**
 * Base class for CSS Typed OM numeric values (`CSSUnitValue` and `CSSMathValue`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
abstract class CSSNumericValue extends CSSStyleValue
{
	use SimpleValueTrait, LengthTrait;
	use TypeableUnitTrait {
		TypeableUnitTrait::type as public unitType;
	}

	/** @var array<string, array<string, float>> */
	private const array DIMENSION_FACTORS = [
		'angle' => ['deg' => 1.0, 'rad' => 180.0 / M_PI, 'grad' => 0.9, 'turn' => 360.0],
		'time' => ['s' => 1.0, 'ms' => 0.001],
		'frequency' => ['hz' => 1.0, 'khz' => 1000.0],
		'resolution' => ['dpi' => 1.0, 'dpcm' => 2.54, 'dppx' => 96.0, 'x' => 96.0],
		'flex' => ['fr' => 1.0],
	];

	public function __construct()
	{
	}

	/**
	 * Parses a CSS numeric value or math expression (`calc()`, `min()`, `max()`, `clamp()`).
	 *
	 * @throws InvalidArgumentException When syntax is invalid, non-finite, dimensionally incompatible, or exceeds maxMathDepth
	 */
	public static function parse(
		string $propertyOrCssText,
		?string $cssText = null,
		?CSSParserOptions $options = null,
		int $depth = 0,
	): self {
		if ($cssText !== null) {
			$value = CSSStyleValue::parse($propertyOrCssText, $cssText, $options);
			if (!$value instanceof self) {
				throw new InvalidArgumentException('CSS property value is not numeric.');
			}
			return $value;
		}

		$options ??= new CSSParserOptions();
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new InvalidArgumentException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}

		$cssText = trim($propertyOrCssText);
		if ($cssText === '') {
			throw new InvalidArgumentException('Invalid CSS numeric value.');
		}

		if (preg_match('/^(min|max|clamp)\s*\(/i', $cssText, $functionMatch)) {
			$open = strpos($cssText, '(');
			$close = $open === false ? null : CSSSyntaxScanner::findMatchingDelimiter($cssText, $open);
			if ($close !== strlen($cssText) - 1) {
				throw new InvalidArgumentException('Invalid CSS math function: ' . $cssText);
			}

			$rawArgs = CSSSyntaxScanner::splitTopLevel(substr($cssText, $open + 1, $close - $open - 1), ',');
			$arguments = array_map(
				static fn (string $argument): CSSNumericValue => self::parse(trim($argument), null, $options, $depth + 1),
				$rawArgs,
			);
			$function = strtolower($functionMatch[1]);
			if (($function === 'clamp' && count($arguments) !== 3) || ($function !== 'clamp' && $arguments === [])) {
				throw new InvalidArgumentException('Invalid argument count for ' . $function . '().');
			}

			return match ($function) {
				'min' => new CSSMathMin($arguments),
				'max' => new CSSMathMax($arguments),
				'clamp' => new CSSMathClamp($arguments[0], $arguments[1], $arguments[2]),
			};
		}

		if (preg_match('/^calc\s*\(/i', $cssText) && str_ends_with($cssText, ')')) {
			return CSSCalcParser::parse($cssText, $options, $depth);
		}

		if (preg_match('/^([+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)([a-zA-Z%]*)$/D', $cssText, $matches)) {
			$value = (float) $matches[1];
			if (!is_finite($value)) {
				throw new InvalidArgumentException('CSS numeric value must be finite: ' . $cssText);
			}
			$unit = CSSUnitEnum::tryFromSymbol($matches[2]);
			if ($unit === null) {
				throw new InvalidArgumentException('Unsupported CSS unit: ' . $matches[2]);
			}
			return new CSSUnitValue($value, $unit);
		}

		throw new InvalidArgumentException('Invalid CSS numeric value: ' . $cssText);
	}

	public function from(string $cssText): self
	{
		return self::parse($cssText);
	}

	/**
	 * Returns the CSSNumericType exponent map of this value per Typed OM.
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue/type
	 */
	public function type(): CSSNumericType
	{
		return $this->getNumericType();
	}

	public function getType(): CSSNumericType
	{
		return $this->getNumericType();
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
				throw new InvalidArgumentException('Negation requires a numeric operand.');
			}
			return $values[0]->getNumericType();
		}
		if ($this instanceof CSSMathInvert) {
			if (!isset($values[0])) {
				throw new InvalidArgumentException('Inversion requires a numeric operand.');
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

		return self::mergeAdditiveTypes($values);
	}

	public function getNumericCategory(): string
	{
		$type = $this->getNumericType();
		$nonZero = [];
		foreach ($type->toArray() as $dimension => $exponent) {
			if ($dimension === 'percentHint') {
				continue;
			}
			if ((int) $exponent !== 0) {
				$nonZero[$dimension] = (int) $exponent;
			}
		}
		if ($nonZero === []) {
			return 'number';
		}
		if (count($nonZero) === 1) {
			$dimension = array_key_first($nonZero);
			$exponent = $nonZero[$dimension];
			if ($exponent === 1) {
				return $dimension;
			}
			if ($exponent === -1) {
				return 'inverse-' . $dimension;
			}
		}

		return 'derived';
	}

	/**
	 * Converts this numeric value to the specified compatible unit.
	 *
	 * @throws InvalidArgumentException When converting across incompatible dimensions or unsupported units
	 */
	public function to(string $unit, ?CSSContext $context = null): self
	{
		$context ??= new CSSContext();
		$targetUnit = self::resolveUnit($unit);
		$currentType = $this->getNumericType();
		$targetDimension = $targetUnit->type()?->value;
		$expectedType = CSSNumericType::fromDimensions($targetDimension === null ? [] : [$targetDimension => 1]);

		if ($currentType->percentHint !== null) {
			throw new InvalidArgumentException('Cannot convert a mixed percentage expression directly to a single unit.');
		}
		if (!$currentType->equals($expectedType)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot convert %s to %s.',
				$this->getNumericCategory(),
				$targetDimension ?? 'number',
			));
		}

		return self::convertNumericValue($this, $targetUnit, $context);
	}

	/**
	 * Converts and groups this value into a CSSMathSum over the requested units.
	 *
	 * @throws InvalidArgumentException When any requested unit is incompatible
	 */
	public function toSum(string ...$units): CSSMathSum
	{
		if ($units === []) {
			return new CSSMathSum([$this]);
		}

		if ($this instanceof CSSUnitValue) {
			$convertedValues = [];
			foreach ($units as $unit) {
				$convertedValues[] = $this->to($unit);
			}
			return new CSSMathSum($convertedValues);
		}

		$currentValues = $this->_getCurrentValues();
		if (count($currentValues) <= count($units)) {
			$convertedValues = [];
			foreach ($units as $index => $unit) {
				$source = $currentValues[$index] ?? $this;
				$convertedValues[] = $source->to($unit);
			}
			return new CSSMathSum($convertedValues);
		}

		$buckets = [];
		foreach ($units as $unit) {
			$resolved = self::resolveUnit($unit);
			$buckets[$resolved->toUnitName()] = new CSSUnitValue(0.0, $resolved);
		}

		$primary = array_key_first($buckets);
		foreach ($currentValues as $term) {
			$targetKey = $term instanceof CSSUnitValue && isset($buckets[$term->unit]) ? $term->unit : $primary;
			$converted = $term->to($targetKey);
			if ($converted instanceof CSSUnitValue) {
				$buckets[$targetKey] = new CSSUnitValue($buckets[$targetKey]->value + $converted->value, $targetKey);
			} else {
				return new CSSMathSum(array_map(static fn (CSSNumericValue $value): CSSNumericValue => $value->to($primary), $currentValues));
			}
		}

		return new CSSMathSum(array_values($buckets));
	}

	public function add(CSSNumericValue|int|float ...$values): self
	{
		$operands = self::normalizeVariadicOperands($values);
		$all = [...self::sumOperands($this), ...$operands];
		self::mergeAdditiveTypes($all);

		return new CSSMathSum($all);
	}

	public function sub(CSSNumericValue|int|float ...$values): self
	{
		$operands = self::normalizeVariadicOperands($values);
		$negated = array_map(static fn (self $value): CSSMathNegate => new CSSMathNegate([$value]), $operands);
		$all = [...self::sumOperands($this), ...$negated];
		self::mergeAdditiveTypes($all);

		return new CSSMathSum($all);
	}

	public function mul(CSSNumericValue|int|float ...$values): CSSMathProduct
	{
		$operands = self::normalizeVariadicOperands($values);
		return new CSSMathProduct([$this, ...$operands]);
	}

	public function div(CSSNumericValue|int|float ...$values): CSSMathProduct
	{
		$operands = self::normalizeVariadicOperands($values);
		return new CSSMathProduct([$this, ...array_map(static fn (self $value): CSSMathInvert => new CSSMathInvert([$value]), $operands)]);
	}

	public function min(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMin
	{
		$operands = self::flattenNumericArguments($values);
		self::assertHasOperands($operands);
		$all = [$this, ...$operands];
		self::mergeAdditiveTypes($all);

		return new CSSMathMin($all);
	}

	public function max(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMax
	{
		$operands = self::flattenNumericArguments($values);
		self::assertHasOperands($operands);
		$all = [$this, ...$operands];
		self::mergeAdditiveTypes($all);

		return new CSSMathMax($all);
	}

	public function equals(self ...$numericValues): bool
	{
		foreach ($numericValues as $numericValue) {
			if (!$this->equalsOne($numericValue)) {
				return false;
			}
		}
		return true;
	}

	public function __get(string $name): mixed
	{
		if ($name === 'type') {
			return $this->getNumericType();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	protected static function toNumericValue(self|int|float $value): self
	{
		if ($value instanceof self) {
			return $value;
		}
		if (!is_finite((float) $value)) {
			throw new InvalidArgumentException('CSS numeric operand must be finite.');
		}

		return new CSSUnitValue((float) $value, 'number');
	}

	/**
	 * Merges additive/comparison operand types using Typed OM percentHint rules.
	 *
	 * @param list<self> $values
	 * @throws InvalidArgumentException When operands have incompatible dimensions
	 */
	public static function mergeAdditiveTypes(array $values): CSSNumericType
	{
		if ($values === []) {
			return CSSNumericType::fromDimensions([]);
		}

		$merged = $values[0]->getNumericType();
		foreach (array_slice($values, 1) as $value) {
			$merged = self::addTwoTypes($merged, $value->getNumericType());
		}

		return $merged;
	}

	/**
	 * @return list<self>
	 */
	protected function _getCurrentValues(): array
	{
		return property_exists($this, 'values') && $this->values instanceof CSSNumericArray
			? $this->values->values
			: [$this];
	}

	private function equalsOne(self $numericValue): bool
	{
		if ($this instanceof CSSUnitValue && $numericValue instanceof CSSUnitValue) {
			if ($this->unit === $numericValue->unit) {
				return self::floatsNearlyEqual($this->value, $numericValue->value);
			}
			if ($this->unitType() === $numericValue->unitType()) {
				try {
					$converted = $numericValue->to($this->unit);
					return $converted instanceof CSSUnitValue && self::floatsNearlyEqual($this->value, $converted->value);
				} catch (\Throwable) {
					return false;
				}
			}
			return false;
		}

		if ($this::class === $numericValue::class) {
			$thisValues = $this->_getCurrentValues();
			$otherValues = $numericValue->_getCurrentValues();
			if (count($thisValues) === count($otherValues)) {
				foreach ($thisValues as $index => $value) {
					if (!$value->equals($otherValues[$index])) {
						return false;
					}
				}
				return true;
			}
		}

		return false;
	}

	private static function floatsNearlyEqual(float $left, float $right): bool
	{
		if (!is_finite($left) || !is_finite($right)) {
			return false;
		}
		$tolerance = max(1e-9, 1e-6 * max(abs($left), abs($right)));
		return abs($left - $right) <= $tolerance;
	}

	private static function addTwoTypes(CSSNumericType $left, CSSNumericType $right): CSSNumericType
	{
		if ($left->equals($right)) {
			return $left;
		}

		$leftBase = self::stripPercentHint($left);
		$rightBase = self::stripPercentHint($right);
		$percentOnly = CSSNumericType::fromDimensions(['percent' => 1]);

		if ($leftBase->equals($percentOnly) && $right->percentHint === null && self::isSingleDimension($right, $hint)) {
			return self::withPercentHint($right, $hint);
		}
		if ($rightBase->equals($percentOnly) && $left->percentHint === null && self::isSingleDimension($left, $hint)) {
			return self::withPercentHint($left, $hint);
		}

		// Combining e.g. ['length' => 1, 'percentHint' => 'length'] with ['length' => 1]
		if (($left->percentHint !== null && $right->percentHint === null && $leftBase->equals($rightBase))
			|| ($right->percentHint !== null && $left->percentHint === null && $leftBase->equals($rightBase))) {
			return $left->percentHint !== null ? $left : $right;
		}

		throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
	}

	private static function stripPercentHint(CSSNumericType $type): CSSNumericType
	{
		$dimensions = [];
		foreach ($type->toArray() as $key => $value) {
			if ($key !== 'percentHint') {
				$dimensions[$key] = $value;
			}
		}
		return CSSNumericType::fromDimensions($dimensions);
	}

	private static function isSingleDimension(CSSNumericType $type, ?string &$dimensionName): bool
	{
		$dimensionName = null;
		foreach ($type->toArray() as $key => $exponent) {
			if ($key === 'percentHint' || (int) $exponent === 0) {
				continue;
			}
			if ($dimensionName !== null || (int) $exponent !== 1 || $key === 'percent') {
				return false;
			}
			$dimensionName = $key;
		}
		return $dimensionName !== null;
	}

	private static function withPercentHint(CSSNumericType $type, string $hint): CSSNumericType
	{
		$dimensions = $type->toArray();
		$dimensions['percentHint'] = $hint;
		return CSSNumericType::fromDimensions($dimensions);
	}

	private static function convertToPx(float $value, string $unit, CSSContext $context): float
	{
		return $value * $context->getToPxFactor($unit);
	}

	private static function convertFromPx(float $pxValue, string $unit, CSSContext $context): float
	{
		return $pxValue * $context->getFromPxFactor($unit);
	}

	private static function resolveUnit(string $unit): CSSUnitEnum
	{
		$resolved = CSSUnitEnum::tryFromUnitName($unit);
		if ($resolved === null) {
			throw new InvalidArgumentException('Unsupported CSS unit: ' . $unit);
		}

		return $resolved;
	}

	private static function convertNumericValue(self $value, CSSUnitEnum $targetUnit, CSSContext $context): self
	{
		if ($value instanceof CSSUnitValue) {
			$sourceUnit = $value->unitObj;
			if ($sourceUnit === null) {
				throw new InvalidArgumentException('The numeric value has no unit.');
			}
			if ($sourceUnit === $targetUnit) {
				return new CSSUnitValue($value->value, $targetUnit);
			}
			$category = $sourceUnit->type()?->value;
			if ($category === CSSUnitTypeEnum::LENGTH->value) {
				$base = self::convertToPx($value->value, $sourceUnit->toString(), $context);
				return new CSSUnitValue(self::convertFromPx($base, $targetUnit->toString(), $context), $targetUnit);
			}

			$categoryFactors = self::DIMENSION_FACTORS[$category ?? ''] ?? [];
			$from = $categoryFactors[strtolower($sourceUnit->toString())] ?? null;
			$to = $categoryFactors[strtolower($targetUnit->toString())] ?? null;
			if ($from === null || $to === null) {
				throw new InvalidArgumentException('Unit conversion is not supported for these units.');
			}
			return new CSSUnitValue($value->value * $from / $to, $targetUnit);
		}

		if ($value instanceof CSSMathNegate) {
			return new CSSMathNegate([self::convertNumericValue($value->values->values[0], $targetUnit, $context)]);
		}
		if ($value instanceof CSSMathInvert) {
			throw new InvalidArgumentException('Cannot convert an inverted numeric value to a direct unit.');
		}
		if ($value instanceof CSSMathProduct) {
			$values = $value->values->values;
			$dimensionalIndexes = [];
			foreach ($values as $index => $operand) {
				if ($operand->getNumericCategory() !== 'number') {
					$dimensionalIndexes[] = $index;
				}
			}
			if (count($dimensionalIndexes) !== 1) {
				throw new InvalidArgumentException('Only products with a single unit-bearing operand can be converted.');
			}
			$index = $dimensionalIndexes[0];
			$values[$index] = self::convertNumericValue($values[$index], $targetUnit, $context);
			return new CSSMathProduct($values);
		}

		$converted = array_map(
			static fn (self $operand): self => self::convertNumericValue($operand, $targetUnit, $context),
			$value->values->values,
		);
		return match (true) {
			$value instanceof CSSMathSum => new CSSMathSum($converted),
			$value instanceof CSSMathMin => new CSSMathMin($converted),
			$value instanceof CSSMathMax => new CSSMathMax($converted),
			$value instanceof CSSMathClamp => new CSSMathClamp($converted[0], $converted[1], $converted[2]),
			default => throw new InvalidArgumentException('Unsupported numeric expression conversion.'),
		};
	}

	/** @param list<self|int|float> $values @return list<self> */
	private static function normalizeVariadicOperands(array $values): array
	{
		if ($values === []) {
			throw new InvalidArgumentException('At least one numeric operand is required.');
		}

		return array_map(static fn (self|int|float $value): self => self::toNumericValue($value), $values);
	}

	/** @param list<self> $values */
	private static function assertHasOperands(array $values): void
	{
		if ($values === []) {
			throw new InvalidArgumentException('At least one numeric operand is required.');
		}
	}

	/** @param list<CSSNumericArray|self|int|float> $values @return list<self> */
	private static function flattenNumericArguments(array $values): array
	{
		$result = [];
		foreach ($values as $value) {
			if ($value instanceof CSSNumericArray) {
				array_push($result, ...$value->values);
			} else {
				$result[] = self::toNumericValue($value);
			}
		}

		return $result;
	}

	/** @return list<self> */
	private static function sumOperands(self $value): array
	{
		return $value instanceof CSSMathSum ? $value->values->values : [$value];
	}
}
