<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents CSS Values Level 4 math functions (`round`, `mod`, `rem`, trigonometric, exponential, and sign functions)
 * per CSS Typed OM Level 2 / `cssomnom` conventions.
 *
 * @see https://drafts.csswg.org/css-values-4/#math
 */
final class CSSMathFunction extends CSSMathValue
{
	public protected(set) CSSMathFunctionEnum $function;
	public protected(set) ?CSSRoundingStrategy $strategy;

	/**
	 * @param string|CSSMathFunctionEnum $operator Function name (e.g. `'sin'`, `'round'`, `'mod'`, `'hypot'`)
	 * @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values Numeric arguments
	 * @param CSSRoundingStrategy|string|null $strategy Optional rounding strategy (valid only for `'round'`; defaults to `'nearest'`)
	 * @throws InvalidArgumentException When operator, strategy, operand count, or numeric types are invalid
	 */
	public function __construct(
		string|CSSMathFunctionEnum $operator,
		CSSNumericValue|CSSNumericArray|array $values,
		CSSRoundingStrategy|string|null $strategy = null,
	) {
		if (is_string($operator)) {
			$resolved = CSSMathFunctionEnum::tryFrom(strtolower($operator));
			if ($resolved === null) {
				throw new InvalidArgumentException('Unsupported CSS math function: ' . $operator);
			}
			$function = $resolved;
		} else {
			$function = $operator;
		}

		if ($function === CSSMathFunctionEnum::ROUND) {
			if ($strategy === null) {
				$resolvedStrategy = CSSRoundingStrategy::NEAREST;
			} elseif (is_string($strategy)) {
				$resolvedStrategy = CSSRoundingStrategy::tryFrom(strtolower($strategy));
				if ($resolvedStrategy === null) {
					throw new InvalidArgumentException('Unsupported CSS rounding strategy: ' . $strategy);
				}
			} else {
				$resolvedStrategy = $strategy;
			}
		} elseif ($strategy !== null) {
			throw new InvalidArgumentException(sprintf('Rounding strategy is only valid for round(), not %s().', $function->value));
		} else {
			$resolvedStrategy = null;
		}

		$this->function = $function;
		$this->strategy = $resolvedStrategy;
		parent::__construct($values);
		$this->requireOperandCount($function->minArgs(), $function->maxArgs());
		$this->cachedNumericType = self::computeAndValidateTypeInternal($function, $this->values->values);
	}

	public function getOperator(): string
	{
		return $this->function->value;
	}

	public function getFunction(): CSSMathFunctionEnum
	{
		return $this->function;
	}

	public function getStrategy(): ?CSSRoundingStrategy
	{
		return $this->strategy;
	}

	/**
	 * @internal Fast path for validated reconstruction and cloning.
	 * @param list<CSSNumericValue> $values
	 */
	public static function fromValidatedInternal(
		CSSMathFunctionEnum $function,
		array $values,
		?CSSNumericType $type = null,
		?CSSRoundingStrategy $strategy = null,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $node */
		$node = $reflection->newInstanceWithoutConstructor();
		$node->function = $function;
		$node->strategy = $function === CSSMathFunctionEnum::ROUND ? ($strategy ?? CSSRoundingStrategy::NEAREST) : null;
		$node->values = new CSSNumericArray($values);
		$node->cachedNumericType = $type;

		return $node;
	}

	/**
	 * Computes and validates the CSSNumericType of a CSS Values Level 4 math function (§10.9).
	 *
	 * @internal
	 * @param list<CSSNumericValue> $args
	 * @throws InvalidArgumentException When operand count or numeric types violate CSS Values 4 §10.9
	 */
	public static function computeAndValidateTypeInternal(CSSMathFunctionEnum $function, array $args): CSSNumericType
	{
		$count = count($args);
		$max = $function->maxArgs();
		if ($count < $function->minArgs() || ($max !== null && $count > $max)) {
			throw new InvalidArgumentException(sprintf('Invalid number of operands for %s().', $function->value));
		}

		return match ($function) {
			CSSMathFunctionEnum::SIN, CSSMathFunctionEnum::COS, CSSMathFunctionEnum::TAN => self::validateTrigInput($args[0], $function->value),
			CSSMathFunctionEnum::ASIN, CSSMathFunctionEnum::ACOS, CSSMathFunctionEnum::ATAN => self::validateAllPureNumbers($args, $function->value, 'angle'),
			CSSMathFunctionEnum::ATAN2 => self::validateAtan2($args),
			CSSMathFunctionEnum::POW, CSSMathFunctionEnum::SQRT, CSSMathFunctionEnum::LOG, CSSMathFunctionEnum::EXP => self::validateAllPureNumbers($args, $function->value, null),
			CSSMathFunctionEnum::ROUND => $count === 1
				? self::validateAllPureNumbers($args, $function->value, null)
				: CSSNumericValue::mergeAdditiveTypesInternal($args),
			CSSMathFunctionEnum::MOD, CSSMathFunctionEnum::REM, CSSMathFunctionEnum::HYPOT => CSSNumericValue::mergeAdditiveTypesInternal($args),
			CSSMathFunctionEnum::ABS => $args[0]->getNumericTypeInternal(),
			CSSMathFunctionEnum::SIGN => CSSNumericType::fromValidatedDimensions([]),
		};
	}

	private static function isPureNumberType(CSSNumericType $type): bool
	{
		return $type->toArray() === [] && $type->percentHint === null;
	}

	private static function isPureAngleType(CSSNumericType $type): bool
	{
		if ($type->percentHint !== null) {
			return false;
		}
		$dims = $type->toArray();

		return count($dims) === 1 && ($dims['angle'] ?? 0) === 1;
	}

	private static function validateTrigInput(CSSNumericValue $arg, string $fnName): CSSNumericType
	{
		$type = $arg->getNumericTypeInternal();
		if (!self::isPureNumberType($type) && !self::isPureAngleType($type)) {
			throw new InvalidArgumentException(sprintf('CSS %s() requires a <number> or <angle> operand.', $fnName));
		}

		return CSSNumericType::fromValidatedDimensions([]);
	}

	/**
	 * @param list<CSSNumericValue> $args
	 */
	private static function validateAllPureNumbers(array $args, string $fnName, ?string $returnDim): CSSNumericType
	{
		foreach ($args as $arg) {
			if (!self::isPureNumberType($arg->getNumericTypeInternal())) {
				throw new InvalidArgumentException(sprintf('CSS %s() requires <number> operands.', $fnName));
			}
		}

		return CSSNumericType::fromValidatedDimensions($returnDim === null ? [] : [$returnDim => 1]);
	}

	/**
	 * @param list<CSSNumericValue> $args
	 */
	private static function validateAtan2(array $args): CSSNumericType
	{
		CSSNumericValue::mergeAdditiveTypesInternal($args);

		return CSSNumericType::fromValidatedDimensions(['angle' => 1]);
	}
}
