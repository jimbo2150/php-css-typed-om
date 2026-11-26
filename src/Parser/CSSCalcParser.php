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
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstantEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunctionEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSimplifier;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSRoundingStrategy;

/**
 * Shunting-yard parser for CSS `calc()` expressions and CSS Values Level 4 math functions.
 */
class CSSCalcParser
{
	/** @var array<string, true> All recognized CSS Values Level 4 math function names (lowercase) */
	public const array MATH_FUNCTIONS = [
		'calc' => true,
		'min' => true,
		'max' => true,
		'clamp' => true,
		'round' => true,
		'mod' => true,
		'rem' => true,
		'sin' => true,
		'cos' => true,
		'tan' => true,
		'asin' => true,
		'acos' => true,
		'atan' => true,
		'atan2' => true,
		'pow' => true,
		'sqrt' => true,
		'hypot' => true,
		'log' => true,
		'exp' => true,
		'abs' => true,
		'sign' => true,
	];

	private const int GROUP_SUM = 1;
	private const int GROUP_PRODUCT = 2;

	private const int T_NUMBER = 1;
	private const int T_FUNC = 2;
	private const int T_OP = 3;
	private const int T_LPAREN = 4;
	private const int T_RPAREN = 5;
	private const int T_UNKNOWN = 6;
	private const int T_CONST = 7;

	/** @var array<string, array{precedence: int, left: bool}> */
	private const array OPERATORS = [
		'+' => ['precedence' => 1, 'left' => true],
		'-' => ['precedence' => 1, 'left' => true],
		'*' => ['precedence' => 2, 'left' => true],
		'/' => ['precedence' => 2, 'left' => true],
		'u+' => ['precedence' => 3, 'left' => false],
		'u-' => ['precedence' => 3, 'left' => false],
	];

