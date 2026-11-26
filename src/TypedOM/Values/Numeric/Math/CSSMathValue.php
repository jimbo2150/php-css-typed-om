<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Base class for CSS Typed OM math expressions (`calc()`, `min()`, `max()`, `clamp()`, and CSS Values 4 functions).
 *
 * @property-read ?string $operator
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
	use MultiValueTrait;

	public const ?string operator = null;

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

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes): string
	{
		return self::serializeMath($this, $maxBytes);
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
				$needsParens = $inner instanceof CSSMathNegate
					|| ($inner instanceof CSSUnitValue && $inner->value < 0.0)
					|| $inner instanceof CSSMathConstant
					|| $inner instanceof CSSMathFunction
					|| $inner instanceof CSSMathMin
					|| $inner instanceof CSSMathMax
					|| $inner instanceof CSSMathClamp;
				$childDepth = $needsParens ? $mathDepth + 1 : $mathDepth;
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
						} elseif ($operand instanceof CSSMathNegate
							|| ($operand instanceof CSSUnitValue && $operand->value < 0.0)
							|| $operand instanceof CSSMathConstant
						) {
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

	private static function serializeMath(
		CSSNumericValue $rootValue,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): string {
		$parts = [];
		$totalBytes = 0;
		$visits = 0;
		// Frame [0 = EMIT_STR, string] | [1 = EVAL_NODE, CSSNumericValue, parentPrecedence, isRoot, depth]
		$stack = [[1, $rootValue, 0, true, 0]];

		$emit = static function (string $chunk) use (&$parts, &$totalBytes, $maxBytes): void {
			$totalBytes += strlen($chunk);
			if ($maxBytes !== null && $totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf(
					'CSS math expression exceeds maximum serialized size of %d bytes.',
					$maxBytes,
				));
			}
			$parts[] = $chunk;
		};

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 0) {
				$emit($frame[1]);
				continue;
			}

			[, $value, $parentPrecedence, $root, $depth] = $frame;
			if ($depth > self::MAX_MATH_TREE_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression tree depth of %d exceeded.', self::MAX_MATH_TREE_DEPTH));
			}
			if (++$visits > self::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
			}

			if (!$value instanceof self) {
				$emit((string) $value);
				continue;
			}

			if ($value instanceof CSSMathConstant) {
				$kw = $value->constant->value;
				$emit($root ? 'calc(' . $kw . ')' : $kw);
				continue;
			}

			if ($value instanceof CSSMathFunction) {
				$args = $value->values->values;
				$emit($value->function->value . '(');
				$stack[] = [0, ')'];
				for ($i = count($args) - 1; $i >= 0; $i--) {
					$stack[] = [1, $args[$i], 0, false, $depth + 1];
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
				$name = $value instanceof CSSMathMin ? 'min' : ($value instanceof CSSMathMax ? 'max' : 'clamp');
				$args = $value->values->values;
				$emit($name . '(');
				$stack[] = [0, ')'];
				for ($i = count($args) - 1; $i >= 0; $i--) {
					$stack[] = [1, $args[$i], 0, false, $depth + 1];
					if ($i > 0) {
						$stack[] = [0, ', '];
					}
				}
				continue;
			}

			if ($value instanceof CSSMathNegate) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					$emit('calc(0)');
					continue;
				}
				$needsParens = $inner instanceof CSSMathNegate
					|| ($inner instanceof CSSUnitValue && $inner->value < 0.0)
					|| $inner instanceof CSSMathConstant
					|| $inner instanceof CSSMathFunction
					|| $inner instanceof CSSMathMin
					|| $inner instanceof CSSMathMax
					|| $inner instanceof CSSMathClamp;
				if ($root) {
					$emit('calc(');
					$stack[] = [0, ')'];
				}
				if ($needsParens) {
					$emit('-(');
					$stack[] = [0, ')'];
				} else {
					$emit('-');
				}
				$stack[] = [1, $inner, 3, false, $depth + 1];
				continue;
			}

			if ($value instanceof CSSMathInvert) {
				$inner = $value->values->values[0] ?? null;
				if ($inner === null) {
					$emit('calc(1)');
					continue;
				}
				if ($root) {
					$emit('calc(1 / ');
					$stack[] = [0, ')'];
				} elseif ($parentPrecedence >= 3) {
					$emit('(1 / ');
					$stack[] = [0, ')'];
				} else {
					$emit('1 / ');
				}
				$stack[] = [1, $inner, 3, false, $depth + 1];
				continue;
			}

			if ($value instanceof CSSMathProduct) {
				$values = $value->values->values;
				$count = count($values);
				if ($count === 0) {
					$out = '0';
					if ($parentPrecedence > 2) {
						$out = '(' . $out . ')';
					}
					$emit($root ? 'calc(' . $out . ')' : $out);
					continue;
				}
				if ($root) {
					$emit('calc(');
					$stack[] = [0, ')'];
				}
				if ($parentPrecedence > 2) {
					$emit('(');
					$stack[] = [0, ')'];
				}

				if ($values[0] instanceof CSSMathInvert) {
					$first = $values[0]->values->values[0] ?? null;
					for ($i = $count - 1; $i >= 1; $i--) {
						$child = $values[$i];
						if ($child instanceof CSSMathInvert) {
							$operand = $child->values->values[0] ?? null;
							if ($operand === null) {
								$stack[] = [0, ' / 1'];
							} else {
								$stack[] = [1, $operand, 3, false, $depth + 1];
								$stack[] = [0, ' / '];
							}
						} else {
							$stack[] = [1, $child, 2, false, $depth + 1];
							$stack[] = [0, ' * '];
						}
					}
					$emit('1 / ');
					if ($first === null) {
						$emit('1');
					} else {
						$stack[] = [1, $first, 3, false, $depth + 1];
					}
				} else {
					for ($i = $count - 1; $i >= 0; $i--) {
						$child = $values[$i];
						if ($child instanceof CSSMathInvert) {
							$operand = $child->values->values[0] ?? null;
							if ($operand === null) {
								$stack[] = [0, $i > 0 ? ' / 1' : '1'];
							} else {
								$stack[] = [1, $operand, 3, false, $depth + 1];
								if ($i > 0) {
									$stack[] = [0, ' / '];
								}
							}
						} else {
							$stack[] = [1, $child, 2, false, $depth + 1];
							if ($i > 0) {
								$stack[] = [0, ' * '];
							}
						}
					}
				}
				continue;
			}

			if ($value instanceof CSSMathSum) {
				$values = $value->values->values;
				$count = count($values);
				if ($count === 0) {
					$out = '0';
					if ($parentPrecedence >= 1) {
						$out = '(' . $out . ')';
					}
					$emit($root ? 'calc(' . $out . ')' : $out);
					continue;
				}
				if ($root) {
					$emit('calc(');
					$stack[] = [0, ')'];
				}
				if ($parentPrecedence >= 1) {
					$emit('(');
					$stack[] = [0, ')'];
				}

				for ($i = $count - 1; $i >= 0; $i--) {
					$child = $values[$i];
					if ($i === 0) {
						$stack[] = [1, $child, 1, false, $depth + 1];
						continue;
					}
					if ($child instanceof CSSUnitValue && $child->value < 0.0) {
						$symbol = $child->unitObj?->toString() ?? '';
						$stack[] = [0, ' - ' . (-$child->value) . $symbol];
					} elseif ($child instanceof CSSMathNegate) {
						$operand = $child->values->values[0] ?? null;
						if ($operand === null) {
							$stack[] = [0, ' - 0'];
						} elseif ($operand instanceof CSSMathNegate
							|| ($operand instanceof CSSUnitValue && $operand->value < 0.0)
							|| $operand instanceof CSSMathConstant
						) {
							$stack[] = [0, ')'];
							$stack[] = [1, $operand, 1, false, $depth + 1];
							$stack[] = [0, ' - ('];
						} else {
							$stack[] = [1, $operand, 1, false, $depth + 1];
							$stack[] = [0, ' - '];
						}
					} else {
						$stack[] = [1, $child, 1, false, $depth + 1];
						$stack[] = [0, ' + '];
					}
				}
				continue;
			}

			$emit((string) $value);
		}

		return count($parts) === 1 ? $parts[0] : implode('', $parts);
	}

	public function __clone(): void
	{
		$visits = 0;
		$cloned = [];
		foreach ($this->values->values as $child) {
			$cloned[] = self::cloneTreeIteratively($child, $visits, 1);
		}
		$this->values = new CSSNumericArray($cloned);
		if ($this->cachedNumericType !== null) {
			$this->cachedNumericType = clone $this->cachedNumericType;
		}
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
