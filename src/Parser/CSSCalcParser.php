<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;

/**
 * Shunting-yard parser for CSS `calc()` expressions and bare `<calc-sum>` arguments.
 */
class CSSCalcParser
{
	private const int GROUP_SUM = 1;
	private const int GROUP_PRODUCT = 2;

	private const int T_NUMBER = 1;
	private const int T_FUNC = 2;
	private const int T_OP = 3;
	private const int T_LPAREN = 4;
	private const int T_RPAREN = 5;
	private const int T_UNKNOWN = 6;

	/** @var array<string, array{precedence: int, left: bool}> */
	private const array OPERATORS = [
		'+' => ['precedence' => 1, 'left' => true],
		'-' => ['precedence' => 1, 'left' => true],
		'*' => ['precedence' => 2, 'left' => true],
		'/' => ['precedence' => 2, 'left' => true],
		'u+' => ['precedence' => 3, 'left' => false],
		'u-' => ['precedence' => 3, 'left' => false],
	];

	/**
	 * Parses a CSS `calc(...)` expression into a CSSNumericValue / CSSMathValue tree.
	 *
	 * @throws InvalidArgumentException When the expression is malformed, dimensionally incompatible, or exceeds maxMathDepth
	 */
	public static function parse(string $calcString, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($calcString) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS calc() expression exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}
		if (CSSSyntaxScanner::hasControlCharacters($calcString)) {
			throw new InvalidArgumentException('CSS calc() expression contains disallowed control characters.');
		}

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($calcString);
		$len = strlen($trimmed);
		if ($len < 5 || substr_compare($trimmed, 'calc(', 0, 5, true) !== 0) {
			throw new InvalidArgumentException('Expected a calc() expression.');
		}
		$close = CSSSyntaxScanner::findMatchingDelimiter($trimmed, 4, $len);
		if ($close === null || ($close < $len - 1 && $trimmed[$close + 1] === ')')) {
			throw new InvalidArgumentException('Mismatched parentheses.');
		}
		if ($close !== $len - 1) {
			throw new InvalidArgumentException('Invalid expression.');
		}

