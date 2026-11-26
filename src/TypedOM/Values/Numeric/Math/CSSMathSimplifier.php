<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Iterative CSS Values and Units Level 4 §10.10 calculation-tree simplifier and §10.8 range clamper.
 *
 * Uses an explicit work stack (never unbounded PHP recursion) and enforces both visit and
 * node-allocation budgets against {@see CSSNumericValue::MAX_MATH_NODES}.
 *
 * @see https://drafts.csswg.org/css-values-4/#calc-simplification
 */
final class CSSMathSimplifier
{
	private const int MAX_TREE_DEPTH = 512;

	/** @var array<string, float> */
	private const array ABSOLUTE_LENGTH_TO_PX = [
		'px' => 1.0,
		'in' => 96.0,
		'cm' => 96.0 / 2.54,
		'mm' => 96.0 / 25.4,
		'q' => 96.0 / 101.6,
		'pt' => 96.0 / 72.0,
		'pc' => 16.0,
	];

	/** @var array<string, array{CSSUnitEnum, float}> */
	private const array CANONICAL_CONVERSIONS = [
		'rad' => [CSSUnitEnum::ANGLE_deg, 180.0 / M_PI],
		'grad' => [CSSUnitEnum::ANGLE_deg, 0.9],
		'turn' => [CSSUnitEnum::ANGLE_deg, 360.0],
		'ms' => [CSSUnitEnum::TIME_s, 0.001],
		'khz' => [CSSUnitEnum::FREQ_Hz, 1000.0],
		'dpi' => [CSSUnitEnum::RES_dppx, 1.0 / 96.0],
		'dpcm' => [CSSUnitEnum::RES_dppx, 2.54 / 96.0],
		'x' => [CSSUnitEnum::RES_dppx, 1.0],
	];

	/** @var array<string, CSSUnitEnum> */
	private const array DIMENSION_TO_CANONICAL_UNIT = [
		'length' => CSSUnitEnum::LENGTH_px,
		'angle' => CSSUnitEnum::ANGLE_deg,
		'time' => CSSUnitEnum::TIME_s,
		'frequency' => CSSUnitEnum::FREQ_Hz,
		'resolution' => CSSUnitEnum::RES_dppx,
		'flex' => CSSUnitEnum::FLEX_fr,
		'percent' => CSSUnitEnum::PERCENT,
	];

	/**
	 * Simplifies a CSS numeric value / calculation tree per CSS Values Level 4 §10.10.
	 */
	public static function simplify(CSSNumericValue $root, ?CSSContext $context = null): CSSNumericValue
	{
		$visits = 0;
		$allocated = 0;

		return self::simplifyWithBudget($root, $context, $visits, $allocated);
	}

	/**
	 * @internal Simplifies using caller-owned visit and allocation counters to share MAX_MATH_NODES budgets across subtrees.
	 */
	public static function simplifyWithBudget(
		CSSNumericValue $root,
		?CSSContext $context,
		int &$visits,
		int &$allocated,
	): CSSNumericValue {
		$slots = [];
		$nextSlot = 1;
		// Frame [0 = VISIT, CSSNumericValue, depth, targetSlot]
		// Frame [1 = REDUCE, CSSMathValue, list<int> childSlots, targetSlot]
		$stack = [[0, $root, 0, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 1) {
				[, $node, $childSlots, $targetSlot] = $frame;
				$children = [];
				foreach ($childSlots as $cs) {
					$children[] = $slots[$cs];
					unset($slots[$cs]);
				}
				assert($node instanceof CSSMathValue);
				$slots[$targetSlot] = self::reduceNode($node, $children, $context, $visits, $allocated);
				continue;
			}

			/** @var CSSNumericValue $node */
			$node = $frame[1];
			/** @var int $depth */
			$depth = $frame[2];
			/** @var int $targetSlot */
			$targetSlot = $frame[3];

			if ($depth > self::MAX_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_TREE_DEPTH));
			}
			self::countVisit($visits);

			if ($node instanceof CSSUnitValue) {
				$slots[$targetSlot] = self::canonicalizeUnitValue($node, $context, $allocated);
				continue;
			}
			if ($node instanceof CSSMathConstant) {
				$slots[$targetSlot] = self::simplifyConstant($node, $allocated);
				continue;
			}
			if (!$node instanceof CSSMathValue) {
				$slots[$targetSlot] = $node;
				continue;
			}

