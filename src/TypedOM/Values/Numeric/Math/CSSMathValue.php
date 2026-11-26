<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use ReflectionClass;

/**
 * Base class for CSS Typed OM math expressions (`calc()`, `min()`, `max()`, `clamp()`, and CSS Values 4 functions).
 *
 * @property-read ?string $operator
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
	public const ?string operator = null;

	public protected(set) CSSNumericArray $values;

	/** @var list<CSSNumericValue> */
	public array $inner_values {
		get => $this->values->values;
	}

	public int $length {
		get => count($this->values->values);
	}

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		if ($values instanceof CSSNumericArray) {
			$this->values = $values;
		} elseif ($values instanceof CSSNumericValue) {
			$this->values = new CSSNumericArray([$values]);
		} else {
			$this->values = new CSSNumericArray($values);
		}
	}

	public function getOperator(): ?string
	{
		return static::operator;
	}

	public function __get(string $name): mixed
	{
		if ($name === 'operator') {
			return $this->getOperator();
		}
		return parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'operator' || parent::__isset($name);
	}

	protected function requireOperandCount(int $minimum, ?int $maximum = null): void
	{
		$count = count($this->values);
		if ($count > CSSNumericValue::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
		}
		if ($count < $minimum || ($maximum !== null && $count > $maximum)) {
			throw new InvalidArgumentException('Invalid number of operands for ' . static::class . '.');
		}
	}

	protected function validateAdditiveTypes(): void
	{
		$this->cachedNumericType = self::mergeAdditiveTypesInternal($this->values->values);
	}

	/**
	 * @internal Fast path for already type-checked operand lists.
	 * @param list<CSSNumericValue> $values
	 */
	public static function fromValidated(array $values, ?CSSNumericType $type = null): static
	{
		static $reflections = [];
		$cls = static::class;
		$refl = $reflections[$cls] ??= new ReflectionClass($cls);
		/** @var static $node */
		$node = $refl->newInstanceWithoutConstructor();
		$node->values = CSSNumericArray::fromTrustedList($values);
		$node->cachedNumericType = $type;
		if ($node instanceof CSSMathNegate || $node instanceof CSSMathInvert) {
			$node->value = $values[0] ?? null;
		} elseif ($node instanceof CSSMathClamp) {
			$node->value = $values[1] ?? null;
		}

		return $node;
	}

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes, ?int $maxMathDepth = null): string
	{
		return self::serializeMath($this, $maxBytes, $maxMathDepth);
	}

	/**
	 * @internal Verifies that serialized paren/function nesting stays within $maxMathDepth
	 * without failing on implicit AST wrappers (`CSSMathNegate` inside sum subtraction,
	 * `CSSMathInvert` inside product division, or `CSSMathProduct` inside `CSSMathSum`).
	 */
	public function assertMathDepthWithinInternal(int $maxMathDepth): void
	{
		$visits = 0;
		// Frame: [CSSNumericValue $value, int $mathDepth, int $treeDepth, bool $isRoot, int $parentPrecedence]
		$stack = [[$this, 0, 0, true, 0]];

		while ($stack !== []) {
			[$value, $mathDepth, $treeDepth, $root, $parentPrecedence] = array_pop($stack);
			if ($treeDepth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}

			if (!$value instanceof self) {
				if ($mathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				continue;
			}

			if ($value instanceof CSSMathConstant) {
				if ($mathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				continue;
			}

			if ($value instanceof CSSMathMin || $value instanceof CSSMathMax || $value instanceof CSSMathClamp || $value instanceof CSSMathFunction) {
				$childDepth = $mathDepth + 1;
				if ($childDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				$args = $value->values->values;
				for ($i = count($args) - 1; $i >= 0; $i--) {
					$stack[] = [$args[$i], $childDepth, $treeDepth + 1, false, 0];
				}
				continue;
			}

			if ($value instanceof CSSMathNegate) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					if ($mathDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					continue;
				}
				$childDepth = self::negateNeedsParens($inner) ? $mathDepth + 1 : $mathDepth;
				$stack[] = [$inner, $childDepth, $treeDepth + 1, false, 3];
				continue;
			}

			if ($value instanceof CSSMathInvert) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					if ($mathDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					continue;
				}
				$childDepth = (!$root && $parentPrecedence >= 3) ? $mathDepth + 1 : $mathDepth;
				$stack[] = [$inner, $childDepth, $treeDepth + 1, false, 3];
				continue;
			}

			if ($value instanceof CSSMathProduct) {
				$values = $value->values->values;
				$count = count($values);
				$innerDepth = (!$root && $parentPrecedence > 2) ? $mathDepth + 1 : $mathDepth;
				if ($count === 0) {
					if ($innerDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					continue;
				}
				for ($i = $count - 1; $i >= 0; $i--) {
					$child = $values[$i];
					if ($child instanceof CSSMathInvert) {
						$operand = $child->values->values[0] ?? null;
						if ($operand === null) {
							if ($innerDepth > $maxMathDepth) {
								throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
							}
						} else {
							$stack[] = [$operand, $innerDepth, $treeDepth + 2, false, 3];
						}
					} else {
						$stack[] = [$child, $innerDepth, $treeDepth + 1, false, 2];
					}
				}
				continue;
			}

			if ($value instanceof CSSMathSum) {
				$values = $value->values->values;
				$count = count($values);
				$innerDepth = (!$root && $parentPrecedence >= 1) ? $mathDepth + 1 : $mathDepth;
				if ($count === 0) {
					if ($innerDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					continue;
				}
				for ($i = $count - 1; $i >= 0; $i--) {
					$child = $values[$i];
					if ($i === 0) {
						$stack[] = [$child, $innerDepth, $treeDepth + 1, false, 1];
						continue;
					}
					if ($child instanceof CSSMathNegate) {
						$operand = $child->values->values[0] ?? null;
						if ($operand === null) {
							if ($innerDepth > $maxMathDepth) {
								throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
							}
						} elseif (self::sumNegateOperandNeedsParens($operand)) {
							$stack[] = [$operand, $innerDepth + 1, $treeDepth + 2, false, 1];
						} else {
							$stack[] = [$operand, $innerDepth, $treeDepth + 2, false, 1];
						}
					} else {
						$stack[] = [$child, $innerDepth, $treeDepth + 1, false, 1];
					}
				}
				continue;
			}

			if ($mathDepth > $maxMathDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
			}
		}
	}

	public function __toString(): string
	{
		return self::serializeMath($this);
	}

	private static function negateNeedsParens(CSSNumericValue $inner): bool
	{
		return $inner instanceof CSSMathNegate
			|| ($inner instanceof CSSUnitValue && self::isNegativeFloat($inner->value))
			|| $inner instanceof CSSMathConstant
			|| $inner instanceof CSSMathFunction
			|| $inner instanceof CSSMathMin
			|| $inner instanceof CSSMathMax
			|| $inner instanceof CSSMathClamp;
	}

	private static function sumNegateOperandNeedsParens(CSSNumericValue $operand): bool
	{
		return $operand instanceof CSSMathNegate
			|| ($operand instanceof CSSUnitValue && self::isNegativeFloat($operand->value))
			|| $operand instanceof CSSMathConstant;
	}

	private static function serializeMath(
		CSSNumericValue $rootValue,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		?int $maxMathDepth = null,
	): string {
		$parts = [];
		$totalBytes = 0;
		$visits = 0;
		// Frame [0 = EMIT_STR, string] | [1 = EVAL_NODE, CSSNumericValue, parentPrecedence, isRoot, depth, mathDepth]
		$stack = [[1, $rootValue, 0, true, 0, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 0) {
				$chunk = $frame[1];
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				continue;
			}

			[, $value, $parentPrecedence, $root, $depth, $mathDepth] = $frame;
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}

			if (!$value instanceof self) {
				if ($maxMathDepth !== null && $mathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				$chunk = (string) $value;
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				continue;
			}

			if ($value instanceof CSSMathConstant) {
				if ($maxMathDepth !== null && $mathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				$kw = $value->constant->value;
				$chunk = $root ? 'calc(' . $kw . ')' : $kw;
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				continue;
			}

			if ($value instanceof CSSMathFunction) {
				$childMathDepth = $mathDepth + 1;
				if ($maxMathDepth !== null && $childMathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				$args = $value->values->values;
				$chunk = $value->function->value . '(';
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				$stack[] = [0, ')'];
				for ($i = count($args) - 1; $i >= 0; $i--) {
					$stack[] = [1, $args[$i], 0, false, $depth + 1, $childMathDepth];
					if ($i > 0) {
						$stack[] = [0, ', '];
					}
				}
				if ($value->function === CSSMathFunctionEnum::ROUND
					&& $value->strategy !== null
					&& $value->strategy !== CSSRoundingStrategy::NEAREST
				) {
					$stack[] = [0, $value->strategy->value . ', '];
				}
				continue;
			}

			if ($value instanceof CSSMathMin || $value instanceof CSSMathMax || $value instanceof CSSMathClamp) {
				$childMathDepth = $mathDepth + 1;
				if ($maxMathDepth !== null && $childMathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}
				$name = $value instanceof CSSMathMin ? 'min' : ($value instanceof CSSMathMax ? 'max' : 'clamp');
				$args = $value->values->values;
				$chunk = $name . '(';
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				$stack[] = [0, ')'];
				for ($i = count($args) - 1; $i >= 0; $i--) {
					$stack[] = [1, $args[$i], 0, false, $depth + 1, $childMathDepth];
					if ($i > 0) {
						$stack[] = [0, ', '];
					}
				}
				continue;
			}

			if ($value instanceof CSSMathNegate) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					if ($maxMathDepth !== null && $mathDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					$totalBytes += 7;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = 'calc(0)';
					continue;
				}
				$needsParens = self::negateNeedsParens($inner);
				$childMathDepth = $needsParens ? $mathDepth + 1 : $mathDepth;
				if ($root) {
					$totalBytes += 5;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = 'calc(';
					$stack[] = [0, ')'];
				}
				if ($needsParens) {
					$totalBytes += 2;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = '-(';
					$stack[] = [0, ')'];
				} else {
					$totalBytes += 1;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = '-';
				}
				$stack[] = [1, $inner, 3, false, $depth + 1, $childMathDepth];
				continue;
			}

			if ($value instanceof CSSMathInvert) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					if ($maxMathDepth !== null && $mathDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					$totalBytes += 7;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = 'calc(1)';
					continue;
				}
				if ($root) {
					$chunk = 'calc(1 / ';
					$childMathDepth = $mathDepth;
					$stack[] = [0, ')'];
				} elseif ($parentPrecedence >= 3) {
					$chunk = '(1 / ';
					$childMathDepth = $mathDepth + 1;
					$stack[] = [0, ')'];
				} else {
					$chunk = '1 / ';
					$childMathDepth = $mathDepth;
				}
				$totalBytes += strlen($chunk);
				if ($maxBytes !== null && $totalBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
				}
				$parts[] = $chunk;
				$stack[] = [1, $inner, 3, false, $depth + 1, $childMathDepth];
				continue;
			}

			if ($value instanceof CSSMathProduct) {
				$values = $value->values->values;
				$count = count($values);
				$innerDepth = (!$root && $parentPrecedence > 2) ? $mathDepth + 1 : $mathDepth;
				if ($count === 0) {
					if ($maxMathDepth !== null && $innerDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					$out = $parentPrecedence > 2 ? '(0)' : '0';
					$chunk = $root ? 'calc(' . $out . ')' : $out;
					$totalBytes += strlen($chunk);
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = $chunk;
					continue;
				}
				if ($root) {
					$totalBytes += 5;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = 'calc(';
					$stack[] = [0, ')'];
				}
				if ($parentPrecedence > 2) {
					$totalBytes += 1;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = '(';
					$stack[] = [0, ')'];
				}

				for ($i = $count - 1; $i >= 0; $i--) {
					$child = $values[$i];
					if ($child instanceof CSSMathInvert) {
						if ($visits >= self::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
						}
						$visits++;
						$operand = $child->values->values[0] ?? null;
						if ($operand === null) {
							$stack[] = [0, $i > 0 ? ' / 1' : '1 / 1'];
						} else {
							$stack[] = [1, $operand, 3, false, $depth + 2, $innerDepth];
							$stack[] = [0, $i > 0 ? ' / ' : '1 / '];
						}
					} else {
						$stack[] = [1, $child, 2, false, $depth + 1, $innerDepth];
						if ($i > 0) {
							$stack[] = [0, ' * '];
						}
					}
				}
				continue;
			}

			if ($value instanceof CSSMathSum) {
				$values = $value->values->values;
				$count = count($values);
				$innerDepth = (!$root && $parentPrecedence >= 1) ? $mathDepth + 1 : $mathDepth;
				if ($count === 0) {
					if ($maxMathDepth !== null && $innerDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					$out = $parentPrecedence >= 1 ? '(0)' : '0';
					$chunk = $root ? 'calc(' . $out . ')' : $out;
					$totalBytes += strlen($chunk);
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = $chunk;
					continue;
				}
				if ($root) {
					$totalBytes += 5;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = 'calc(';
					$stack[] = [0, ')'];
				}
				if ($parentPrecedence >= 1) {
					$totalBytes += 1;
					if ($maxBytes !== null && $totalBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
					}
					$parts[] = '(';
					$stack[] = [0, ')'];
				}

				for ($i = $count - 1; $i >= 0; $i--) {
					$child = $values[$i];
					if ($i === 0) {
						$stack[] = [1, $child, 1, false, $depth + 1, $innerDepth];
						continue;
					}
					if ($child instanceof CSSUnitValue && self::isNegativeFloat($child->value)) {
						$symbol = $child->getUnitObjInternal()?->toString() ?? '';
						$mag = -$child->value;
						$stack[] = [0, ' - ' . ($mag === 0.0 ? 0.0 : $mag) . $symbol];
					} elseif ($child instanceof CSSMathNegate) {
						if ($visits >= self::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
						}
						$visits++;
						$operand = $child->values->values[0] ?? null;
						if ($operand === null) {
							$stack[] = [0, ' - 0'];
						} elseif (self::sumNegateOperandNeedsParens($operand)) {
							$stack[] = [0, ')'];
							$stack[] = [1, $operand, 1, false, $depth + 2, $innerDepth + 1];
							$stack[] = [0, ' - ('];
						} else {
							$stack[] = [1, $operand, 1, false, $depth + 2, $innerDepth];
							$stack[] = [0, ' - '];
						}
					} else {
						$stack[] = [1, $child, 1, false, $depth + 1, $innerDepth];
						$stack[] = [0, ' + '];
					}
				}
				continue;
			}

			$chunk = (string) $value;
			$totalBytes += strlen($chunk);
			if ($maxBytes !== null && $totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum serialized size of %d bytes.', $maxBytes));
			}
			$parts[] = $chunk;
		}

		return count($parts) === 1 ? $parts[0] : implode('', $parts);
	}

	private static function isNegativeFloat(float $value): bool
	{
		return $value < 0.0 || ($value === 0.0 && fdiv(1.0, $value) < 0.0);
	}

	public function __clone(): void
	{
		$visits = 0;
		$cloned = [];
		foreach ($this->values->values as $child) {
			$cloned[] = self::cloneTreeIteratively($child, $visits, 1);
		}
		$this->values = CSSNumericArray::fromTrustedList($cloned);
		if ($this instanceof CSSMathNegate || $this instanceof CSSMathInvert) {
			$this->value = $cloned[0];
		} elseif ($this instanceof CSSMathClamp) {
			$this->value = $cloned[1];
		}
	}

	public function clone(): static
	{
		return clone $this;
	}
}
