<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Error;
use Exception;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
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
use ReflectionClass;

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

	public const int MAX_MATH_NODES = 100_000;
	protected const int MAX_MATH_TREE_DEPTH = 512;

	protected ?CSSNumericType $cachedNumericType = null;

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

		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($propertyOrCssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS numeric value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}
		if (CSSSyntaxScanner::hasControlCharacters($propertyOrCssText)) {
			throw new InvalidArgumentException('CSS numeric value contains disallowed control characters.');
		}

		$cssText = CSSSyntaxScanner::trimCssWhitespace($propertyOrCssText);
		if ($cssText === '') {
			throw new InvalidArgumentException('Invalid CSS numeric value.');
		}

		$parsed = self::tryParseTrimmed($cssText, $options, $depth, true);
		if ($parsed !== null) {
			return $parsed;
		}

		throw new InvalidArgumentException('Invalid CSS numeric value: ' . $cssText);
	}

	/**
	 * @internal Attempts to parse a standalone trimmed numeric/math CSS expression without throwing on non-numeric syntax.
	 * @throws CSSParserLimitException When resource limits are exceeded
	 */
	public static function tryParse(string $cssText, ?CSSParserOptions $options = null, int $depth = 0): ?self
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS numeric value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}
		if (CSSSyntaxScanner::hasControlCharacters($cssText)) {
			return null;
		}

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($cssText);
		if ($trimmed === '') {
			return null;
		}

		return self::tryParseTrimmed($trimmed, $options, $depth, false);
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
		return clone $this->getNumericTypeInternal();
	}

	/**
	 * @internal Iteratively computes CSSNumericType without deep PHP call recursion.
	 */
	public function getNumericTypeInternal(): CSSNumericType
	{
		if ($this instanceof CSSUnitValue) {
			return self::baseTypeForUnitCategory($this->unitObj?->type()?->value);
		}
		if (!$this instanceof CSSMathValue) {
			return self::baseTypeForUnitCategory(null);
		}
		if ($this->cachedNumericType !== null) {
			return $this->cachedNumericType;
		}

		$visits = 0;
		$stack = [[0, $this, 0]];
		while ($stack !== []) {
			[$phase, $node, $depth] = array_pop($stack);
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (!$node instanceof CSSMathValue) {
				continue;
			}
			if ($node->cachedNumericType !== null) {
				continue;
			}
			if ($phase === 0) {
				if (++$visits > self::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
				}
				$children = $node->values->values;
				if ($node instanceof CSSMathNegate) {
					if (!isset($children[0])) {
						throw new InvalidArgumentException('Negation requires a numeric operand.');
					}
				} elseif ($node instanceof CSSMathInvert) {
					if (!isset($children[0])) {
						throw new InvalidArgumentException('Inversion requires a numeric operand.');
					}
				}
				$stack[] = [1, $node, $depth];
				for ($i = count($children) - 1; $i >= 0; $i--) {
					$child = $children[$i];
					if ($child instanceof CSSMathValue && $child->cachedNumericType === null) {
						$stack[] = [0, $child, $depth + 1];
					}
				}
				continue;
			}

			$children = $node->values->values;
			if ($node instanceof CSSMathNegate) {
				$node->cachedNumericType = $children[0]->getNumericTypeInternal();
			} elseif ($node instanceof CSSMathInvert) {
				$node->cachedNumericType = $children[0]->getNumericTypeInternal()->invert();
			} elseif ($node instanceof CSSMathProduct) {
				$type = self::baseTypeForUnitCategory(null);
				foreach ($children as $child) {
					$type = $type->multiply($child->getNumericTypeInternal());
				}
				$node->cachedNumericType = $type;
			} else {
				$node->cachedNumericType = self::mergeAdditiveTypes($children);
			}
		}

		return $this->cachedNumericType ?? self::baseTypeForUnitCategory(null);
	}

	public function getNumericCategory(): string
	{
		$type = $this->getNumericTypeInternal();
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
		$visits = 0;
		return $this->toWithVisits($this, self::resolveUnit($unit), $context, $visits);
	}

	/**
	 * Converts and groups this value into a CSSMathSum over the requested units while preserving total value.
	 *
	 * @throws InvalidArgumentException When any requested unit is incompatible
	 */
	public function toSum(string ...$units): CSSMathSum
	{
		if ($units === []) {
			return new CSSMathSum([$this]);
		}

		$buckets = [];
		$seenUnits = [];
		foreach ($units as $unit) {
			$resolved = self::resolveUnit($unit);
			if (isset($seenUnits[$resolved->value])) {
				continue;
			}
			$seenUnits[$resolved->value] = true;
			$buckets[] = [
				'unit' => $resolved,
				'category' => $resolved->type()?->value ?? 'number',
				'value' => 0.0,
			];
		}

		$visits = 0;
		$terms = [];
		self::collectAdditiveTerms($this, 1.0, $terms, $visits);
		$context = null;

		foreach ($terms as [$sign, $term]) {
			$targetIdx = null;
			if ($term instanceof CSSUnitValue) {
				foreach ($buckets as $idx => $bucket) {
					if ($bucket['unit'] === $term->unitObj) {
						$targetIdx = $idx;
						break;
					}
				}
			}

			if ($targetIdx === null) {
				$termCategory = $term->getNumericCategory();
				foreach ($buckets as $idx => $bucket) {
					if ($bucket['category'] === $termCategory) {
						$targetIdx = $idx;
						break;
					}
				}
			}

			if ($targetIdx === null) {
				// Force standard incompatible conversion error
				$this->toWithVisits($term, $buckets[0]['unit'], $context, $visits);
				throw new InvalidArgumentException('Cannot convert numeric value into requested toSum() units.');
			}

			$targetUnit = $buckets[$targetIdx]['unit'];
			if ($targetUnit->type() === CSSUnitTypeEnum::LENGTH && $context === null) {
				$context = new CSSContext();
			}

			$converted = $this->toWithVisits($term, $targetUnit, $context, $visits);
			if ($converted instanceof CSSUnitValue) {
				$termValue = $converted->value;
			} else {
				$evaluated = self::evaluateConcreteTerm($converted, $visits);
				if ($evaluated === null) {
					throw new InvalidArgumentException('Cannot sum non-unit expression in toSum().');
				}
				$termValue = $evaluated;
			}
			$buckets[$targetIdx]['value'] += $sign * $termValue;
		}

		$result = [];
		foreach ($buckets as $bucket) {
			$result[] = new CSSUnitValue($bucket['value'], $bucket['unit']);
		}

		return new CSSMathSum($result);
	}

	public function add(CSSNumericValue|int|float ...$values): self
	{
		$operands = self::normalizeVariadicOperands($values);
		$existing = self::sumOperands($this);
		if (count($existing) + count($operands) > self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}
		$all = [...$existing, ...$operands];
		$merged = self::mergeAdditiveTypes($all);

		return CSSMathSum::fromValidated($all, $merged);
	}

	public function sub(CSSNumericValue|int|float ...$values): self
	{
		$operands = self::normalizeVariadicOperands($values);
		$existing = self::sumOperands($this);
		if (count($existing) + (2 * count($operands)) > self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}
		$negated = [];
		foreach ($operands as $value) {
			$negated[] = new CSSMathNegate([$value]);
		}
		$all = [...$existing, ...$negated];
		$merged = self::mergeAdditiveTypes($all);

		return CSSMathSum::fromValidated($all, $merged);
	}

	public function mul(CSSNumericValue|int|float ...$values): CSSMathProduct
	{
		$operands = self::normalizeVariadicOperands($values);
		if (1 + count($operands) > self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}
		return new CSSMathProduct([$this, ...$operands]);
	}

	public function div(CSSNumericValue|int|float ...$values): CSSMathProduct
	{
		$operands = self::normalizeVariadicOperands($values);
		if (1 + (2 * count($operands)) > self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}
		$inverted = [];
		foreach ($operands as $value) {
			$inverted[] = new CSSMathInvert([$value]);
		}
		return new CSSMathProduct([$this, ...$inverted]);
	}

	/**
	 * @internal Tree-clones a numeric value iteratively without memoization so mutable unit leaves never alias.
	 */
	public static function cloneTreeIteratively(self $root, int &$visits = 0, int $baseDepth = 0): self
	{
		if ($baseDepth > self::MAX_MATH_TREE_DEPTH) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
		}
		if ($root instanceof CSSUnitValue) {
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}
			return new CSSUnitValue($root->value, $root->unitObj);
		}
		if (!$root instanceof CSSMathValue) {
			return clone $root;
		}

		static $reflections = [];
		$slots = [];
		$nextSlot = 1;
		$stack = [[0, $root, $baseDepth, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 1) {
				[, $orig, $childSlots, $targetSlot] = $frame;
				$newChildren = [];
				foreach ($childSlots as $cs) {
					$newChildren[] = $slots[$cs];
					unset($slots[$cs]);
				}
				$cls = $orig::class;
				$refl = $reflections[$cls] ??= new ReflectionClass($cls);
				/** @var CSSMathValue $copy */
				$copy = $refl->newInstanceWithoutConstructor();
				$copy->values = new CSSNumericArray($newChildren);
				$copy->cachedNumericType = $orig->cachedNumericType !== null ? clone $orig->cachedNumericType : null;
				if ($copy instanceof CSSMathNegate || $copy instanceof CSSMathInvert) {
					$copy->value = $newChildren[0] ?? null;
				} elseif ($copy instanceof CSSMathClamp) {
					$copy->value = $newChildren[1] ?? null;
				}
				$slots[$targetSlot] = $copy;
				continue;
			}

			[, $node, $depth, $targetSlot] = $frame;
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}
			if ($node instanceof CSSUnitValue) {
				$slots[$targetSlot] = new CSSUnitValue($node->value, $node->unitObj);
				continue;
			}
			if (!$node instanceof CSSMathValue) {
				$slots[$targetSlot] = clone $node;
				continue;
			}

			$children = $node->values->values;
			$childSlots = [];
			foreach ($children as $_) {
				$childSlots[] = $nextSlot++;
			}
			$stack[] = [1, $node, $childSlots, $targetSlot];
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = [0, $children[$i], $depth + 1, $childSlots[$i]];
			}
		}

		return $slots[0];
	}

	public function min(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMin
	{
		$operands = self::flattenNumericArguments($values, self::MAX_MATH_NODES - 1);
		self::assertHasOperands($operands);
		$all = [$this, ...$operands];
		$merged = self::mergeAdditiveTypes($all);

		return CSSMathMin::fromValidated($all, $merged);
	}

	public function max(CSSNumericArray|CSSNumericValue|int|float ...$values): CSSMathMax
	{
		$operands = self::flattenNumericArguments($values, self::MAX_MATH_NODES - 1);
		self::assertHasOperands($operands);
		$all = [$this, ...$operands];
		$merged = self::mergeAdditiveTypes($all);

		return CSSMathMax::fromValidated($all, $merged);
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

	public function __isset(string $name): bool
	{
		return $name === 'type';
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
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
			return self::baseTypeForUnitCategory(null);
		}

		$merged = $values[0]->getNumericTypeInternal();
		$count = count($values);
		for ($i = 1; $i < $count; $i++) {
			$merged = self::combineAdditiveTypes($merged, $values[$i]->getNumericTypeInternal());
		}

		return $merged;
	}

	/**
	 * @internal Combines two numeric types additively per W3C Typed OM §4.1.2 without redundant allocations when equal.
	 * @throws InvalidArgumentException When dimensions are incompatible
	 */
	public static function combineAdditiveTypes(CSSNumericType $left, CSSNumericType $right): CSSNumericType
	{
		if ($left->equals($right)) {
			return $left;
		}

		$leftHint = $left->percentHint;
		$rightHint = $right->percentHint;

		if ($leftHint !== null && $rightHint !== null) {
			throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
		}
		if ($leftHint !== null) {
			$applied = $right->applyPercentHint((string) $leftHint);
			if ($left->equals($applied)) {
				return $left;
			}
			throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
		}
		if ($rightHint !== null) {
			$applied = $left->applyPercentHint((string) $rightHint);
			if ($right->equals($applied)) {
				return $right;
			}
			throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
		}

		$leftMap = $left->toArray();
		$rightMap = $right->toArray();
		if (((int) ($leftMap['percent'] ?? 0) === 0) && ((int) ($rightMap['percent'] ?? 0) === 0)) {
			throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
		}

		foreach (['length', 'angle', 'time', 'frequency', 'resolution', 'flex'] as $candidate) {
			if ((int) ($leftMap[$candidate] ?? 0) !== (int) ($rightMap[$candidate] ?? 0)) {
				$lApplied = $left->applyPercentHint($candidate);
				$rApplied = $right->applyPercentHint($candidate);
				if ($lApplied->equals($rApplied)) {
					return $lApplied;
				}
				break;
			}
		}

		throw new InvalidArgumentException('CSS math operands have incompatible numeric types.');
	}

	protected static function toNumericValue(self|int|float $value): self
	{
		if ($value instanceof self) {
			return $value;
		}
		if (!is_finite((float) $value)) {
			throw new InvalidArgumentException('CSS numeric operand must be finite.');
		}

		return new CSSUnitValue((float) $value, CSSUnitEnum::NUMBER);
	}

	/**
	 * @return list<self>
	 */
	protected function _getCurrentValues(): array
	{
		return $this instanceof CSSMathValue ? $this->values->values : [$this];
	}

	/**
	 * @internal Fast path for already limit-checked, control-byte-free, whitespace-trimmed strings.
	 * @throws CSSParserLimitException When resource limits are exceeded
	 */
	public static function tryParseValidated(string $trimmedCssText, ?CSSParserOptions $options = null, int $depth = 0): ?self
	{
		if ($trimmedCssText === '') {
			return null;
		}
		$options ??= CSSParserOptions::default();
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}

		return self::tryParseTrimmed($trimmedCssText, $options, $depth, false);
	}

	private static function tryParseTrimmed(string $cssText, CSSParserOptions $options, int $depth, bool $throwOnInvalid): ?self
	{
		if ($cssText !== '') {
			$c0 = $cssText[0];
			if ((($c0 >= '0' && $c0 <= '9') || $c0 === '.' || $c0 === '+' || $c0 === '-')
				&& !str_contains($cssText, '(')
			) {
				$len = strlen($cssText);
				if (preg_match('/\G([+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?)([a-zA-Z%]*)/', $cssText, $matches) === 1
					&& strlen($matches[0]) === $len
				) {
					$value = (float) $matches[1];
					if (!is_finite($value)) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('CSS numeric value must be finite: ' . $cssText);
						}
						return null;
					}
					$unit = CSSUnitEnum::tryFromSymbol($matches[2]);
					if ($unit === null) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Unsupported CSS unit: ' . $matches[2]);
						}
						return null;
					}
					return new CSSUnitValue($value, $unit);
				}
				if ($throwOnInvalid) {
					throw new InvalidArgumentException('Invalid CSS numeric value: ' . $cssText);
				}
				return null;
			}
		}

		return CSSCalcParser::parseUnitOrMathSlice($cssText, 0, strlen($cssText), $options, $depth, $throwOnInvalid);
	}

	private static function baseTypeForUnitCategory(?string $category): CSSNumericType
	{
		static $cache = [];
		$key = $category ?? '';

		return $cache[$key] ??= CSSNumericType::fromValidatedDimensions(
			$key === '' ? [] : [$key => 1],
		);
	}

	/** @param list<array{float, CSSNumericValue}> $terms */
	private static function collectAdditiveTerms(self $root, float $rootSign, array &$terms, int &$visits = 0): void
	{
		$stack = [[$root, $rootSign, 0]];
		while ($stack !== []) {
			[$value, $sign, $depth] = array_pop($stack);
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}
			if ($value instanceof CSSMathNegate) {
				$inner = $value->values->values[0] ?? null;
				if ($inner !== null) {
					$stack[] = [$inner, -$sign, $depth + 1];
				}
				continue;
			}
			if ($value instanceof CSSMathSum) {
				$children = $value->values->values;
				for ($i = count($children) - 1; $i >= 0; $i--) {
					$stack[] = [$children[$i], $sign, $depth + 1];
				}
				continue;
			}

			$terms[] = [$sign, $value];
		}
	}

	private function equalsOne(self $numericValue): bool
	{
		if ($this === $numericValue) {
			return true;
		}

		$visits = 0;
		$stack = [[$this, $numericValue, 0]];
		while ($stack !== []) {
			[$left, $right, $depth] = array_pop($stack);
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}
			if ($left === $right) {
				continue;
			}
			if ($left instanceof CSSUnitValue && $right instanceof CSSUnitValue) {
				if ($left->unitObj === $right->unitObj) {
					if (!self::floatsNearlyEqual($left->value, $right->value)) {
						return false;
					}
					continue;
				}
				if ($left->unitType() === $right->unitType()) {
					try {
						$converted = $right->to($left->unit);
						if (!$converted instanceof CSSUnitValue || !self::floatsNearlyEqual($left->value, $converted->value)) {
							return false;
						}
						continue;
					} catch (Exception) {
						return false;
					}
				}
				return false;
			}

			if ($left::class !== $right::class) {
				return false;
			}
			$leftValues = $left->_getCurrentValues();
			$rightValues = $right->_getCurrentValues();
			if (count($leftValues) !== count($rightValues)) {
				return false;
			}
			for ($i = count($leftValues) - 1; $i >= 0; $i--) {
				$stack[] = [$leftValues[$i], $rightValues[$i], $depth + 1];
			}
		}

		return true;
	}

	private static function floatsNearlyEqual(float $left, float $right): bool
	{
		if (!is_finite($left) || !is_finite($right)) {
			return false;
		}
		$tolerance = max(1e-9, 1e-6 * max(abs($left), abs($right)));
		return abs($left - $right) <= $tolerance;
	}

	private static function resolveUnit(string $unit): CSSUnitEnum
	{
		$resolved = CSSUnitEnum::tryFromUnitName($unit);
		if ($resolved === null) {
			throw new InvalidArgumentException('Unsupported CSS unit: ' . $unit);
		}

		return $resolved;
	}

	private function toWithVisits(self $term, CSSUnitEnum $targetUnit, ?CSSContext $context, int &$visits): self
	{
		$currentType = $term->getNumericTypeInternal();
		$targetDimension = $targetUnit->type()?->value;
		$expectedType = self::baseTypeForUnitCategory($targetDimension);

		if ($currentType->percentHint !== null) {
			throw new InvalidArgumentException('Cannot convert a mixed percentage expression directly to a single unit.');
		}
		if (!$currentType->equals($expectedType)) {
			throw new InvalidArgumentException(sprintf(
				'Cannot convert %s to %s.',
				$term->getNumericCategory(),
				$targetDimension ?? 'number',
			));
		}

		if ($targetDimension === CSSUnitTypeEnum::LENGTH->value) {
			$context ??= new CSSContext();
		}

		return self::convertNumericValue($term, $targetUnit, $context, $visits);
	}

	private static function convertNumericValue(self $root, CSSUnitEnum $targetUnit, ?CSSContext $context, int &$visits = 0): self
	{
		$slots = [];
		$nextSlot = 1;
		$stack = [[0, $root, 0, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 1) {
				[, $orig, $childSlots, $targetSlot] = $frame;
				$converted = [];
				foreach ($childSlots as $cs) {
					$converted[] = $slots[$cs];
					unset($slots[$cs]);
				}
				$slots[$targetSlot] = match (true) {
					$orig instanceof CSSMathNegate => new CSSMathNegate([$converted[0]]),
					$orig instanceof CSSMathSum => new CSSMathSum($converted),
					$orig instanceof CSSMathMin => new CSSMathMin($converted),
					$orig instanceof CSSMathMax => new CSSMathMax($converted),
					$orig instanceof CSSMathClamp => new CSSMathClamp($converted[0], $converted[1], $converted[2]),
					default => throw new InvalidArgumentException('Unsupported numeric expression conversion.'),
				};
				continue;
			}

			if ($frame[0] === 2) {
				[, $origProduct, $dimIndex, $dimSlot, $depth, $targetSlot] = $frame;
				$values = $origProduct->values->values;
				$newValues = [];
				foreach ($values as $idx => $operand) {
					if ($idx === $dimIndex && $dimSlot !== null) {
						$newValues[] = $slots[$dimSlot];
						unset($slots[$dimSlot]);
					} else {
						$newValues[] = self::cloneTreeIteratively($operand, $visits, $depth + 1);
					}
					if ($visits > self::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
					}
				}
				$slots[$targetSlot] = new CSSMathProduct($newValues);
				continue;
			}

			[, $value, $depth, $targetSlot] = $frame;
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}

			if ($value instanceof CSSUnitValue) {
				$sourceUnit = $value->unitObj;
				if ($sourceUnit === null) {
					throw new InvalidArgumentException('The numeric value has no unit.');
				}
				if ($sourceUnit === $targetUnit) {
					$slots[$targetSlot] = new CSSUnitValue($value->value, $targetUnit);
					continue;
				}
				$category = $sourceUnit->type()?->value;
				if ($category === CSSUnitTypeEnum::LENGTH->value) {
					$context ??= new CSSContext();
					$base = $value->value * $context->getToPxFactor($sourceUnit->toString());
					$slots[$targetSlot] = new CSSUnitValue($base * $context->getFromPxFactor($targetUnit->toString()), $targetUnit);
					continue;
				}

				$categoryFactors = self::DIMENSION_FACTORS[$category ?? ''] ?? [];
				$from = $categoryFactors[strtolower($sourceUnit->toString())] ?? null;
				$to = $categoryFactors[strtolower($targetUnit->toString())] ?? null;
				if ($from === null || $to === null) {
					throw new InvalidArgumentException('Unit conversion is not supported for these units.');
				}
				$slots[$targetSlot] = new CSSUnitValue($value->value * $from / $to, $targetUnit);
				continue;
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
				if ($dimensionalIndexes === []) {
					if ($targetUnit !== CSSUnitEnum::NUMBER) {
						throw new InvalidArgumentException('Unit conversion is not supported for these units.');
					}
					$stack[] = [2, $value, -1, null, $depth, $targetSlot];
					continue;
				}
				if (count($dimensionalIndexes) !== 1) {
					throw new InvalidArgumentException('Only products with a single unit-bearing operand can be converted.');
				}
				$dimIdx = $dimensionalIndexes[0];
				$dimSlot = $nextSlot++;
				$stack[] = [2, $value, $dimIdx, $dimSlot, $depth, $targetSlot];
				$stack[] = [0, $values[$dimIdx], $depth + 1, $dimSlot];
				continue;
			}

			$children = $value->values->values;
			$childSlots = [];
			foreach ($children as $_) {
				$childSlots[] = $nextSlot++;
			}
			$stack[] = [1, $value, $childSlots, $targetSlot];
			for ($i = count($children) - 1; $i >= 0; $i--) {
				$stack[] = [0, $children[$i], $depth + 1, $childSlots[$i]];
			}
		}

		return $slots[0];
	}

	private static function evaluateConcreteTerm(self $root, int &$visits = 0): ?float
	{
		$slots = [];
		$nextSlot = 1;
		// Phase 0 = VISIT(node, numberOnly, depth, slotIdx)
		// Phase 1 = REDUCE(node, numberOnly, childSlots, slotIdx)
		$stack = [[0, $root, false, 0, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 1) {
				[, $node, $numberOnly, $childSlots, $targetSlot] = $frame;
				$childVals = [];
				foreach ($childSlots as $cs) {
					$v = $slots[$cs] ?? null;
					unset($slots[$cs]);
					if ($v === null) {
						return null;
					}
					$childVals[] = $v;
				}
				if ($node instanceof CSSMathNegate) {
					$slots[$targetSlot] = -$childVals[0];
					continue;
				}
				if ($node instanceof CSSMathInvert) {
					if ($childVals[0] === 0.0) {
						throw new InvalidArgumentException('Division by zero in numeric expression.');
					}
					$inv = 1.0 / $childVals[0];
					if (!is_finite($inv)) {
						throw new InvalidArgumentException('Numeric inversion overflows finite precision.');
					}
					$slots[$targetSlot] = $inv;
					continue;
				}
				if ($node instanceof CSSMathSum) {
					$sum = 0.0;
					foreach ($childVals as $cv) {
						$sum += $cv;
					}
					if (!is_finite($sum)) {
						throw new InvalidArgumentException('Numeric sum overflows finite precision.');
					}
					$slots[$targetSlot] = $sum;
					continue;
				}
				if ($node instanceof CSSMathProduct) {
					$prod = 1.0;
					foreach ($childVals as $cv) {
						$prod *= $cv;
					}
					if (!is_finite($prod)) {
						throw new InvalidArgumentException('Numeric product overflows finite precision.');
					}
					$slots[$targetSlot] = $prod;
					continue;
				}
				return null;
			}

			[, $node, $numberOnly, $depth, $targetSlot] = $frame;
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}

			if ($node instanceof CSSUnitValue) {
				if ($numberOnly && $node->unit !== 'number') {
					return null;
				}
				$slots[$targetSlot] = $node->value;
				continue;
			}

			if ($node instanceof CSSMathNegate || $node instanceof CSSMathSum) {
				$children = $node->values->values;
				if ($node instanceof CSSMathNegate && count($children) !== 1) {
					return null;
				}
				$childSlots = [];
				foreach ($children as $_) {
					$childSlots[] = $nextSlot++;
				}
				$stack[] = [1, $node, $numberOnly, $childSlots, $targetSlot];
				for ($i = count($children) - 1; $i >= 0; $i--) {
					$stack[] = [0, $children[$i], $numberOnly, $depth + 1, $childSlots[$i]];
				}
				continue;
			}

			if ($node instanceof CSSMathInvert) {
				if (!$numberOnly || count($node->values->values) !== 1) {
					return null;
				}
				$cs = $nextSlot++;
				$stack[] = [1, $node, true, [$cs], $targetSlot];
				$stack[] = [0, $node->values->values[0], true, $depth + 1, $cs];
				continue;
			}

			if ($node instanceof CSSMathProduct) {
				$children = $node->values->values;
				$childSlots = [];
				$seenDimensional = false;
				foreach ($children as $child) {
					if (!$numberOnly && $child->getNumericCategory() !== 'number') {
						if ($seenDimensional) {
							return null;
						}
						$seenDimensional = true;
						$childSlots[] = [$nextSlot++, false];
					} else {
						$childSlots[] = [$nextSlot++, true];
					}
				}
				$rawSlots = array_column($childSlots, 0);
				$stack[] = [1, $node, $numberOnly, $rawSlots, $targetSlot];
				for ($i = count($children) - 1; $i >= 0; $i--) {
					$stack[] = [0, $children[$i], $childSlots[$i][1], $depth + 1, $childSlots[$i][0]];
				}
				continue;
			}

			return null;
		}

		return $slots[0] ?? null;
	}

	/** @param list<self|int|float> $values @return list<self> */
	private static function normalizeVariadicOperands(array $values): array
	{
		if ($values === []) {
			throw new InvalidArgumentException('At least one numeric operand is required.');
		}

		$result = [];
		foreach ($values as $value) {
			$result[] = self::toNumericValue($value);
		}

		return $result;
	}

	/** @param list<self> $values */
	private static function assertHasOperands(array $values): void
	{
		if ($values === []) {
			throw new InvalidArgumentException('At least one numeric operand is required.');
		}
	}

	/** @param list<CSSNumericArray|self|int|float> $values @return list<self> */
	private static function flattenNumericArguments(array $values, int $maxItems = self::MAX_MATH_NODES): array
	{
		$result = [];
		foreach ($values as $value) {
			if ($value instanceof CSSNumericArray) {
				$count = count($value->values);
				if (count($result) + $count > $maxItems) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
				}
				array_push($result, ...$value->values);
			} else {
				if (count($result) >= $maxItems) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
				}
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