		return self::parseSliceIterative($trimmed, 5, $close, $options, $depth, 2, true);
	}

	/**
	 * Parses an inner `<calc-sum>` expression (without the outer `calc(...)` wrapper).
	 *
	 * @throws InvalidArgumentException When the expression is malformed, dimensionally incompatible, or exceeds limits
	 */
	public static function parseExpression(string $expression, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($expression) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS math expression exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}
		if (CSSSyntaxScanner::hasControlCharacters($expression)) {
			throw new InvalidArgumentException('CSS math expression contains disallowed control characters.');
		}

		return self::parseSliceIterative($expression, 0, strlen($expression), $options, $depth, 2, true);
	}

	/**
	 * @internal Iterative slice-based entry point for unit values, `calc()`, `min()`, `max()`, and `clamp()`.
	 * @throws CSSParserLimitException When resource limits are exceeded
	 */
	public static function parseUnitOrMathSlice(
		string $source,
		int $start,
		int $end,
		CSSParserOptions $options,
		int $depth,
		bool $throwOnInvalid,
	): ?CSSNumericValue {
		if ($throwOnInvalid) {
			return self::parseSliceIterative($source, $start, $end, $options, $depth, 0, true);
		}
		try {
			return self::parseSliceIterative($source, $start, $end, $options, $depth, 0, false);
		} catch (CSSParserLimitException $e) {
			throw $e;
		} catch (InvalidArgumentException) {
			return null;
		}
	}

	/**
	 * Modes: 0 = auto (unit/calc/min/max/clamp), 2 = inner calc expression.
	 */
	private static function parseSliceIterative(
		string $source,
		int $rootStart,
		int $rootEnd,
		CSSParserOptions $options,
		int $rootDepth,
		int $rootMode,
		bool $throwOnInvalid,
	): ?CSSNumericValue {
		$matchingParens = null;
		$totalTokens = 0;
		$totalNodes = 0;
		$slots = [];
		$nextSlot = 1;
		$workStack = [[0, $rootStart, $rootEnd, $rootDepth, $rootMode, 0]];

		while ($workStack !== []) {
			$frame = array_pop($workStack);
			$kind = $frame[0];

			if ($kind === 1) {
				[, $outputQueue, $targetSlot] = $frame;
				$valueStack = [];
				foreach ($outputQueue as $token) {
					$type = $token[0];
					if ($type === self::T_OP) {
						$op = $token[1];
						if ($op === 'u-' || $op === 'u+') {
							if ($valueStack === []) {
								throw new InvalidArgumentException('Insufficient operands for operator: ' . substr($op, 1));
							}
							$value = self::materializeValue(array_pop($valueStack), $totalNodes);
							if ($op === 'u-') {
								if ($value instanceof CSSMathNegate) {
									$valueStack[] = $value->values->values[0];
								} else {
									if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
										throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
									}
									$valueStack[] = new CSSMathNegate([$value]);
								}
							} else {
								$valueStack[] = $value;
							}
							continue;
						}
						if (count($valueStack) < 2) {
							throw new InvalidArgumentException('Insufficient operands for operator: ' . $op);
						}
						$operand2 = self::materializeValue(array_pop($valueStack), $totalNodes);
						$rawLeft = array_pop($valueStack);

						if ($op === '+' || $op === '-') {
							if ($op === '-') {
								if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
									throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
								}
								$rhs = new CSSMathNegate([$operand2]);
							} else {
								$rhs = $operand2;
							}
							if (is_array($rawLeft) && $rawLeft[0] === self::GROUP_SUM) {
								$rawLeft[2] = CSSNumericValue::combineAdditiveTypes($rawLeft[2], $rhs->getNumericTypeInternal());
								$rawLeft[1][] = $rhs;
								$valueStack[] = $rawLeft;
							} elseif ($rawLeft instanceof CSSMathSum) {
								$merged = CSSNumericValue::combineAdditiveTypes($rawLeft->getNumericTypeInternal(), $rhs->getNumericTypeInternal());
								$list = $rawLeft->values->values;
								$list[] = $rhs;
								$valueStack[] = [self::GROUP_SUM, $list, $merged];
							} else {
								$left = self::materializeValue($rawLeft, $totalNodes);
								$merged = CSSNumericValue::combineAdditiveTypes($left->getNumericTypeInternal(), $rhs->getNumericTypeInternal());
								$valueStack[] = [self::GROUP_SUM, [$left, $rhs], $merged];
							}
						} else {
							if ($op === '/') {
								if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
									throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
								}
								$rhs = new CSSMathInvert([$operand2]);
							} else {
								$rhs = $operand2;
							}
							if (is_array($rawLeft) && $rawLeft[0] === self::GROUP_PRODUCT) {
								$rawLeft[2] = $rawLeft[2]->multiply($rhs->getNumericTypeInternal());
								$rawLeft[1][] = $rhs;
								$valueStack[] = $rawLeft;
							} elseif ($rawLeft instanceof CSSMathProduct) {
								$mergedType = $rawLeft->getNumericTypeInternal()->multiply($rhs->getNumericTypeInternal());
								$list = $rawLeft->values->values;
								$list[] = $rhs;
								$valueStack[] = [self::GROUP_PRODUCT, $list, $mergedType];
							} else {
								$left = self::materializeValue($rawLeft, $totalNodes);
								$mergedType = $left->getNumericTypeInternal()->multiply($rhs->getNumericTypeInternal());
								$valueStack[] = [self::GROUP_PRODUCT, [$left, $rhs], $mergedType];
							}
						}
					} elseif ($type === self::T_NUMBER) {
						if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
						}
						/** @var float $numValue */
						$numValue = $token[2];
						/** @var CSSUnitEnum $unitEnum */
						$unitEnum = $token[3];
						$valueStack[] = new CSSUnitValue($numValue, $unitEnum);
					} else {
						/** @var int $childSlot */
						$childSlot = $token[1];
						$childVal = $slots[$childSlot] ?? null;
						unset($slots[$childSlot]);
						if (!$childVal instanceof CSSNumericValue) {
							throw new InvalidArgumentException('Invalid numeric function expression.');
						}
						$valueStack[] = $childVal;
					}
				}

				if (count($valueStack) !== 1) {
					throw new InvalidArgumentException('Invalid expression.');
				}
				$slots[$targetSlot] = self::materializeValue($valueStack[0], $totalNodes);
				continue;
			}

			if ($kind === 2) {
				[, $function, $argSlots, $targetSlot] = $frame;
				$arguments = [];
				foreach ($argSlots as $as) {
					$val = $slots[$as] ?? null;
					unset($slots[$as]);
					if (!$val instanceof CSSNumericValue) {
						throw new InvalidArgumentException('Invalid argument in ' . $function . '().');
					}
					$arguments[] = $val;
				}
				if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
				}
				$slots[$targetSlot] = match ($function) {
					'min' => new CSSMathMin($arguments),
					'max' => new CSSMathMax($arguments),
					'clamp' => new CSSMathClamp($arguments[0], $arguments[1], $arguments[2]),
				};
				continue;
			}

			// kind === 0: PARSE slice
			[, $start, $end, $depth, $mode, $targetSlot] = $frame;
			self::trimSlice($source, $start, $end);
			if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
			}

			if ($mode === 0) {
				if ($start >= $end) {
					if ($throwOnInvalid) {
						throw new InvalidArgumentException('Invalid CSS numeric value.');
					}
					return null;
				}
				$c0 = $source[$start];
				$sliceLen = $end - $start;

				if ((($c0 >= '0' && $c0 <= '9') || $c0 === '.' || $c0 === '+' || $c0 === '-')
					&& preg_match('/\G([+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?)([a-zA-Z%]*)/', $source, $matches, 0, $start) === 1
					&& strlen($matches[0]) === $sliceLen
				) {
					$value = (float) $matches[1];
					if (!is_finite($value)) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('CSS numeric value must be finite: ' . substr($source, $start, $sliceLen));
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
					if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}
					$slots[$targetSlot] = new CSSUnitValue($value, $unit);
					continue;
				}

				if (($c0 === 'c' || $c0 === 'C')
					&& $sliceLen >= 6
					&& substr_compare($source, 'calc(', $start, 5, true) === 0
				) {
					$matchingParens ??= self::buildMatchingParens($source, $rootStart, $rootEnd);
					$close = $matchingParens[$start + 4] ?? null;
					if ($close !== $end - 1) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Invalid CSS calc() expression.');
						}
						return null;
					}
					$start += 5;
					$end = $close;
					self::trimSlice($source, $start, $end);
					$mode = 2;
				}
			}

			if ($mode === 0) {
				$c0 = $source[$start];
				$sliceLen = $end - $start;
				$fnName = null;
				$openOffset = -1;
				if ($sliceLen >= 5 && ($c0 === 'm' || $c0 === 'M')) {
					if (substr_compare($source, 'min(', $start, 4, true) === 0) {
						$fnName = 'min';
						$openOffset = $start + 3;
					} elseif (substr_compare($source, 'max(', $start, 4, true) === 0) {
						$fnName = 'max';
						$openOffset = $start + 3;
					}
				} elseif ($sliceLen >= 7 && ($c0 === 'c' || $c0 === 'C') && substr_compare($source, 'clamp(', $start, 6, true) === 0) {
					$fnName = 'clamp';
					$openOffset = $start + 5;
				}

				if ($fnName !== null) {
					$matchingParens ??= self::buildMatchingParens($source, $rootStart, $rootEnd);
					$close = $matchingParens[$openOffset] ?? null;
					if ($close !== $end - 1) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Invalid CSS math function.');
						}
						return null;
					}

					$argRanges = [];
					$argPos = $openOffset + 1;
					while ($argPos <= $close) {
						$comma = CSSSyntaxScanner::findTopLevel($source, ',', $argPos, $close);
						$segEnd = $comma ?? $close;
						$argRanges[] = [$argPos, $segEnd];
						if (($totalNodes + count($argRanges)) > CSSNumericValue::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
						}
						if ($comma === null) {
							break;
						}
						$argPos = $comma + 1;
					}

					if (($fnName === 'clamp' && count($argRanges) !== 3) || ($fnName !== 'clamp' && $argRanges === [])) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Invalid argument count for ' . $fnName . '().');
						}
						return null;
					}
					if ($fnName !== 'clamp' && $options->maxMathDepth !== null) {
						$maxArgs = max(1024, $options->maxMathDepth * 16);
						if (count($argRanges) > $maxArgs) {
							throw new CSSParserLimitException(sprintf('CSS %s() argument count exceeds maximum limit of %d.', $fnName, $maxArgs));
						}
					}

					$argSlots = [];
					$argJobs = [];
					foreach ($argRanges as [$as, $ae]) {
						self::trimSliceWithComments($source, $as, $ae);
						if ($as >= $ae) {
							throw new InvalidArgumentException('Empty argument in ' . $fnName . '().');
						}
						$argSlot = $nextSlot++;
						$argSlots[] = $argSlot;
						$isExpr = self::sliceContainsMathExpression($source, $as, $ae);
						$argJobs[] = [0, $as, $ae, $depth + 1, $isExpr ? 2 : 0, $argSlot];
					}
					$workStack[] = [2, $fnName, $argSlots, $targetSlot];
					for ($i = count($argJobs) - 1; $i >= 0; $i--) {
						$workStack[] = $argJobs[$i];
					}
					continue;
				}

				if ($throwOnInvalid) {
					throw new InvalidArgumentException('Invalid CSS numeric value.');
				}
				return null;
			}

			// mode === 2: inner <calc-sum> expression
			$tokens = self::tokenizeSlice($source, $start, $end, $options, $depth, $totalTokens, $matchingParens, $rootStart, $rootEnd);
			$outputQueue = [];
			$operatorStack = [];
			$parenDepth = 0;
			$childFuncJobs = [];

			foreach ($tokens as $token) {
				$type = $token[0];
				if ($type === self::T_NUMBER) {
					$outputQueue[] = $token;
				} elseif ($type === self::T_FUNC) {
					$fnSlot = $nextSlot++;
					$outputQueue[] = [self::T_FUNC, $fnSlot];
					$childFuncJobs[] = [0, $token[1], $token[2], $depth + $parenDepth + 1, 0, $fnSlot];
				} elseif ($type === self::T_OP) {
					$op1 = $token[1];
					$op1Meta = self::OPERATORS[$op1];
					while ($operatorStack !== []) {
						$top = end($operatorStack);
						if ($top[0] !== self::T_OP) {
							break;
						}
						$op2 = $top[1];
						$op2Meta = self::OPERATORS[$op2];
						if (
							($op1Meta['left'] && $op1Meta['precedence'] <= $op2Meta['precedence']) ||
							(!$op1Meta['left'] && $op1Meta['precedence'] < $op2Meta['precedence'])
						) {
							$outputQueue[] = array_pop($operatorStack);
						} else {
							break;
						}
					}
					$operatorStack[] = $token;
				} elseif ($type === self::T_LPAREN) {
					$parenDepth++;
					if ($options->maxMathDepth !== null && ($depth + $parenDepth) > $options->maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
					}
					$operatorStack[] = $token;
				} elseif ($type === self::T_RPAREN) {
					while ($operatorStack !== [] && end($operatorStack)[0] !== self::T_LPAREN) {
						$outputQueue[] = array_pop($operatorStack);
					}
					if ($operatorStack === []) {
						throw new InvalidArgumentException('Mismatched parentheses.');
					}
					array_pop($operatorStack);
					$parenDepth--;
				} else {
					throw new InvalidArgumentException('Unknown token: ' . $token[1]);
				}
			}

			while ($operatorStack !== []) {
				$op = array_pop($operatorStack);
				if ($op[0] === self::T_LPAREN || $op[0] === self::T_RPAREN) {
					throw new InvalidArgumentException('Mismatched parentheses.');
				}
				$outputQueue[] = $op;
			}

			$workStack[] = [1, $outputQueue, $targetSlot];
			for ($i = count($childFuncJobs) - 1; $i >= 0; $i--) {
				$workStack[] = $childFuncJobs[$i];
			}
		}

		return $slots[0] ?? null;
	}

	private static function trimSlice(string $source, int &$start, int &$end): void
	{
		while ($start < $end && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$start])) {
			$start++;
		}
		while ($end > $start && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$end - 1])) {
			$end--;
		}
	}

	private static function trimSliceWithComments(string $source, int &$start, int &$end): void
	{
		while ($start < $end) {
			$start += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $start, $end - $start);
			if ($start + 1 < $end && $source[$start] === '/' && $source[$start + 1] === '*') {
				$cEnd = strpos($source, '*/', $start + 2);
				if ($cEnd === false || $cEnd + 1 >= $end) {
					throw new InvalidArgumentException('Unclosed comment in CSS math expression.');
				}
				$start = $cEnd + 2;
				continue;
			}
			break;
		}
		if ($start >= $end) {
			return;
		}

		$firstComment = strpos($source, '/*', $start);
		if ($firstComment === false || $firstComment >= $end) {
			while ($end > $start && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$end - 1])) {
				$end--;
			}
			return;
		}

		$lastContentEnd = $start;
		$pos = $start;
		while ($pos < $end) {
			$pos += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $pos, $end - $pos);
			if ($pos >= $end) {
				break;
			}
			if ($pos + 1 < $end && $source[$pos] === '/' && $source[$pos + 1] === '*') {
				$cEnd = strpos($source, '*/', $pos + 2);
				if ($cEnd === false || $cEnd + 1 >= $end) {
					throw new InvalidArgumentException('Unclosed comment in CSS math expression.');
				}
				$pos = $cEnd + 2;
				continue;
			}
			$nextComment = strpos($source, '/*', $pos);
			$chunkEnd = ($nextComment === false || $nextComment >= $end) ? $end : $nextComment;
			$trimmedEnd = $chunkEnd;
			while ($trimmedEnd > $pos && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$trimmedEnd - 1])) {
				$trimmedEnd--;
			}
			if ($trimmedEnd > $pos) {
				$lastContentEnd = $trimmedEnd;
			}
			$pos = $chunkEnd;
		}
		$end = $lastContentEnd;
	}

	private static function sliceContainsMathExpression(string $source, int $start, int $end): bool
	{
		if ($start >= $end) {
			return false;
		}
		$match = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, '+-*/', $start, $end);
		if ($match !== null) {
			if (($source[$start] === '+' || $source[$start] === '-')
				&& CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, '+-*/', $start + 1, $end) === null
				&& strcspn($source, '(', $start, $end - $start) === ($end - $start)
			) {
				return false;
			}
			return true;
		}

		return $source[$start] === '(';
	}

	/**
	 * @param CSSNumericValue|array{0: int, 1: list<CSSNumericValue>, 2: CSSNumericType} $entry
	 */
	private static function materializeValue(CSSNumericValue|array $entry, int &$totalNodes): CSSNumericValue
	{
		if ($entry instanceof CSSNumericValue) {
			return $entry;
		}
		if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf(
				'Maximum CSS math expression node count of %d exceeded.',
				CSSNumericValue::MAX_MATH_NODES,
			));
		}
		if ($entry[0] === self::GROUP_SUM) {
			return CSSMathSum::fromValidated($entry[1], $entry[2]);
		}

		return CSSMathProduct::fromValidated($entry[1], $entry[2]);
	}

	/** @return array<int, int> */
	private static function buildMatchingParens(string $source, int $start, int $end): array
	{
		$matchingParens = [];
		$parenStack = [];
		$scan = $start;
		while ($scan < $end) {
			$scan += strcspn($source, '()/', $scan, $end - $scan);
			if ($scan >= $end) {
				break;
			}
			$ch = $source[$scan];
			if ($ch === '/') {
				if ($scan + 1 < $end && $source[$scan + 1] === '*') {
					$cEnd = strpos($source, '*/', $scan + 2);
					if ($cEnd === false || $cEnd + 1 >= $end) {
						break;
					}
					$scan = $cEnd + 2;
					continue;
				}
				$scan++;
				continue;
			}
			if ($ch === '(') {
				$parenStack[] = $scan;
			} elseif ($parenStack !== []) {
				$matchingParens[array_pop($parenStack)] = $scan;
			}
			$scan++;
		}

		return $matchingParens;
	}

	/**
	 * @param ?array<int, int> $matchingParens
	 * @return list<array{0: int, 1: mixed, 2?: mixed, 3?: CSSUnitEnum}>
	 */
	private static function tokenizeSlice(
		string $expression,
		int $sliceStart,
		int $sliceEnd,
		CSSParserOptions $options,
		int $depth,
		int &$totalTokens,
		?array &$matchingParens,
		int $rootStart,
		int $rootEnd,
	): array {
		$tokens = [];
		$offset = $sliceStart;
		$expectsOperand = true;
		$hadWhitespace = false;
		$unaryRun = 0;
		$maxTokens = $options->maxMathDepth !== null ? max(4096, $options->maxMathDepth * 64) : null;
		$numberPattern = '/\G[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:[eE][+-]?\d+)?([a-zA-Z%]*)/';

		while ($offset < $sliceEnd) {
			$ws = strspn($expression, CSSSyntaxScanner::CSS_WHITESPACE, $offset, $sliceEnd - $offset);
			if ($ws > 0) {
				$offset += $ws;
				$hadWhitespace = true;
				if ($offset >= $sliceEnd) {
					break;
				}
			}

			$character = $expression[$offset];

			if ($character === '/' && ($offset + 1 < $sliceEnd) && $expression[$offset + 1] === '*') {
				$commentEnd = strpos($expression, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $sliceEnd) {
					throw new InvalidArgumentException('Unclosed comment in calc() expression.');
				}
				$offset = $commentEnd + 2;
				continue;
			}

			if ($maxTokens !== null && count($tokens) >= $maxTokens) {
				throw new CSSParserLimitException('CSS calc() expression exceeds maximum token limit.');
			}
			if (++$totalTokens > CSSNumericValue::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf(
					'Maximum CSS math expression node count of %d exceeded.',
					CSSNumericValue::MAX_MATH_NODES,
				));
			}

			if (!$expectsOperand && ($character === '+' || $character === '-')) {
				if (!$hadWhitespace || ($offset + 1 < $sliceEnd && !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $expression[$offset + 1]) && $expression[$offset + 1] !== ')')) {
					throw new InvalidArgumentException('Binary + and - operators in calc() require surrounding whitespace.');
				}
				$tokens[] = [self::T_OP, $character];
				$offset++;
				$expectsOperand = true;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if (!$expectsOperand && ($character === '*' || $character === '/')) {
				$tokens[] = [self::T_OP, $character];
				$offset++;
				$expectsOperand = true;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if (($character === '+' || $character === '-') && $expectsOperand) {
				if (preg_match($numberPattern, $expression, $number, 0, $offset) && ($offset + strlen($number[0])) <= $sliceEnd) {
					$tokens[] = self::makeNumberToken($number[0], $number[1]);
					$offset += strlen($number[0]);
					$expectsOperand = false;
					$hadWhitespace = false;
					$unaryRun = 0;
					continue;
				}
				$unaryRun++;
				if ($options->maxMathDepth !== null && ($depth + $unaryRun) > $options->maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
				}
				$tokens[] = [self::T_OP, $character === '-' ? 'u-' : 'u+'];
				$offset++;
				$hadWhitespace = false;
				continue;
			}

			if ((($character >= '0' && $character <= '9') || $character === '.')
				&& preg_match($numberPattern, $expression, $number, 0, $offset)
				&& ($offset + strlen($number[0])) <= $sliceEnd
			) {
				$tokens[] = self::makeNumberToken($number[0], $number[1]);
				$offset += strlen($number[0]);
				$expectsOperand = false;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if ($character === '(') {
				$tokens[] = [self::T_LPAREN, '('];
				$offset++;
				$expectsOperand = true;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if ($character === ')') {
				if ($expectsOperand) {
					throw new InvalidArgumentException('Unexpected closing parenthesis in calc() expression.');
				}
				$tokens[] = [self::T_RPAREN, ')'];
				$offset++;
				$expectsOperand = false;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if ($character === '+' || $character === '-' || $character === '*' || $character === '/') {
				$tokens[] = [self::T_OP, $character];
				$offset++;
				$expectsOperand = true;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			if ((($character >= 'a' && $character <= 'z') || ($character >= 'A' && $character <= 'Z'))
				&& preg_match('/\G[a-zA-Z][a-zA-Z0-9-]*/', $expression, $identifier, 0, $offset)
				&& ($offset + strlen($identifier[0])) <= $sliceEnd
			) {
				$name = $identifier[0];
				$afterName = $offset + strlen($name);
				if ($afterName < $sliceEnd && $expression[$afterName] === '(') {
					$matchingParens ??= self::buildMatchingParens($expression, $rootStart, $rootEnd);
					$close = $matchingParens[$afterName] ?? null;
					if ($close === null || $close >= $sliceEnd) {
						throw new InvalidArgumentException('Mismatched parentheses.');
					}
					$tokens[] = [self::T_FUNC, $offset, $close + 1];
					$offset = $close + 1;
					$expectsOperand = false;
					$hadWhitespace = false;
					$unaryRun = 0;
					continue;
				}
				$tokens[] = [self::T_UNKNOWN, $name];
				$offset = $afterName;
				$expectsOperand = false;
				$hadWhitespace = false;
				$unaryRun = 0;
				continue;
			}

			$start = $offset++;
			while ($offset < $sliceEnd && !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $expression[$offset])
				&& $expression[$offset] !== '(' && $expression[$offset] !== ')'
				&& $expression[$offset] !== '+' && $expression[$offset] !== '-'
				&& $expression[$offset] !== '*' && $expression[$offset] !== '/') {
				$offset++;
			}
			$tokens[] = [self::T_UNKNOWN, substr($expression, $start, $offset - $start)];
			$expectsOperand = false;
			$hadWhitespace = false;
			$unaryRun = 0;
		}

		return $tokens;
	}

	/** @return array{0: int, 1: string, 2: float, 3: CSSUnitEnum} */
	private static function makeNumberToken(string $raw, string $unitSymbol): array
	{
		$numStr = $unitSymbol === '' ? $raw : substr($raw, 0, strlen($raw) - strlen($unitSymbol));
		$float = (float) $numStr;
		if (!is_finite($float)) {
			throw new InvalidArgumentException('CSS numeric value must be finite: ' . $raw);
		}
		$unit = CSSUnitEnum::tryFromSymbol($unitSymbol);
		if ($unit === null) {
			throw new InvalidArgumentException('Unsupported CSS unit: ' . $unitSymbol);
		}

		return [self::T_NUMBER, $raw, $float, $unit];
	}
}