			// Preserve raw radian leaf inside sin/cos/tan so rad doesn't suffer rad->deg->rad roundtrip loss.
			if ($node instanceof CSSMathFunction
				&& ($node->function === CSSMathFunctionEnum::SIN || $node->function === CSSMathFunctionEnum::COS || $node->function === CSSMathFunctionEnum::TAN)
			) {
				$arg = $node->values->values[0] ?? null;
				if ($arg instanceof CSSUnitValue && $arg->getUnitObjInternal() === CSSUnitEnum::ANGLE_rad) {
					self::countVisit($visits);
					$slots[$targetSlot] = self::evaluateTrigRadians($node->function, $arg->value, $allocated);
					continue;
				}
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

	/**
	 * Clamps a numeric value to `[min, max]` per CSS Values Level 4 §10.8 degenerate clamping rules.
	 *
	 * @throws InvalidArgumentException When bounds are non-finite or $min > $max
	 */
	public static function clampToRange(
		CSSNumericValue $value,
		?float $min = null,
		?float $max = null,
		?CSSContext $context = null,
	): CSSNumericValue {
		if ($min !== null && !is_finite($min)) {
			throw new InvalidArgumentException('Range clamp minimum must be a finite float.');
		}
		if ($max !== null && !is_finite($max)) {
			throw new InvalidArgumentException('Range clamp maximum must be a finite float.');
		}
		if ($min !== null && $max !== null && $min > $max) {
			throw new InvalidArgumentException('Range clamp minimum cannot exceed maximum.');
		}

		$simplified = self::simplify($value, $context);
		if ($simplified instanceof CSSUnitValue) {
			$clamped = self::clampFloat($simplified->value, $min, $max);

			return new CSSUnitValue($clamped, $simplified->getUnitObjInternal() ?? $simplified->unit);
		}

		if ($simplified instanceof CSSMathConstant) {
			$clamped = self::clampDegenerateFloat($simplified->getFloatValue(), $min, $max);

			return new CSSUnitValue($clamped, CSSUnitEnum::NUMBER);
		}

		// Only collapse single-unit degenerate products (e.g. `calc(infinity * 1px)` / `calc(NaN * 1px)`)
		// with no percentHint; compound/inverted/percentHint degenerates retain their full CSSNumericType.
		if ($simplified instanceof CSSMathProduct && $simplified->getNumericTypeInternal()->percentHint === null) {
			$factors = $simplified->values->values;
			if (count($factors) === 2
				&& $factors[0] instanceof CSSMathConstant
				&& $factors[1] instanceof CSSUnitValue
				&& $factors[1]->value === 1.0
				&& $factors[1]->getUnitObjInternal() !== null
			) {
				$clamped = self::clampDegenerateFloat($factors[0]->getFloatValue(), $min, $max);

				return new CSSUnitValue($clamped, $factors[1]->getUnitObjInternal());
			}
		}

		return $simplified;
	}

	private static function clampFloat(float $value, ?float $min, ?float $max): float
	{
		if ($min !== null && $value < $min) {
			$value = $min;
		}
		if ($max !== null && $value > $max) {
			$value = $max;
		}

		return $value === 0.0 ? 0.0 : $value;
	}

	private static function clampDegenerateFloat(float $value, ?float $min, ?float $max): float
	{
		if (is_nan($value)) {
			return self::clampFloat(0.0, $min, $max);
		}
		if ($value > 0.0) {
			return $max ?? PHP_FLOAT_MAX;
		}
		if ($value < 0.0) {
			return $min ?? -PHP_FLOAT_MAX;
		}

		return self::clampFloat($value, $min, $max);
	}

	private static function simplifyConstant(CSSMathConstant $node, int &$allocated): CSSNumericValue
	{
		if ($node->constant === CSSMathConstantEnum::E) {
			self::countAllocation($allocated);

			return new CSSUnitValue(M_E, CSSUnitEnum::NUMBER);
		}
		if ($node->constant === CSSMathConstantEnum::PI) {
			self::countAllocation($allocated);

			return new CSSUnitValue(M_PI, CSSUnitEnum::NUMBER);
		}

		return $node;
	}

	private static function canonicalizeUnitValue(CSSUnitValue $unitValue, ?CSSContext $context, int &$allocated): CSSNumericValue
	{
		$unitObj = $unitValue->getUnitObjInternal();
		if ($unitObj === null) {
			return $unitValue;
		}

		$lower = strtolower($unitObj->toString());
		if (isset(self::ABSOLUTE_LENGTH_TO_PX[$lower])) {
			$scaled = $unitValue->value * self::ABSOLUTE_LENGTH_TO_PX[$lower];
			if (!is_finite($scaled)) {
				return self::buildDegenerate($scaled, CSSNumericType::fromValidatedDimensions(['length' => 1]), $allocated);
			}
			self::countAllocation($allocated);

			return new CSSUnitValue($scaled, CSSUnitEnum::LENGTH_px);
		}

		if ($context !== null && $unitObj->type() === CSSUnitTypeEnum::LENGTH) {
			$scaled = $unitValue->value * $context->getToPxFactor($lower);
			if (!is_finite($scaled)) {
				return self::buildDegenerate($scaled, CSSNumericType::fromValidatedDimensions(['length' => 1]), $allocated);
			}
			self::countAllocation($allocated);

			return new CSSUnitValue($scaled, CSSUnitEnum::LENGTH_px);
		}

		if (isset(self::CANONICAL_CONVERSIONS[$lower])) {
			[$targetUnit, $factor] = self::CANONICAL_CONVERSIONS[$lower];
			$scaled = $unitValue->value * $factor;
			if (!is_finite($scaled)) {
				$category = $targetUnit->type()?->value;
				$type = CSSNumericType::fromValidatedDimensions($category !== null ? [$category => 1] : []);

				return self::buildDegenerate($scaled, $type, $allocated);
			}
			self::countAllocation($allocated);

			return new CSSUnitValue($scaled, $targetUnit);
		}

		return $unitValue;
	}

	/**
	 * @param list<CSSNumericValue> $children
	 */
	private static function reduceNode(
		CSSMathValue $node,
		array $children,
		?CSSContext $context,
		int &$visits,
		int &$allocated,
	): CSSNumericValue {
		if ($node instanceof CSSMathNegate) {
			return self::simplifyNegate($children[0], $visits, $allocated);
		}
		if ($node instanceof CSSMathInvert) {
			return self::simplifyInvert($children[0], $visits, $allocated);
		}
		if ($node instanceof CSSMathSum) {
			return self::simplifySum($children, $node->getNumericTypeInternal(), $visits, $allocated);
		}
		if ($node instanceof CSSMathProduct) {
			return self::simplifyProduct($children, $node->getNumericTypeInternal(), $visits, $allocated);
		}
		if ($node instanceof CSSMathMin || $node instanceof CSSMathMax) {
			return self::simplifyMinMax($node instanceof CSSMathMin, $children, $node->getNumericTypeInternal(), $visits, $allocated);
		}
		if ($node instanceof CSSMathClamp) {
			return self::simplifyClamp($children[0], $children[1], $children[2], $node->getNumericTypeInternal(), $allocated);
		}
		if ($node instanceof CSSMathFunction) {
			return self::simplifyFunction($node->function, $children, $node->strategy, $node->getNumericTypeInternal(), $context, $visits, $allocated);
		}

		return $node;
	}

	private static function simplifyNegate(CSSNumericValue $child, int &$visits, int &$allocated): CSSNumericValue
	{
		if ($child instanceof CSSUnitValue) {
			self::countAllocation($allocated);

			return new CSSUnitValue(-$child->value, $child->getUnitObjInternal() ?? $child->unit);
		}
		if ($child instanceof CSSMathConstant) {
			self::countAllocation($allocated);

			return match ($child->constant) {
				CSSMathConstantEnum::INFINITY => CSSMathConstant::negInfinity(),
				CSSMathConstantEnum::NEG_INFINITY => CSSMathConstant::infinity(),
				CSSMathConstantEnum::NAN => CSSMathConstant::nan(),
				CSSMathConstantEnum::E => new CSSUnitValue(-M_E, CSSUnitEnum::NUMBER),
				CSSMathConstantEnum::PI => new CSSUnitValue(-M_PI, CSSUnitEnum::NUMBER),
			};
		}
		if ($child instanceof CSSMathNegate) {
			return $child->values->values[0];
		}
		if ($child instanceof CSSMathSum) {
			$negated = [];
			foreach ($child->values->values as $term) {
				self::countVisit($visits);
				$negated[] = self::simplifyNegate($term, $visits, $allocated);
			}

			return self::simplifySum($negated, $child->getNumericTypeInternal(), $visits, $allocated);
		}
		if ($child instanceof CSSMathProduct) {
			$factors = $child->values->values;
			if ($factors !== [] && $factors[0] instanceof CSSMathConstant) {
				self::countVisit($visits);
				$negFirst = self::simplifyNegate($factors[0], $visits, $allocated);
				$factors[0] = $negFirst;

				return self::simplifyProduct($factors, $child->getNumericTypeInternal(), $visits, $allocated);
			}
		}

		self::countAllocation($allocated);

		return new CSSMathNegate([$child]);
	}

	private static function simplifyInvert(CSSNumericValue $child, int &$visits, int &$allocated): CSSNumericValue
	{
		if ($child instanceof CSSUnitValue && $child->getUnitObjInternal() === CSSUnitEnum::NUMBER) {
			$inv = fdiv(1.0, $child->value);

			return self::boxNumber($inv, $allocated);
		}
		if ($child instanceof CSSMathConstant) {
			$inv = fdiv(1.0, $child->getFloatValue());

			return self::boxNumber($inv, $allocated);
		}
		if ($child instanceof CSSMathInvert) {
			return $child->values->values[0];
		}
		if ($child instanceof CSSMathProduct) {
			$invertedFactors = [];
			foreach ($child->values->values as $factor) {
				self::countVisit($visits);
				$invertedFactors[] = self::simplifyInvert($factor, $visits, $allocated);
			}

			return self::simplifyProduct($invertedFactors, $child->getNumericTypeInternal()->invert(), $visits, $allocated);
		}

		self::countAllocation($allocated);

		return new CSSMathInvert([$child]);
	}

	/**
	 * @param list<CSSNumericValue> $children
	 */
	private static function simplifySum(array $children, CSSNumericType $origType, int &$visits, int &$allocated): CSSNumericValue
	{
		$flat = [];
		foreach ($children as $child) {
			if ($child instanceof CSSMathSum) {
				foreach ($child->values->values as $nested) {
					$flat[] = $nested;
				}
			} else {
				$flat[] = $child;
			}
		}

		/** @var array<string, array{unit: CSSUnitEnum, sum: float}> $unitBuckets */
		$unitBuckets = [];
		/** @var array<string, true> $overflowedUnits */
		$overflowedUnits = [];
		/** @var list<CSSNumericValue> $nonLeafTerms */
		$nonLeafTerms = [];
		$hasNaN = false;
		$hasPosInf = false;
		$hasNegInf = false;

		foreach ($flat as $term) {
			self::countVisit($visits);
			$scalar = self::extractScalar($term);
			if ($scalar !== null) {
				[$val, $unit] = $scalar;
				if (is_nan($val)) {
					$hasNaN = true;
					continue;
				}
				if (is_infinite($val)) {
					if ($val > 0.0) {
						$hasPosInf = true;
					} else {
						$hasNegInf = true;
					}
					continue;
				}
				if ($unit !== null) {
					$key = $unit->value;
					if (isset($overflowedUnits[$key])) {
						continue;
					}
					if (!isset($unitBuckets[$key])) {
						$unitBuckets[$key] = ['unit' => $unit, 'sum' => $val];
					} else {
						$sum = $unitBuckets[$key]['sum'] + $val;
						if (is_nan($sum)) {
							$hasNaN = true;
							unset($unitBuckets[$key]);
							$overflowedUnits[$key] = true;
						} elseif (is_infinite($sum)) {
							if ($sum > 0.0) {
								$hasPosInf = true;
							} else {
								$hasNegInf = true;
							}
							unset($unitBuckets[$key]);
							$overflowedUnits[$key] = true;
						} else {
							$unitBuckets[$key]['sum'] = $sum;
						}
					}
					continue;
				}
			}

			$nonLeafTerms[] = $term;
		}

		if ($hasNaN || ($hasPosInf && $hasNegInf)) {
			return self::buildDegenerate(NAN, $origType, $allocated);
		}

		if ($hasPosInf || $hasNegInf) {
			$infVal = $hasPosInf ? INF : -INF;
			if ($nonLeafTerms === []) {
				return self::buildDegenerate($infVal, $origType, $allocated);
			}
			// Preserve unresolved non-leaf terms alongside the infinite term because they might resolve to opposite infinity or NaN.
			$infTerm = self::buildDegenerate($infVal, $origType, $allocated);
			$nonLeafTerms = [$infTerm, ...$nonLeafTerms];
		}

		/** @var list<CSSUnitValue> $combinedLeaves */
		$combinedLeaves = [];
		foreach ($unitBuckets as $bucket) {
			self::countAllocation($allocated);
			$combinedLeaves[] = new CSSUnitValue($bucket['sum'], $bucket['unit']);
		}

		// Drop redundant 0 leaves only when remaining terms exist and preserve the exact CSSNumericType (including percentHint).
		$totalRemaining = count($combinedLeaves) + count($nonLeafTerms);
		if ($totalRemaining > 1) {
			$hasPercentHint = $origType->percentHint !== null;
			$hintedCount = 0;
			$purePercentCount = 0;
			$pureBaseCount = 0;
			if ($hasPercentHint) {
				foreach ($combinedLeaves as $term) {
					if ($term->getUnitObjInternal() === CSSUnitEnum::PERCENT) {
						$purePercentCount++;
					} else {
						$pureBaseCount++;
					}
				}
				foreach ($nonLeafTerms as $term) {
					$termDims = $term->getNumericTypeInternal()->toArray();
					if (isset($termDims['percentHint'])) {
						$hintedCount++;
					} elseif (((int) ($termDims['percent'] ?? 0)) !== 0) {
						$purePercentCount++;
					} else {
						$pureBaseCount++;
					}
				}
			}

			$keptLeaves = [];
			foreach ($combinedLeaves as $leaf) {
				if ($leaf->value !== 0.0 || $totalRemaining <= 1) {
					$keptLeaves[] = $leaf;
					continue;
				}
				if (!$hasPercentHint) {
					$totalRemaining--;
					continue;
				}
				if ($leaf->getUnitObjInternal() === CSSUnitEnum::PERCENT) {
					if ($hintedCount > 0 || $purePercentCount > 1) {
						$purePercentCount--;
						$totalRemaining--;
						continue;
					}
				} elseif ($hintedCount > 0 || $pureBaseCount > 1) {
					$pureBaseCount--;
					$totalRemaining--;
					continue;
				}
				$keptLeaves[] = $leaf;
			}
			$combinedLeaves = $keptLeaves;
		}

		$allTerms = [...$combinedLeaves, ...$nonLeafTerms];
		if ($allTerms === []) {
			return self::buildZeroForType($origType, $allocated);
		}

		// Canonical §10.10.2 sort: number -> percentage -> dimensions ASCII case-insensitively -> non-leaf nodes (stable).
		$indexed = [];
		foreach ($allTerms as $idx => $term) {
			if ($term instanceof CSSUnitValue) {
				$unitObj = $term->getUnitObjInternal();
				if ($unitObj === CSSUnitEnum::NUMBER) {
					$tier = 0;
					$key = '';
				} elseif ($unitObj === CSSUnitEnum::PERCENT) {
					$tier = 1;
					$key = '%';
				} else {
					$tier = 2;
					$key = strtolower($unitObj?->toString() ?? '');
				}
			} else {
				$tier = 3;
				$key = '';
			}
			$indexed[] = [$tier, $key, $idx, $term];
		}
		usort($indexed, static function (array $a, array $b): int {
			return ($a[0] <=> $b[0]) ?: strcmp($a[1], $b[1]) ?: ($a[2] <=> $b[2]);
		});

		$sorted = array_column($indexed, 3);
		if (count($sorted) === 1 && $sorted[0]->getNumericTypeInternal()->equals($origType)) {
			return $sorted[0];
		}

		self::countAllocation($allocated);

		return CSSMathSum::fromValidated($sorted, clone $origType);
	}

	/**
	 * @param list<CSSNumericValue> $children
	 */
	private static function simplifyProduct(array $children, CSSNumericType $origType, int &$visits, int &$allocated): CSSNumericValue
	{
		$flat = [];
		foreach ($children as $child) {
			if ($child instanceof CSSMathProduct) {
				foreach ($child->values->values as $nested) {
					$flat[] = $nested;
				}
			} else {
				$flat[] = $child;
			}
		}

		$k = 1.0;
		/** @var list<CSSUnitEnum> $directUnits */
		$directUnits = [];
		/** @var list<CSSUnitEnum> $invertedUnits */
		$invertedUnits = [];
		/** @var list<CSSNumericValue> $complexFactors */
		$complexFactors = [];

		foreach ($flat as $factor) {
			self::countVisit($visits);
			if ($factor instanceof CSSUnitValue) {
				$u = $factor->getUnitObjInternal() ?? CSSUnitEnum::NUMBER;
				$k *= $factor->value;
				if ($u !== CSSUnitEnum::NUMBER) {
					$directUnits[] = $u;
				}
				continue;
			}
			if ($factor instanceof CSSMathConstant) {
				$k *= $factor->getFloatValue();
				continue;
			}
			if ($factor instanceof CSSMathInvert) {
				$inner = $factor->values->values[0] ?? null;
				if ($inner instanceof CSSUnitValue) {
					$u = $inner->getUnitObjInternal() ?? CSSUnitEnum::NUMBER;
					$k = fdiv($k, $inner->value);
					if ($u !== CSSUnitEnum::NUMBER) {
						$invertedUnits[] = $u;
					}
					continue;
				}
				if ($inner instanceof CSSMathConstant) {
					$k = fdiv($k, $inner->getFloatValue());
					continue;
				}
			}
			$complexFactors[] = $factor;
		}

		// Cancel matching direct and inverted dimension units in O(N) (never cancel `% / %`).
		if ($directUnits !== [] && $invertedUnits !== []) {
			/** @var array<string, int> $directCounts */
			$directCounts = [];
			foreach ($directUnits as $u) {
				if ($u !== CSSUnitEnum::PERCENT) {
					$directCounts[$u->value] = ($directCounts[$u->value] ?? 0) + 1;
				}
			}

			/** @var array<string, int> $canceledCounts */
			$canceledCounts = [];
			$remainingInverted = [];
			foreach ($invertedUnits as $invUnit) {
				$key = $invUnit->value;
				if ($invUnit !== CSSUnitEnum::PERCENT && ($directCounts[$key] ?? 0) > 0) {
					$directCounts[$key]--;
					$canceledCounts[$key] = ($canceledCounts[$key] ?? 0) + 1;
					continue;
				}
				$remainingInverted[] = $invUnit;
			}

			if ($canceledCounts !== []) {
				$remainingDirect = [];
				foreach ($directUnits as $u) {
					$key = $u->value;
					if (($canceledCounts[$key] ?? 0) > 0) {
						$canceledCounts[$key]--;
						continue;
					}
					$remainingDirect[] = $u;
				}
				$directUnits = $remainingDirect;
				$invertedUnits = $remainingInverted;
			}
		}

		if (is_nan($k)) {
			return self::buildDegenerate(NAN, $origType, $allocated);
		}
		if (is_infinite($k)) {
			if ($complexFactors === []) {
				return self::buildDegenerate($k, $origType, $allocated);
			}
		}

		// Distribute finite scalar multiplier into a single CSSMathSum factor (never distribute sum * sum).
		if (is_finite($k)
			&& $directUnits === []
			&& $invertedUnits === []
			&& count($complexFactors) === 1
			&& $complexFactors[0] instanceof CSSMathSum
		) {
			$sum = $complexFactors[0];
			if ($k === 1.0 && $sum->getNumericTypeInternal()->equals($origType)) {
				return $sum;
			}
			self::countAllocation($allocated);
			$scalarNode = new CSSUnitValue($k, CSSUnitEnum::NUMBER);
			$distributed = [];
			foreach ($sum->values->values as $sumTerm) {
				self::countVisit($visits);
				$distributed[] = self::simplifyProduct([$scalarNode, $sumTerm], $sumTerm->getNumericTypeInternal(), $visits, $allocated);
			}

			return self::simplifySum($distributed, $origType, $visits, $allocated);
		}

		if (is_finite($k) && $complexFactors === [] && $invertedUnits === [] && $directUnits === []) {
			if ($origType->toArray() === []) {
				self::countAllocation($allocated);

				return new CSSUnitValue($k, CSSUnitEnum::NUMBER);
			}
		}

		if (is_finite($k) && $complexFactors === [] && $invertedUnits === [] && count($directUnits) === 1) {
			$singleUnit = $directUnits[0];
			self::countAllocation($allocated);
			$candidate = new CSSUnitValue($k, $singleUnit);
			if ($candidate->getNumericTypeInternal()->equals($origType)) {
				return $candidate;
			}
		}

		/** @var list<CSSNumericValue> $ordered */
		$ordered = [];
		$hasOtherFactors = $directUnits !== [] || $invertedUnits !== [] || $complexFactors !== [];
		if (is_infinite($k)) {
			self::countAllocation($allocated);
			$ordered[] = $k > 0.0 ? CSSMathConstant::infinity() : CSSMathConstant::negInfinity();
		} elseif ($k !== 1.0 || !$hasOtherFactors) {
			self::countAllocation($allocated);
			$ordered[] = new CSSUnitValue($k, CSSUnitEnum::NUMBER);
		}

		$percentUnits = [];
		$dimUnits = [];
		foreach ($directUnits as $u) {
			if ($u === CSSUnitEnum::PERCENT) {
				$percentUnits[] = $u;
			} else {
				$dimUnits[] = $u;
			}
		}
		usort($dimUnits, static fn (CSSUnitEnum $a, CSSUnitEnum $b): int => strcmp(strtolower($a->toString()), strtolower($b->toString())));
		foreach ([...$percentUnits, ...$dimUnits] as $u) {
			self::countAllocation($allocated);
			$ordered[] = new CSSUnitValue(1.0, $u);
		}

		$invPercent = [];
		$invDims = [];
		foreach ($invertedUnits as $u) {
			if ($u === CSSUnitEnum::PERCENT) {
				$invPercent[] = $u;
			} else {
				$invDims[] = $u;
			}
		}
		usort($invDims, static fn (CSSUnitEnum $a, CSSUnitEnum $b): int => strcmp(strtolower($a->toString()), strtolower($b->toString())));
		foreach ([...$invPercent, ...$invDims] as $u) {
			self::countAllocation($allocated);
			self::countAllocation($allocated);
			$ordered[] = new CSSMathInvert([new CSSUnitValue(1.0, $u)]);
		}

		foreach ($complexFactors as $cf) {
			$ordered[] = $cf;
		}

		if ($ordered === []) {
			self::countAllocation($allocated);

			return new CSSUnitValue(1.0, CSSUnitEnum::NUMBER);
		}
		if (count($ordered) === 1 && $ordered[0]->getNumericTypeInternal()->equals($origType)) {
			return $ordered[0];
		}

		self::countAllocation($allocated);

		return CSSMathProduct::fromValidated($ordered, clone $origType);
	}

	/**
	 * @param list<CSSNumericValue> $children
	 */
	private static function simplifyMinMax(bool $isMin, array $children, CSSNumericType $origType, int &$visits, int &$allocated): CSSNumericValue
	{
		if (count($children) === 1 && $children[0]->getNumericTypeInternal()->equals($origType)) {
			return $children[0];
		}

		/** @var array<string, array{unit: CSSUnitEnum, value: float}> $unitBuckets */
		$unitBuckets = [];
		/** @var list<CSSNumericValue> $otherArgs */
		$otherArgs = [];

		foreach ($children as $child) {
			self::countVisit($visits);
			$scalar = self::extractScalar($child);
			if ($scalar !== null) {
				[$val, $unit] = $scalar;
				if (is_nan($val)) {
					return self::buildDegenerate(NAN, $origType, $allocated);
				}
				if ($unit !== null && $unit !== CSSUnitEnum::PERCENT) {
					$key = $unit->value;
					if (!isset($unitBuckets[$key])) {
						$unitBuckets[$key] = ['unit' => $unit, 'value' => $val];
					} else {
						$cur = $unitBuckets[$key]['value'];
						$unitBuckets[$key]['value'] = $isMin
							? ($val < $cur ? $val : $cur)
							: ($val > $cur ? $val : $cur);
					}
					continue;
				}
			}
			$otherArgs[] = $child;
		}

		$resultArgs = [];
		foreach ($unitBuckets as $bucket) {
			if (is_infinite($bucket['value'])) {
				$cat = $bucket['unit']->type()?->value;
				$type = CSSNumericType::fromValidatedDimensions($cat !== null ? [$cat => 1] : []);
				$resultArgs[] = self::buildDegenerate($bucket['value'], $type, $allocated);
			} else {
				self::countAllocation($allocated);
				$resultArgs[] = new CSSUnitValue($bucket['value'], $bucket['unit']);
			}
		}
		foreach ($otherArgs as $arg) {
			$resultArgs[] = $arg;
		}

		if (count($resultArgs) === 1 && $resultArgs[0]->getNumericTypeInternal()->equals($origType)) {
			return $resultArgs[0];
		}

		self::countAllocation($allocated);

		return $isMin
			? CSSMathMin::fromValidated($resultArgs, clone $origType)
			: CSSMathMax::fromValidated($resultArgs, clone $origType);
	}

	private static function simplifyClamp(
		CSSNumericValue $minNode,
		CSSNumericValue $valNode,
		CSSNumericValue $maxNode,
		CSSNumericType $origType,
		int &$allocated,
	): CSSNumericValue {
		$sMin = self::extractScalar($minNode);
		$sVal = self::extractScalar($valNode);
		$sMax = self::extractScalar($maxNode);

		if (($sMin !== null && is_nan($sMin[0])) || ($sVal !== null && is_nan($sVal[0])) || ($sMax !== null && is_nan($sMax[0]))) {
			return self::buildDegenerate(NAN, $origType, $allocated);
		}

		if ($sMin !== null && $sVal !== null && $sMax !== null
			&& $sMin[1] !== null && $sMin[1] !== CSSUnitEnum::PERCENT
			&& $sMin[1] === $sVal[1] && $sVal[1] === $sMax[1]
		) {
			$minV = $sMin[0];
			$valV = $sVal[0];
			$maxV = $sMax[0];
			// CSS Values 4 §10.3: max(MIN, min(VAL, MAX)) — MIN wins when MIN > MAX.
			$innerMin = $valV < $maxV ? $valV : $maxV;
			$clamped = $minV > $innerMin ? $minV : $innerMin;

			return self::boxScalar($clamped, $sMin[1], $origType, $allocated);
		}

		self::countAllocation($allocated);

		return new CSSMathClamp($minNode, $valNode, $maxNode);
	}

	/**
	 * @param list<CSSNumericValue> $args
	 */
	private static function simplifyFunction(
		CSSMathFunctionEnum $function,
		array $args,
		?CSSRoundingStrategy $strategy,
		CSSNumericType $origType,
		?CSSContext $context,
		int &$visits,
		int &$allocated,
	): CSSNumericValue {
		switch ($function) {
			case CSSMathFunctionEnum::ROUND:
				$sA = self::extractScalar($args[0]);
				$sB = isset($args[1]) ? self::extractScalar($args[1]) : [1.0, CSSUnitEnum::NUMBER];
				if ($sA !== null && $sB !== null && $sA[1] !== null && $sA[1] !== CSSUnitEnum::PERCENT && $sA[1] === $sB[1]) {
					$res = self::evaluateRound($strategy ?? CSSRoundingStrategy::NEAREST, $sA[0], $sB[0]);

					return self::boxScalar($res, $sA[1], $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::MOD:
			case CSSMathFunctionEnum::REM:
				$sA = self::extractScalar($args[0]);
				$sB = self::extractScalar($args[1]);
				if ($sA !== null && $sB !== null && $sA[1] !== null && $sA[1] !== CSSUnitEnum::PERCENT && $sA[1] === $sB[1]) {
					$res = $function === CSSMathFunctionEnum::MOD
						? self::evaluateMod($sA[0], $sB[0])
						: self::evaluateRem($sA[0], $sB[0]);

					return self::boxScalar($res, $sA[1], $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::SIN:
			case CSSMathFunctionEnum::COS:
			case CSSMathFunctionEnum::TAN:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] !== null) {
					if ($sA[1] === CSSUnitEnum::NUMBER || $sA[1] === CSSUnitEnum::ANGLE_rad) {
						return self::evaluateTrigRadians($function, $sA[0], $allocated);
					}
					if ($sA[1]->type() === CSSUnitTypeEnum::ANGLE) {
						$deg = match ($sA[1]) {
							CSSUnitEnum::ANGLE_grad => $sA[0] * 0.9,
							CSSUnitEnum::ANGLE_turn => $sA[0] * 360.0,
							default => $sA[0],
						};

						return self::evaluateTrigDegrees($function, $deg, $allocated);
					}
				}
				break;

			case CSSMathFunctionEnum::ASIN:
			case CSSMathFunctionEnum::ACOS:
			case CSSMathFunctionEnum::ATAN:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] === CSSUnitEnum::NUMBER) {
					$v = $sA[0];
					$deg = match ($function) {
						CSSMathFunctionEnum::ASIN => (is_nan($v) || $v < -1.0 || $v > 1.0) ? NAN : rad2deg(asin($v)),
						CSSMathFunctionEnum::ACOS => (is_nan($v) || $v < -1.0 || $v > 1.0) ? NAN : rad2deg(acos($v)),
						CSSMathFunctionEnum::ATAN => is_nan($v) ? NAN : (is_infinite($v) ? ($v > 0.0 ? 90.0 : -90.0) : rad2deg(atan($v))),
					};

					return self::boxScalar($deg, CSSUnitEnum::ANGLE_deg, $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::ATAN2:
				$sY = self::extractScalar($args[0]);
				$sX = self::extractScalar($args[1]);
				if ($sY !== null && $sX !== null && $sY[1] !== null && $sY[1] !== CSSUnitEnum::PERCENT && $sY[1] === $sX[1]) {
					$deg = (is_nan($sY[0]) || is_nan($sX[0])) ? NAN : rad2deg(atan2($sY[0], $sX[0]));

					return self::boxScalar($deg, CSSUnitEnum::ANGLE_deg, $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::POW:
				$sA = self::extractScalar($args[0]);
				$sB = self::extractScalar($args[1]);
				if ($sA !== null && $sB !== null && $sA[1] === CSSUnitEnum::NUMBER && $sB[1] === CSSUnitEnum::NUMBER) {
					return self::boxNumber(self::evaluatePow($sA[0], $sB[0]), $allocated);
				}
				break;

			case CSSMathFunctionEnum::SQRT:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] === CSSUnitEnum::NUMBER) {
					$v = $sA[0];
					$res = (is_nan($v) || $v < 0.0) ? NAN : (is_infinite($v) ? INF : sqrt($v));

					return self::boxNumber($res, $allocated);
				}
				break;

			case CSSMathFunctionEnum::HYPOT:
				$vals = [];
				$commonUnit = null;
				$canFold = true;
				foreach ($args as $arg) {
					self::countVisit($visits);
					$scalar = self::extractScalar($arg);
					if ($scalar === null || $scalar[1] === null || $scalar[1] === CSSUnitEnum::PERCENT) {
						$canFold = false;
						break;
					}
					if ($commonUnit === null) {
						$commonUnit = $scalar[1];
					} elseif ($commonUnit !== $scalar[1]) {
						$canFold = false;
						break;
					}
					$vals[] = $scalar[0];
				}
				if ($canFold && $commonUnit !== null) {
					return self::boxScalar(self::evaluateHypot($vals), $commonUnit, $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::LOG:
				$sA = self::extractScalar($args[0]);
				$sB = isset($args[1]) ? self::extractScalar($args[1]) : null;
				if ($sA !== null && $sA[1] === CSSUnitEnum::NUMBER && (!isset($args[1]) || ($sB !== null && $sB[1] === CSSUnitEnum::NUMBER))) {
					$lnA = self::evaluateLn($sA[0]);
					$res = $sB === null ? $lnA : fdiv($lnA, self::evaluateLn($sB[0]));

					return self::boxNumber($res, $allocated);
				}
				break;

			case CSSMathFunctionEnum::EXP:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] === CSSUnitEnum::NUMBER) {
					$v = $sA[0];
					$res = is_nan($v) ? NAN : (is_infinite($v) ? ($v > 0.0 ? INF : 0.0) : exp($v));

					return self::boxNumber($res, $allocated);
				}
				break;

			case CSSMathFunctionEnum::ABS:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] !== null && $sA[1] !== CSSUnitEnum::PERCENT) {
					return self::boxScalar(abs($sA[0]), $sA[1], $origType, $allocated);
				}
				break;

			case CSSMathFunctionEnum::SIGN:
				$sA = self::extractScalar($args[0]);
				if ($sA !== null && $sA[1] !== null && $sA[1] !== CSSUnitEnum::PERCENT) {
					$isFoldableUnit = $sA[1] === CSSUnitEnum::NUMBER
						|| $sA[1]->type() !== CSSUnitTypeEnum::LENGTH
						|| $sA[1] === CSSUnitEnum::LENGTH_px
						|| isset(self::ABSOLUTE_LENGTH_TO_PX[strtolower($sA[1]->toString())])
						|| $context !== null;
					if ($isFoldableUnit) {
						$v = $sA[0];
						$res = is_nan($v) ? NAN : ($v > 0.0 ? 1.0 : ($v < 0.0 ? -1.0 : 0.0));

						return self::boxNumber($res, $allocated);
					}
				}
				break;
		}

		self::countAllocation($allocated);

		return CSSMathFunction::fromValidatedInternal($function, $args, clone $origType, $strategy);
	}

	private static function evaluateRound(CSSRoundingStrategy $strategy, float $a, float $b): float
	{
		if (is_nan($a) || is_nan($b) || $b === 0.0 || (is_infinite($a) && is_infinite($b))) {
			return NAN;
		}
		if (is_infinite($a)) {
			return $a;
		}
		if (is_infinite($b)) {
			return match ($strategy) {
				CSSRoundingStrategy::NEAREST, CSSRoundingStrategy::TO_ZERO => 0.0,
				CSSRoundingStrategy::UP => $a > 0.0 ? INF : 0.0,
				CSSRoundingStrategy::DOWN => $a < 0.0 ? -INF : 0.0,
			};
		}

		$step = abs($b);
		$q = fdiv($a, $step);
		if (!is_finite($q)) {
			// Quotient overflow in IEEE-754 binary64 means $a is already an exact multiple of $step.
			return $a;
		}

		$qFloor = floor($q);
		$lower = $qFloor * $step;
		$upper = ceil($q) * $step;

		return match ($strategy) {
			CSSRoundingStrategy::NEAREST => ($q - $qFloor) >= 0.5 ? $upper : $lower,
			CSSRoundingStrategy::UP => $upper,
			CSSRoundingStrategy::DOWN => $lower,
			CSSRoundingStrategy::TO_ZERO => $a >= 0.0 ? $lower : $upper,
		};
	}

	private static function evaluateMod(float $a, float $b): float
	{
		if (is_nan($a) || is_nan($b) || is_infinite($a) || $b === 0.0) {
			return NAN;
		}
		if (is_infinite($b)) {
			if ($a === 0.0) {
				return 0.0;
			}

			return (($a > 0.0 && $b > 0.0) || ($a < 0.0 && $b < 0.0)) ? $a : NAN;
		}

		$r = fmod($a, $b);
		if ($r === 0.0) {
			return 0.0;
		}
		if (($r < 0.0) !== ($b < 0.0)) {
			$r += $b;
		}

		return $r === 0.0 ? 0.0 : $r;
	}

	private static function evaluateRem(float $a, float $b): float
	{
		if (is_nan($a) || is_nan($b) || is_infinite($a) || $b === 0.0) {
			return NAN;
		}
		if (is_infinite($b)) {
			return $a;
		}

		$r = fmod($a, $b);

		return $r === 0.0 ? 0.0 : $r;
	}

	private static function evaluateTrigRadians(CSSMathFunctionEnum $fn, float $rad, int &$allocated): CSSNumericValue
	{
		if (is_nan($rad) || is_infinite($rad)) {
			return self::boxNumber(NAN, $allocated);
		}
		$res = match ($fn) {
			CSSMathFunctionEnum::SIN => sin($rad),
			CSSMathFunctionEnum::COS => cos($rad),
			CSSMathFunctionEnum::TAN => tan($rad),
			default => NAN,
		};

		return self::boxNumber($res, $allocated);
	}

	private static function evaluateTrigDegrees(CSSMathFunctionEnum $fn, float $deg, int &$allocated): CSSNumericValue
	{
		if (is_nan($deg) || is_infinite($deg)) {
			return self::boxNumber(NAN, $allocated);
		}

		$d = fmod($deg, 360.0);
		if ($d < 0.0) {
			$d += 360.0;
		}
		if ($d === 360.0) {
			$d = 0.0;
		}

		$res = match ($fn) {
			CSSMathFunctionEnum::SIN => match (true) {
				$d === 0.0 || $d === 180.0 => 0.0,
				$d === 90.0 => 1.0,
				$d === 270.0 => -1.0,
				default => sin(deg2rad($d)),
			},
			CSSMathFunctionEnum::COS => match (true) {
				$d === 0.0 => 1.0,
				$d === 90.0 || $d === 270.0 => 0.0,
				$d === 180.0 => -1.0,
				default => cos(deg2rad($d)),
			},
			CSSMathFunctionEnum::TAN => match (true) {
				$d === 0.0 || $d === 180.0 => 0.0,
				$d === 45.0 || $d === 225.0 => 1.0,
				$d === 135.0 || $d === 315.0 => -1.0,
				$d === 90.0 => INF,
				$d === 270.0 => -INF,
				default => tan(deg2rad($d)),
			},
			default => NAN,
		};

		return self::boxNumber($res, $allocated);
	}

	private static function evaluatePow(float $a, float $b): float
	{
		if ($b === 0.0 || $a === 1.0) {
			return 1.0;
		}
		if (is_nan($a) || is_nan($b)) {
			return NAN;
		}
		if ($a === -1.0 && is_infinite($b)) {
			return 1.0;
		}
		if ($a < 0.0 && is_finite($a) && is_finite($b) && floor($b) !== $b) {
			return NAN;
		}
		if ($a === 0.0 && $b < 0.0) {
			return INF;
		}

		return $a ** $b;
	}

	/**
	 * @param non-empty-list<float> $vals
	 */
	private static function evaluateHypot(array $vals): float
	{
		$hasNaN = false;
		$maxAbs = 0.0;
		foreach ($vals as $v) {
			if (is_infinite($v)) {
				// IEEE-754 hypot returns +INF whenever any argument is infinite, even if another is NaN.
				return INF;
			}
			if (is_nan($v)) {
				$hasNaN = true;
				continue;
			}
			$abs = abs($v);
			if ($abs > $maxAbs) {
				$maxAbs = $abs;
			}
		}
		if ($hasNaN) {
			return NAN;
		}
		if ($maxAbs === 0.0) {
			return 0.0;
		}

		$sumSq = 0.0;
		foreach ($vals as $v) {
			$scaled = $v / $maxAbs;
			$sumSq += $scaled * $scaled;
		}

		return $maxAbs * sqrt($sumSq);
	}

	private static function evaluateLn(float $x): float
	{
		if (is_nan($x) || $x < 0.0) {
			return NAN;
		}
		if ($x === 0.0) {
			return -INF;
		}
		if (is_infinite($x)) {
			return INF;
		}

		return log($x);
	}

	/**
	 * Extracts `[float, ?CSSUnitEnum]` from a finite `CSSUnitValue`, `CSSMathConstant`,
	 * or single-unit degenerate product (`infinity * 1px`, `NaN * 1px`).
	 *
	 * @return ?array{0: float, 1: ?CSSUnitEnum}
	 */
	private static function extractScalar(CSSNumericValue $node): ?array
	{
		if ($node instanceof CSSUnitValue) {
			return [$node->value, $node->getUnitObjInternal()];
		}
		if ($node instanceof CSSMathConstant) {
			return [$node->getFloatValue(), CSSUnitEnum::NUMBER];
		}
		if ($node instanceof CSSMathProduct && $node->getNumericTypeInternal()->percentHint === null) {
			$factors = $node->values->values;
			if (count($factors) === 2
				&& $factors[0] instanceof CSSMathConstant
				&& $factors[1] instanceof CSSUnitValue
				&& $factors[1]->value === 1.0
				&& $factors[1]->getUnitObjInternal() !== null
			) {
				return [$factors[0]->getFloatValue(), $factors[1]->getUnitObjInternal()];
			}
		}

		return null;
	}

	private static function boxNumber(float $value, int &$allocated): CSSNumericValue
	{
		if (!is_finite($value)) {
			self::countAllocation($allocated);

			return is_nan($value)
				? CSSMathConstant::nan()
				: ($value > 0.0 ? CSSMathConstant::infinity() : CSSMathConstant::negInfinity());
		}

		self::countAllocation($allocated);

		return new CSSUnitValue($value, CSSUnitEnum::NUMBER);
	}

	private static function boxScalar(float $value, CSSUnitEnum $unit, CSSNumericType $origType, int &$allocated): CSSNumericValue
	{
		if (!is_finite($value)) {
			return self::buildDegenerate($value, $origType, $allocated);
		}

		self::countAllocation($allocated);
		$candidate = new CSSUnitValue($value, $unit);
		if ($candidate->getNumericTypeInternal()->equals($origType)) {
			return $candidate;
		}

		return self::wrapWithType($candidate, $origType, $allocated);
	}

	private static function wrapWithType(CSSUnitValue $val, CSSNumericType $origType, int &$allocated): CSSNumericValue
	{
		self::countAllocation($allocated);

		return CSSMathSum::fromValidated([$val], clone $origType);
	}

	private static function buildZeroForType(CSSNumericType $origType, int &$allocated): CSSNumericValue
	{
		$dims = $origType->toArray();
		if ($dims === []) {
			self::countAllocation($allocated);

			return new CSSUnitValue(0.0, CSSUnitEnum::NUMBER);
		}
		$nonZero = [];
		foreach ($dims as $dim => $exp) {
			if ($dim !== 'percentHint' && (int) $exp !== 0) {
				$nonZero[$dim] = (int) $exp;
			}
		}
		if (count($nonZero) === 1 && reset($nonZero) === 1) {
			$dimName = array_key_first($nonZero);
			$unit = self::DIMENSION_TO_CANONICAL_UNIT[$dimName] ?? null;
			if ($unit !== null) {
				self::countAllocation($allocated);
				$uv = new CSSUnitValue(0.0, $unit);
				if ($uv->getNumericTypeInternal()->equals($origType)) {
					return $uv;
				}
			}
		}

		self::countAllocation($allocated);

		return CSSMathProduct::fromValidated([new CSSUnitValue(0.0, CSSUnitEnum::NUMBER)], clone $origType);
	}

	/**
	 * Synthesizes a canonical non-finite expression preserving exact CSSNumericType (with DAG DoS guard).
	 */
	private static function buildDegenerate(float $nonFinite, CSSNumericType $type, int &$allocated): CSSNumericValue
	{
		$const = is_nan($nonFinite)
			? CSSMathConstant::nan()
			: ($nonFinite > 0.0 ? CSSMathConstant::infinity() : CSSMathConstant::negInfinity());

		$dims = $type->toArray();
		if ($dims === []) {
			self::countAllocation($allocated);

			return $const;
		}

		$unitFactors = [];
		foreach ($dims as $dim => $exp) {
			if ($dim === 'percentHint') {
				continue;
			}
			$expInt = (int) $exp;
			if ($expInt === 0) {
				continue;
			}
			$unit = self::DIMENSION_TO_CANONICAL_UNIT[$dim] ?? null;
			if ($unit === null) {
				continue;
			}
			$absExp = $expInt === PHP_INT_MIN ? CSSNumericValue::MAX_MATH_NODES + 1 : abs($expInt);
			$perFactor = $expInt > 0 ? 1 : 2;
			if ($absExp > CSSNumericValue::MAX_MATH_NODES || ($absExp * $perFactor) > (CSSNumericValue::MAX_MATH_NODES - $allocated - 2)) {
				throw new CSSParserLimitException(sprintf(
					'Maximum CSS math expression node count of %d exceeded.',
					CSSNumericValue::MAX_MATH_NODES,
				));
			}
			for ($i = 0; $i < $absExp; $i++) {
				self::countAllocation($allocated);
				$leaf = new CSSUnitValue(1.0, $unit);
				if ($expInt > 0) {
					$unitFactors[] = $leaf;
				} else {
					self::countAllocation($allocated);
					$unitFactors[] = new CSSMathInvert([$leaf]);
				}
			}
		}

		if ($unitFactors === [] && $type->percentHint === null) {
			self::countAllocation($allocated);

			return $const;
		}

		self::countAllocation($allocated);
		self::countAllocation($allocated);

		return CSSMathProduct::fromValidated([$const, ...$unitFactors], clone $type);
	}

	private static function countVisit(int &$visits): void
	{
		if (++$visits > CSSNumericValue::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf(
				'Maximum CSS math expression node count of %d exceeded.',
				CSSNumericValue::MAX_MATH_NODES,
			));
		}
	}

	private static function countAllocation(int &$allocated): void
	{
		if (++$allocated > CSSNumericValue::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf(
				'Maximum CSS math expression node count of %d exceeded.',
				CSSNumericValue::MAX_MATH_NODES,
			));
		}
	}
}