	private static function validateEntryAndLimits(string $expr, ?CSSParserOptions $options, int $depth, string $sizeError): CSSParserOptions
	{
		if ($depth < 0 || $depth === PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($expr) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf($sizeError, $options->maxBytes));
		}
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}
		if (CSSSyntaxScanner::hasControlCharacters($expr)) {
			throw new InvalidArgumentException($sizeError === 'CSS math expression exceeds maximum size of %d bytes.'
				? 'CSS math expression contains disallowed control characters.'
				: 'CSS calc() expression contains disallowed control characters.');
		}

		return $options;
	}

	/**
	 * Parses a CSS `calc(...)` expression into a CSSNumericValue / CSSMathValue tree.
	 *
	 * @throws InvalidArgumentException When the expression is malformed, dimensionally incompatible, or exceeds maxMathDepth
	 */
	public static function parse(string $calcString, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue
	{
		$options = self::validateEntryAndLimits($calcString, $options, $depth, 'CSS calc() expression exceeds maximum size of %d bytes.');

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($calcString);
		$len = strlen($trimmed);
		$pos = CSSSyntaxScanner::skipWhitespaceAndComments($trimmed, 0, $len);
		if ($pos === null) {
			throw new InvalidArgumentException('Unclosed comment in CSS math expression.');
		}
		if (($len - $pos) < 5) {
			throw new InvalidArgumentException('Expected a calc() expression.');
		}
		if (substr_compare($trimmed, 'calc(', $pos, 5, true) === 0) {
			$open = $pos + 4;
		} else {
			$open = strpos($trimmed, '(', $pos);
			$ident = $open !== false ? CSSSyntaxScanner::decodeIdentAt($trimmed, $pos, $len) : null;
			if ($open === false || $ident === null || $ident['end'] !== $open || strtolower($ident['ident']) !== 'calc') {
				throw new InvalidArgumentException('Expected a calc() expression.');
			}
		}

		// C-level fast path: skip findMatchingDelimiter() when the body has no nested parens or comments and ends with `)`.
		$innerLen = $len - $open - 2;
		if ($innerLen >= 0 && $trimmed[$len - 1] === ')' && strcspn($trimmed, '()/', $open + 1, $innerLen) === $innerLen) {
			$close = $len - 1;
		} else {
			$close = CSSSyntaxScanner::findMatchingDelimiter($trimmed, $open, $len);
			if ($close === null || ($close < $len - 1 && $trimmed[$close + 1] === ')')) {
				throw new InvalidArgumentException('Mismatched parentheses.');
			}
			$tail = CSSSyntaxScanner::skipWhitespaceAndComments($trimmed, $close + 1, $len);
			if ($tail === null) {
				throw new InvalidArgumentException('Unclosed comment in CSS math expression.');
			}
			if ($tail !== $len) {
				throw new InvalidArgumentException('Invalid expression.');
			}
		}

		return self::parseSliceIterative($trimmed, $open + 1, $close, $options, $depth, 2, true);
	}

	/**
	 * Parses an inner `<calc-sum>` expression (without the outer `calc(...)` wrapper).
	 *
	 * @throws InvalidArgumentException When the expression is malformed, dimensionally incompatible, or exceeds limits
	 */
	public static function parseExpression(string $expression, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue
	{
		$options = self::validateEntryAndLimits($expression, $options, $depth, 'CSS math expression exceeds maximum size of %d bytes.');

		return self::parseSliceIterative($expression, 0, strlen($expression), $options, $depth, 2, true);
	}

	/**
	 * @internal Iterative slice-based entry point for unit values, `calc()`, `min()`, `max()`, and `clamp()`.
	 * @param ?array<string, float> $channelSymbols Finite numeric bindings for relative-color channel keywords
	 * @throws CSSParserLimitException When resource limits are exceeded
	 */
	public static function parseUnitOrMathSlice(
		string $source,
		int $start,
		int $end,
		CSSParserOptions $options,
		int $depth,
		bool $throwOnInvalid,
		?array $channelSymbols = null,
		int &$sharedNodeBudget = 0,
	): ?CSSNumericValue {
		if ($depth < 0 || $depth === PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}
		$normalizedSymbols = self::normalizeChannelSymbols($channelSymbols);
		if ($throwOnInvalid) {
			return self::parseSliceIterative($source, $start, $end, $options, $depth, 0, true, $normalizedSymbols, $sharedNodeBudget);
		}
		try {
			return self::parseSliceIterative($source, $start, $end, $options, $depth, 0, false, $normalizedSymbols, $sharedNodeBudget);
		} catch (CSSParserLimitException $e) {
			throw $e;
		} catch (InvalidArgumentException) {
			return null;
		}
	}

	/**
	 * @param ?array<string, float> $channelSymbols
	 * @return ?array<string, float>
	 */
	private static function normalizeChannelSymbols(?array $channelSymbols): ?array
	{
		if ($channelSymbols === null || $channelSymbols === []) {
			return null;
		}

		$normalized = [];
		foreach ($channelSymbols as $name => $value) {
			if (!is_finite($value)) {
				throw new InvalidArgumentException(sprintf('Relative color channel "%s" value must be finite.', $name));
			}
			$normalized[strtolower($name)] = $value;
		}

		return $normalized;
	}

	/**
	 * Returns whether a decoded CSS function name is a supported CSS Values Level 4 math function.
	 */
	public static function isMathFunctionName(string $name): bool
	{
		return isset(self::MATH_FUNCTIONS[strtolower($name)]);
	}

	/**
	 * Modes: 0 = auto (unit or math function), 2 = inner calc expression.
	 *
	 * @param ?array<string, float> $channelSymbols
	 */
	private static function parseSliceIterative(
		string $source,
		int $rootStart,
		int $rootEnd,
		CSSParserOptions $options,
		int $rootDepth,
		int $rootMode,
		bool $throwOnInvalid,
		?array $channelSymbols = null,
		int &$sharedNodeBudget = 0,
	): ?CSSNumericValue {
		$mathIndex = null;
		$baseBudget = $sharedNodeBudget;
		$totalTokens = $baseBudget;
		$totalNodes = $baseBudget;
		$slots = [];
		$nextSlot = 1;
		$parsedMathFunction = $rootMode === 2;
		// Frame kind 0: [0, start, end, depth, mode, targetSlot, allowConstants]
		// Frame kind 1: [1, outputQueue, targetSlot]
		// Frame kind 2: [2, function, argSlots, targetSlot, strategy]
		$workStack = [[0, $rootStart, $rootEnd, $rootDepth, $rootMode, 0, $rootMode === 2]];

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
					} elseif ($type === self::T_CONST) {
						if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
						}
						/** @var CSSMathConstantEnum $constEnum */
						$constEnum = $token[1];
						$valueStack[] = new CSSMathConstant($constEnum);
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
				[, $function, $argSlots, $targetSlot, $strategy] = $frame;
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
				if ($function === 'min') {
					$slots[$targetSlot] = new CSSMathMin($arguments);
				} elseif ($function === 'max') {
					$slots[$targetSlot] = new CSSMathMax($arguments);
				} elseif ($function === 'clamp') {
					$slots[$targetSlot] = new CSSMathClamp($arguments[0], $arguments[1], $arguments[2]);
				} else {
					$fnEnum = CSSMathFunctionEnum::tryFrom((string) $function);
					if ($fnEnum === null) {
						throw new InvalidArgumentException('Unsupported CSS math function: ' . (string) $function);
					}
					$slots[$targetSlot] = new CSSMathFunction($fnEnum, $arguments, $strategy);
				}
				continue;
			}

			// kind === 0: PARSE slice
			[, $start, $end, $depth, $mode, $targetSlot, $allowConstants] = $frame;
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
				$unitEnd = 0;

				if ((($c0 >= '0' && $c0 <= '9') || $c0 === '.' || $c0 === '+' || $c0 === '-')
					&& ($numEnd = self::scanNumberTokenEnd($source, $start, $end, $unitEnd)) > 0
					&& $unitEnd === $end
				) {
					$value = (float) substr($source, $start, $numEnd - $start);
					if (!is_finite($value)) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('CSS numeric value must be finite: ' . substr($source, $start, $end - $start));
						}
						return null;
					}
					$unitStr = $unitEnd > $numEnd ? substr($source, $numEnd, $unitEnd - $numEnd) : '';
					$unit = CSSUnitEnum::tryFromSymbol($unitStr);
					if ($unit === null) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Unsupported CSS unit: ' . $unitStr);
						}
						return null;
					}
					if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}
					$slots[$targetSlot] = new CSSUnitValue($value, $unit);
					continue;
				}

				$fnName = null;
				$openOffset = -1;
				if ($c0 === '\\' || ($c0 >= 'a' && $c0 <= 'z') || ($c0 >= 'A' && $c0 <= 'Z') || $c0 === '-') {
					$identEnd = -1;
					$identStr = null;
					if ($c0 !== '\\') {
						$idLen = strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', $start, $end - $start);
						if ($idLen > 0) {
							$afterId = $start + $idLen;
							$c1 = $c0 === '-' && $idLen >= 2 ? $source[$start + 1] : '';
							$validStart = $c0 !== '-' || ($c1 !== '' && ($c1 === '-' || $c1 === '_' || ($c1 >= 'a' && $c1 <= 'z') || ($c1 >= 'A' && $c1 <= 'Z')));
							if ($validStart && ($afterId >= $end || ($source[$afterId] !== '\\' && ord($source[$afterId]) < 0x80))) {
								$identEnd = $afterId;
								$identStr = substr($source, $start, $idLen);
							}
						}
					}
					if ($identStr === null) {
						$decoded = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
						if ($decoded !== null) {
							$identEnd = $decoded['end'];
							$identStr = $decoded['ident'];
						}
					}
					if ($identStr !== null) {
						if ($identEnd === $end && $allowConstants) {
							$constEnum = CSSMathConstantEnum::tryFromKeyword($identStr);
							if ($constEnum !== null) {
								if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
									throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
								}
								$slots[$targetSlot] = new CSSMathConstant($constEnum);
								continue;
							}
							if ($channelSymbols !== null) {
								$lowerSym = strtolower($identStr);
								if (isset($channelSymbols[$lowerSym])) {
									if (++$totalNodes > CSSNumericValue::MAX_MATH_NODES) {
										throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
									}
									$slots[$targetSlot] = new CSSUnitValue($channelSymbols[$lowerSym], CSSUnitEnum::NUMBER);
									continue;
								}
							}
						} elseif ($identEnd < $end && $source[$identEnd] === '(' && $c0 !== '-') {
							$candidate = strtolower($identStr);
							if (isset(self::MATH_FUNCTIONS[$candidate])) {
								$fnName = $candidate;
								$openOffset = $identEnd;
							}
						}
					}
				}

				if ($fnName === 'calc') {
					$mathIndex ??= self::buildMathIndex($source, $rootStart, $rootEnd, $options, $rootDepth, $rootMode === 0, $totalNodes);
					$close = $mathIndex['matchingParens'][$openOffset] ?? null;
					if ($close !== $end - 1) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Invalid CSS calc() expression.');
						}
						return null;
					}
					if ($targetSlot === 0) {
						$parsedMathFunction = true;
					} else {
						if ($depth >= PHP_INT_MAX) {
							throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
						}
						$depth++;
						if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
						}
					}
					$start = $openOffset + 1;
					$end = $close;
					self::trimSlice($source, $start, $end);
					$mode = 2;
					$allowConstants = true;
				} elseif ($fnName !== null) {
					$mathIndex ??= self::buildMathIndex($source, $rootStart, $rootEnd, $options, $rootDepth, $rootMode === 0, $totalNodes);
					$close = $mathIndex['matchingParens'][$openOffset] ?? null;
					if ($close !== $end - 1) {
						if ($throwOnInvalid) {
							throw new InvalidArgumentException('Invalid CSS math function.');
						}
						return null;
					}
					if ($targetSlot === 0) {
						$parsedMathFunction = true;
					}

					$argRanges = [];
					$argPos = $openOffset + 1;
					foreach ($mathIndex['commasByOpen'][$openOffset] ?? [] as $comma) {
						$argRanges[] = [$argPos, $comma];
						if (($totalNodes + count($argRanges)) > CSSNumericValue::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
						}
						$argPos = $comma + 1;
					}
					$argRanges[] = [$argPos, $close];
					if (($totalNodes + count($argRanges)) > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}

					$strategy = null;
					$argMetaOffset = 0;
					if ($fnName === 'round') {
						[$a0s, $a0e] = $argRanges[0];
						self::trimSliceWithComments($source, $a0s, $a0e, $mathIndex['commentEndByStart'], $mathIndex['commentStartByEnd']);
						if ($a0s >= $a0e) {
							throw new InvalidArgumentException('Empty argument in round().');
						}
						$strategyIdent = CSSSyntaxScanner::decodeIdentAt($source, $a0s, $a0e);
						$matchedStrategy = ($strategyIdent !== null && $strategyIdent['end'] === $a0e)
							? CSSRoundingStrategy::tryFrom(strtolower($strategyIdent['ident']))
							: null;
						if ($matchedStrategy !== null) {
							$strategy = $matchedStrategy;
							array_shift($argRanges);
							$argMetaOffset = 1;
						} else {
							$strategy = CSSRoundingStrategy::NEAREST;
						}
					}

					$argCount = count($argRanges);
					if ($fnName === 'clamp') {
						if ($argCount !== 3) {
							if ($throwOnInvalid) {
								throw new InvalidArgumentException('Invalid argument count for clamp().');
							}
							return null;
						}
					} elseif ($fnName === 'min' || $fnName === 'max') {
						if ($options->maxMathDepth !== null) {
							$cappedDepth = min($options->maxMathDepth, intdiv(PHP_INT_MAX, 64));
							$maxArgs = max(1024, $cappedDepth * 16);
							if ($argCount > $maxArgs) {
								throw new CSSParserLimitException(sprintf('CSS %s() argument count exceeds maximum limit of %d.', $fnName, $maxArgs));
							}
						}
					} else {
						$fnEnum = CSSMathFunctionEnum::from($fnName);
						$minArgs = $fnEnum->minArgs();
						$maxFnArgs = $fnEnum->maxArgs();
						if ($argCount < $minArgs || ($maxFnArgs !== null && $argCount > $maxFnArgs)) {
							if ($throwOnInvalid) {
								throw new InvalidArgumentException('Invalid argument count for ' . $fnName . '().');
							}
							return null;
						}
						if ($fnEnum->isVariadic() && $options->maxMathDepth !== null) {
							$cappedDepth = min($options->maxMathDepth, intdiv(PHP_INT_MAX, 64));
							$maxArgs = max(1024, $cappedDepth * 16);
							if ($argCount > $maxArgs) {
								throw new CSSParserLimitException(sprintf('CSS %s() argument count exceeds maximum limit of %d.', $fnName, $maxArgs));
							}
						}
					}

					$argMeta = $mathIndex['argMetaByOpen'][$openOffset] ?? [];
					$argSlots = [];
					$argJobs = [];
					foreach ($argRanges as $argIdx => [$as, $ae]) {
						self::trimSliceWithComments($source, $as, $ae, $mathIndex['commentEndByStart'], $mathIndex['commentStartByEnd']);
						if ($as >= $ae) {
							throw new InvalidArgumentException('Empty argument in ' . $fnName . '().');
						}
						$argSlot = $nextSlot++;
						$argSlots[] = $argSlot;
						$meta = $argMeta[$argIdx + $argMetaOffset] ?? ['opCount' => 0, 'hasChildParen' => false];
						$opCount = $meta['opCount'];
						$firstChar = $source[$as];
						$isExpr = $firstChar === '('
							|| $opCount > 1
							|| ($opCount === 1 && (($firstChar !== '+' && $firstChar !== '-') || $meta['hasChildParen']));
						if ($depth >= PHP_INT_MAX) {
							throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
						}
						$argJobs[] = [0, $as, $ae, $depth + 1, $isExpr ? 2 : 0, $argSlot, true];
					}
					$workStack[] = [2, $fnName, $argSlots, $targetSlot, $strategy];
					for ($i = count($argJobs) - 1; $i >= 0; $i--) {
						$workStack[] = $argJobs[$i];
					}
					continue;
				} else {
					if ($throwOnInvalid) {
						throw new InvalidArgumentException('Invalid CSS numeric value.');
					}
					return null;
				}
			}

			// mode === 2: inner <calc-sum> expression
			$tokens = self::tokenizeSlice($source, $start, $end, $options, $depth, $totalTokens, $mathIndex, $rootStart, $rootEnd, $rootDepth, $rootMode === 0, $channelSymbols, $totalNodes);
			$outputQueue = [];
			$operatorStack = [];
			$parenDepth = 0;
			$childFuncJobs = [];

			foreach ($tokens as $token) {
				$type = $token[0];
				if ($type === self::T_NUMBER || $type === self::T_CONST) {
					$outputQueue[] = $token;
				} elseif ($type === self::T_FUNC) {
					$fnSlot = $nextSlot++;
					$outputQueue[] = [self::T_FUNC, $fnSlot];
					if ($parenDepth >= PHP_INT_MAX - $depth) {
						throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
					}
					$childFuncJobs[] = [0, $token[1], $token[2], $depth + $parenDepth, 0, $fnSlot, true];
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
					if ($parenDepth >= PHP_INT_MAX - $depth) {
						throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
					}
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

		$result = $slots[0] ?? null;
		$indexedNodes = $mathIndex['indexedNodes'] ?? $baseBudget;
		$usedBudget = max($totalTokens, $totalNodes, $indexedNodes);
		if ($result !== null && $parsedMathFunction) {
			$visits = $usedBudget;
			$allocated = $usedBudget;
			$result = CSSMathSimplifier::simplifyWithBudget($result, null, $visits, $allocated);
			$sharedNodeBudget = max($usedBudget, $visits, $allocated);

			return $result;
		}
		$sharedNodeBudget = $usedBudget;

		return $result;
	}

	private static function trimSlice(string $source, int &$start, int &$end): void
	{
		CSSSyntaxScanner::trimSlice($source, $start, $end);
	}

	/**
	 * @param array<int, int> $commentEndByStart
	 * @param array<int, int> $commentStartByEnd
	 */
	private static function trimSliceWithComments(
		string $source,
		int &$start,
		int &$end,
		array $commentEndByStart,
		array $commentStartByEnd,
	): void {
		while ($start < $end) {
			$start += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $start, $end - $start);
			if (isset($commentEndByStart[$start])) {
				$start = $commentEndByStart[$start];
				continue;
			}
			break;
		}
		while ($end > $start) {
			while ($end > $start && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$end - 1])) {
				$end--;
			}
			if (isset($commentStartByEnd[$end]) && $commentStartByEnd[$end] >= $start) {
				$end = $commentStartByEnd[$end];
				continue;
			}
			break;
		}
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

	/**
	 * Single O(N) scan indexing parentheses, direct commas, per-argument operator metadata, and comments.
	 *
	 * @return array{
	 *   matchingParens: array<int, int>,
	 *   commasByOpen: array<int, list<int>>,
	 *   argMetaByOpen: array<int, list<array{opCount: int, hasChildParen: bool}>>,
	 *   commentEndByStart: array<int, int>,
	 *   commentStartByEnd: array<int, int>,
	 *   indexedNodes: int
	 * }
	 */
	private static function buildMathIndex(
		string $source,
		int $start,
		int $end,
		CSSParserOptions $options,
		int $rootDepth,
		bool $hasOuterWrapper,
		int $initialIndexedNodes = 0,
	): array {
		$matchingParens = [];
		$commasByOpen = [];
		$argMetaByOpen = [];
		$commentEndByStart = [];
		$commentStartByEnd = [];
		$indexedNodes = $initialIndexedNodes;
		$maxParenStack = $options->maxMathDepth !== null
			? ($hasOuterWrapper && $options->maxMathDepth < PHP_INT_MAX ? $options->maxMathDepth + 1 : $options->maxMathDepth)
			: null;

		/** @var list<array{int, list<int>, list<array{opCount: int, hasChildParen: bool}>, int, bool}> $parenStack */
		$parenStack = [];
		$scan = $start;
		while ($scan < $end) {
			$scan += strcspn($source, '()/,+-*', $scan, $end - $scan);
			if ($scan >= $end) {
				break;
			}
			$ch = $source[$scan];
			if ($ch === '/' && $scan + 1 < $end && $source[$scan + 1] === '*') {
				$cEnd = strpos($source, '*/', $scan + 2);
				if ($cEnd === false || $cEnd + 1 >= $end) {
					throw new InvalidArgumentException('Unclosed comment in CSS math expression.');
				}
				if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
				}
				$afterComment = $cEnd + 2;
				$commentEndByStart[$scan] = $afterComment;
				$commentStartByEnd[$afterComment] = $scan;
				$scan = $afterComment;
				continue;
			}
			if ($ch === '(') {
				if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
				}
				if ($parenStack !== []) {
					$parenStack[count($parenStack) - 1][4] = true;
				}
				$parenStack[] = [$scan, [], [], 0, false];
				if ($maxParenStack !== null && ($rootDepth > $maxParenStack || count($parenStack) > ($maxParenStack - $rootDepth))) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
				}
			} elseif ($ch === ')') {
				if ($parenStack !== []) {
					[$open, $commas, $argMeta, $curOps, $curHasParen] = array_pop($parenStack);
					$argMeta[] = ['opCount' => $curOps, 'hasChildParen' => $curHasParen];
					$matchingParens[$open] = $scan;
					if ($commas !== []) {
						$commasByOpen[$open] = $commas;
					}
					$argMetaByOpen[$open] = $argMeta;
				}
			} elseif ($ch === ',') {
				if ($parenStack !== []) {
					if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}
					$topIdx = count($parenStack) - 1;
					$parenStack[$topIdx][1][] = $scan;
					$parenStack[$topIdx][2][] = [
						'opCount' => $parenStack[$topIdx][3],
						'hasChildParen' => $parenStack[$topIdx][4],
					];
					$parenStack[$topIdx][3] = 0;
					$parenStack[$topIdx][4] = false;
				}
			} elseif ($parenStack !== []) {
				$parenStack[count($parenStack) - 1][3]++;
			}
			$scan++;
		}

		return [
			'matchingParens' => $matchingParens,
			'commasByOpen' => $commasByOpen,
			'argMetaByOpen' => $argMetaByOpen,
			'commentEndByStart' => $commentEndByStart,
			'commentStartByEnd' => $commentStartByEnd,
			'indexedNodes' => $indexedNodes,
		];
	}

	/**
	 * @param ?array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, argMetaByOpen: array<int, list<array{opCount: int, hasChildParen: bool}>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>, indexedNodes: int} $mathIndex
	 * @param ?array<string, float> $channelSymbols
	 * @return list<array{0: int, 1: mixed, 2?: mixed, 3?: CSSUnitEnum}>
	 */
	private static function tokenizeSlice(
		string $expression,
		int $sliceStart,
		int $sliceEnd,
		CSSParserOptions $options,
		int $depth,
		int &$totalTokens,
		?array &$mathIndex,
		int $rootStart,
		int $rootEnd,
		int $rootDepth,
		bool $hasOuterWrapper,
		?array $channelSymbols = null,
		int $initialIndexedNodes = 0,
	): array {
		$tokens = [];
		$offset = $sliceStart;
		$expectsOperand = true;
		$hadWhitespace = false;
		$unaryRun = 0;
			$maxTokens = $options->maxMathDepth !== null
			? max(4096, min($options->maxMathDepth, intdiv(PHP_INT_MAX, 64)) * 64)
			: null;

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
				if (++$totalTokens > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf(
						'Maximum CSS math expression node count of %d exceeded.',
						CSSNumericValue::MAX_MATH_NODES,
					));
				}
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
				$look = CSSSyntaxScanner::skipWhitespaceAndComments($expression, $offset + 1, $sliceEnd, skipWhitespace: false);
				if ($look === null) {
					throw new InvalidArgumentException('Unclosed comment in calc() expression.');
				}
				if (!$hadWhitespace || ($look < $sliceEnd && !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $expression[$look]) && $expression[$look] !== ')')) {
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

			$unitEnd = 0;
			if ($character === '+' || $character === '-') {
				if (($numEnd = self::scanNumberTokenEnd($expression, $offset, $sliceEnd, $unitEnd)) > 0) {
					$tokens[] = self::makeNumberToken($expression, $offset, $numEnd, $unitEnd);
					$offset = $unitEnd;
					$expectsOperand = false;
					$hadWhitespace = false;
					$unaryRun = 0;
					continue;
				}
				if ($character === '-') {
					$negIdent = CSSSyntaxScanner::decodeIdentAt($expression, $offset, $sliceEnd);
					if ($negIdent !== null) {
						if ($negIdent['end'] < $sliceEnd && $expression[$negIdent['end']] === '(') {
							throw new InvalidArgumentException('Invalid function token in calc() expression.');
						}
						if (CSSMathConstantEnum::tryFromKeyword($negIdent['ident']) === CSSMathConstantEnum::NEG_INFINITY) {
							$tokens[] = [self::T_CONST, CSSMathConstantEnum::NEG_INFINITY];
							$offset = $negIdent['end'];
							$expectsOperand = false;
							$hadWhitespace = false;
							$unaryRun = 0;
							continue;
						}
					}
				}
				$unaryRun += strspn($expression, '+-', $offset, $sliceEnd - $offset);
				if ($options->maxMathDepth !== null && ($depth + $unaryRun) > $options->maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
				}
				$look = CSSSyntaxScanner::skipWhitespaceAndComments($expression, $offset + 1, $sliceEnd, skipWhitespace: false);
				if ($look === null) {
					throw new InvalidArgumentException('Unclosed comment in calc() expression.');
				}
				$sawComment = $look > $offset + 1;
				if ($look < $sliceEnd) {
					$nextChar = $expression[$look];
					if ($nextChar === '+'
						|| $nextChar === '-'
						|| str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $nextChar)
						|| ($sawComment && (($nextChar >= '0' && $nextChar <= '9') || $nextChar === '.'))
					) {
						throw new InvalidArgumentException('Invalid unary operator in calc() expression.');
					}
					if (($nextChar >= 'a' && $nextChar <= 'z') || ($nextChar >= 'A' && $nextChar <= 'Z') || $nextChar === '\\' || ord($nextChar) >= 0x80) {
						$afterUnaryIdent = CSSSyntaxScanner::decodeIdentAt($expression, $look, $sliceEnd);
						if ($afterUnaryIdent !== null
							&& ($afterUnaryIdent['end'] >= $sliceEnd || $expression[$afterUnaryIdent['end']] !== '(')
							&& (CSSMathConstantEnum::tryFromKeyword($afterUnaryIdent['ident']) !== null
								|| ($channelSymbols !== null && isset($channelSymbols[strtolower($afterUnaryIdent['ident'])])))
						) {
							throw new InvalidArgumentException('Invalid unary operator before CSS math constant.');
						}
					}
				}
				$tokens[] = [self::T_OP, $character === '-' ? 'u-' : 'u+'];
				$offset++;
				$hadWhitespace = false;
				continue;
			}

			if ((($character >= '0' && $character <= '9') || $character === '.')
				&& ($numEnd = self::scanNumberTokenEnd($expression, $offset, $sliceEnd, $unitEnd)) > 0
			) {
				$tokens[] = self::makeNumberToken($expression, $offset, $numEnd, $unitEnd);
				$offset = $unitEnd;
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

			if (($character >= 'a' && $character <= 'z') || ($character >= 'A' && $character <= 'Z') || $character === '\\') {
				$name = null;
				$afterName = -1;
				if ($character !== '\\') {
					$idLen = strspn($expression, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-', $offset + 1, $sliceEnd - $offset - 1) + 1;
					$candidateEnd = $offset + $idLen;
					if ($candidateEnd >= $sliceEnd || ($expression[$candidateEnd] !== '\\' && ord($expression[$candidateEnd]) < 0x80)) {
						$name = substr($expression, $offset, $idLen);
						$afterName = $candidateEnd;
					}
				}
				if ($name === null) {
					$ident = CSSSyntaxScanner::decodeIdentAt($expression, $offset, $sliceEnd);
					if ($ident !== null) {
						$name = $ident['ident'];
						$afterName = $ident['end'];
					}
				}
				if ($name !== null) {
					if ($afterName < $sliceEnd && $expression[$afterName] === '(') {
						$mathIndex ??= self::buildMathIndex($expression, $rootStart, $rootEnd, $options, $rootDepth, $hasOuterWrapper, $initialIndexedNodes);
						$close = $mathIndex['matchingParens'][$afterName] ?? null;
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
					$constEnum = CSSMathConstantEnum::tryFromKeyword($name);
					if ($constEnum !== null) {
						$tokens[] = [self::T_CONST, $constEnum];
					} elseif ($channelSymbols !== null && isset($channelSymbols[strtolower($name)])) {
						$tokens[] = [self::T_NUMBER, $name, $channelSymbols[strtolower($name)], CSSUnitEnum::NUMBER];
					} else {
						$tokens[] = [self::T_UNKNOWN, $name];
					}
					$offset = $afterName;
					$expectsOperand = false;
					$hadWhitespace = false;
					$unaryRun = 0;
					continue;
				}
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

	/**
	 * Scans a CSS number (`[+-]?(\d+(\.\d+)?|\.\d+)([eE][+-]?\d+)?`) and optional `[a-zA-Z%]*` unit
	 * within `[$start, $limit)`. Returns the end offset of the numeric portion, or `-1` on mismatch.
	 */
	private static function scanNumberTokenEnd(string $source, int $start, int $limit, int &$unitEnd): int
	{
		$p = $start;
		if ($p >= $limit) {
			return -1;
		}
		if ($source[$p] === '+' || $source[$p] === '-') {
			$p++;
		}
		$digits = strspn($source, '0123456789', $p, $limit - $p);
		$p += $digits;
		$hasDigits = $digits > 0;
		if ($p + 1 < $limit && $source[$p] === '.' && $source[$p + 1] >= '0' && $source[$p + 1] <= '9') {
			$p++;
			$p += strspn($source, '0123456789', $p, $limit - $p);
			$hasDigits = true;
		}
		if (!$hasDigits) {
			return -1;
		}
		if ($p < $limit && ($source[$p] === 'e' || $source[$p] === 'E')) {
			$ep = $p + 1;
			if ($ep < $limit && ($source[$ep] === '+' || $source[$ep] === '-')) {
				$ep++;
			}
			$eDigits = strspn($source, '0123456789', $ep, $limit - $ep);
			if ($eDigits > 0) {
				$p = $ep + $eDigits;
			}
		}
		$unitEnd = $p + strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ%', $p, $limit - $p);

		return $p;
	}

	/** @return array{0: int, 1: string, 2: float, 3: CSSUnitEnum} */
	private static function makeNumberToken(string $source, int $start, int $numEnd, int $unitEnd): array
	{
		$float = (float) substr($source, $start, $numEnd - $start);
		$raw = substr($source, $start, $unitEnd - $start);
		if (!is_finite($float)) {
			throw new InvalidArgumentException('CSS numeric value must be finite: ' . $raw);
		}
		$unitSymbol = $unitEnd > $numEnd ? substr($source, $numEnd, $unitEnd - $numEnd) : '';
		$unit = CSSUnitEnum::tryFromSymbol($unitSymbol);
		if ($unit === null) {
			throw new InvalidArgumentException('Unsupported CSS unit: ' . $unitSymbol);
		}

		return [self::T_NUMBER, $raw, $float, $unit];
	}
}
