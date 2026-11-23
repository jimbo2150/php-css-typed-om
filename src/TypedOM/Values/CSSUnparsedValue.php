<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/**
 * Represents a sequence of CSS text fragments and `CSSVariableReferenceValue` objects.
 *
 * @implements ArrayAccess<int, string|CSSVariableReferenceValue>
 * @implements IteratorAggregate<int, string|CSSVariableReferenceValue>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnparsedValue
 */
final class CSSUnparsedValue extends CSSStyleValue implements ArrayAccess, Countable, IteratorAggregate
{
	private const int MAX_UNPARSED_DEPTH = 512;

	/** @var list<string|CSSVariableReferenceValue> */
	private array $values;

	private ?int $maxBytes;

	public int $length {
		get => count($this->values);
	}

	/**
	 * @param list<string|CSSVariableReferenceValue>|string $value
	 * @throws InvalidArgumentException When an array contains non-string/non-variable fragments or exceeds limits
	 */
	public function __construct(array|string $value, ?CSSParserOptions $options = null, int $depth = 0)
	{
		$this->maxBytes = $options !== null ? $options->maxBytes : CSSParserOptions::DEFAULT_MAX_BYTES;
		$options ??= CSSParserOptions::default();

		if (is_string($value)) {
			if ($this->maxBytes !== null && strlen($value) > $this->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $this->maxBytes));
			}
			$maxDepth = $options->maxDepth;
			if ($maxDepth !== null && $depth > $maxDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxDepth));
			}
			if ($value !== '') {
				CSSSyntaxScanner::assertValidFragment($value, 'CSS unparsed value', [';', '{', '}']);
			}
			$this->values = self::parseFragmentsIterative($value, $options, $depth, $this->maxBytes);
			return;
		}

		$normalized = [];
		foreach ($value as $fragment) {
			if (is_string($fragment)) {
				if (CSSSyntaxScanner::hasControlCharacters($fragment)) {
					throw new InvalidArgumentException('CSS unparsed fragment contains disallowed control characters.');
				}
				$normalized[] = $fragment;
			} elseif ($fragment instanceof CSSVariableReferenceValue) {
				$this->assertNoReferenceCycleAndDepth($fragment, $depth);
				$normalized[] = $fragment->clone();
			} else {
				throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
			}
		}
		self::validateFragmentArray($normalized, $this->maxBytes);
		$this->values = $normalized;
	}

	/** @internal */
	public static function createShell(?int $maxBytes): self
	{
		$shell = new self([], CSSParserOptions::unlimited());
		$shell->maxBytes = $maxBytes;
		return $shell;
	}

	/**
	 * @internal
	 * @param list<string|CSSVariableReferenceValue> $fragments
	 */
	public function setParsedValuesInternal(array $fragments): void
	{
		$this->values = $fragments;
	}

	/** @internal */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	public static function fromCssText(string $cssText, ?CSSParserOptions $options = null, int $depth = 0): self
	{
		return new self($cssText, $options, $depth);
	}

	/** @return list<string|CSSVariableReferenceValue> */
	public function getValues(): array
	{
		return $this->values;
	}

	/** @return list<int> */
	public function keys(): array
	{
		return array_keys($this->values);
	}

	/** @return list<string|CSSVariableReferenceValue> */
	public function values(): array
	{
		return array_values($this->values);
	}

	/** @return list<array{int, string|CSSVariableReferenceValue}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->values as $index => $value) {
			$entries[] = [$index, $value];
		}

		return $entries;
	}

	/**
	 * Invokes $callback($value, $index, $this) for each unparsed fragment in order.
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->values as $index => $value) {
			$callback($value, $index, $this);
		}
	}

	public function count(): int
	{
		return count($this->values);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && array_key_exists($offset, $this->values);
	}

	public function offsetGet(mixed $offset): string|CSSVariableReferenceValue|null
	{
		return is_int($offset) ? ($this->values[$offset] ?? null) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (is_string($value)) {
			if (CSSSyntaxScanner::hasControlCharacters($value)) {
				throw new InvalidArgumentException('CSS unparsed fragment contains disallowed control characters.');
			}
		} elseif ($value instanceof CSSVariableReferenceValue) {
			$this->assertNoReferenceCycleAndDepth($value);
			$value = $value->clone();
		} else {
			throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
		}

		$next = $this->values;
		if ($offset === null) {
			$next[] = $value;
		} elseif (!is_int($offset) || !array_key_exists($offset, $next)) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		} else {
			$next[$offset] = $value;
		}

		self::validateFragmentArray($next, $this->maxBytes);
		$this->values = $next;
	}

	public function offsetUnset(mixed $offset): void
	{
		if (!is_int($offset) || !array_key_exists($offset, $this->values)) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		}
		$next = $this->values;
		array_splice($next, $offset, 1);
		self::validateFragmentArray($next, $this->maxBytes);
		$this->values = $next;
	}

	/** @return Traversable<int, string|CSSVariableReferenceValue> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function __clone(): void
	{
		// Iteratively clone descendant var() / unparsed trees to keep stack depth O(1).
		$stack = [[&$this->values, 0]];
		while ($stack !== []) {
			$entry = array_pop($stack);
			/** @var list<string|CSSVariableReferenceValue> $list */
			$list = &$entry[0];
			$depth = $entry[1];
			if ($depth > self::MAX_UNPARSED_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', self::MAX_UNPARSED_DEPTH));
			}
			foreach ($list as $i => $item) {
				if ($item instanceof CSSVariableReferenceValue) {
					$clonedRef = $item->shallowCloneInternal();
					$list[$i] = $clonedRef;
					$fb = $clonedRef->getFallback();
					if ($fb !== null) {
						$clonedFb = $fb->shallowCloneInternal();
						$clonedRef->setFallbackInternal($clonedFb);
						$stack[] = [&$clonedFb->values, $depth + 1];
					}
				}
			}
			unset($list);
		}
	}

	/** @internal */
	public function shallowCloneInternal(): self
	{
		$copy = new self([], CSSParserOptions::unlimited());
		$copy->maxBytes = $this->maxBytes;
		$copy->values = $this->values;
		return $copy;
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return self::stringifyIteratively($this->values, $this->maxBytes);
	}

	/**
	 * @internal Iteratively serializes unparsed fragments while enforcing byte and depth limits.
	 * @param list<string|CSSVariableReferenceValue> $rootValues
	 */
	public static function stringifyIteratively(array $rootValues, ?int $maxBytes, string $prefix = '', string $suffix = ''): string
	{
		$parts = $prefix === '' ? [] : [$prefix];
		$totalBytes = strlen($prefix);
		$maxAllowed = $maxBytes;
		$stack = [];
		for ($i = count($rootValues) - 1; $i >= 0; $i--) {
			$stack[] = [0, $rootValues[$i], 0];
		}

		while ($stack !== []) {
			[$phase, $item, $depth] = array_pop($stack);
			if ($phase === 1) {
				/** @var string $item */
				$totalBytes += strlen($item);
				if ($maxAllowed !== null && $totalBytes > $maxAllowed) {
					throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxAllowed));
				}
				$parts[] = $item;
				continue;
			}
			if (is_string($item)) {
				$totalBytes += strlen($item);
				if ($maxAllowed !== null && $totalBytes > $maxAllowed) {
					throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxAllowed));
				}
				$parts[] = $item;
				continue;
			}
			if ($depth > self::MAX_UNPARSED_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', self::MAX_UNPARSED_DEPTH));
			}

			/** @var CSSVariableReferenceValue $item */
			$fb = $item->getFallback();
			$head = 'var(' . $item->getVariable() . ($fb !== null ? ', ' : '');
			$totalBytes += strlen($head);
			if ($maxAllowed !== null && $totalBytes > $maxAllowed) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxAllowed));
			}
			$parts[] = $head;
			$stack[] = [1, ')', $depth];
			if ($fb !== null) {
				$fbValues = $fb->values;
				for ($i = count($fbValues) - 1; $i >= 0; $i--) {
					$stack[] = [0, $fbValues[$i], $depth + 1];
				}
			}
		}

		if ($suffix !== '') {
			$totalBytes += strlen($suffix);
			if ($maxAllowed !== null && $totalBytes > $maxAllowed) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxAllowed));
			}
			$parts[] = $suffix;
		}

		return count($parts) === 1 ? $parts[0] : implode('', $parts);
	}

	public function __get(string $name): mixed
	{
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return false;
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private function assertNoReferenceCycleAndDepth(CSSVariableReferenceValue $candidate, int $baseDepth = 0): void
	{
		$visited = [];
		$stack = [[$candidate->getFallback(), $baseDepth + 1]];
		while ($stack !== []) {
			[$fallback, $depth] = array_pop($stack);
			if ($fallback === null) {
				continue;
			}
			if ($depth > self::MAX_UNPARSED_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', self::MAX_UNPARSED_DEPTH));
			}
			if ($fallback === $this) {
				throw new InvalidArgumentException('Cannot insert a CSSVariableReferenceValue creating a fallback cycle.');
			}
			$id = spl_object_id($fallback);
			if (isset($visited[$id])) {
				continue;
			}
			$visited[$id] = true;
			foreach ($fallback->values as $nested) {
				if ($nested instanceof CSSVariableReferenceValue) {
					$stack[] = [$nested->getFallback(), $depth + 1];
				}
			}
		}
	}

	/** @param list<string|CSSVariableReferenceValue> $segments */
	private static function validateFragmentArray(array $segments, ?int $maxBytes = null): void
	{
		$prefixRun = '';
		$totalBytes = 0;
		$joinedParts = [];

		foreach ($segments as $segment) {
			if ($segment instanceof CSSVariableReferenceValue) {
				if ($prefixRun !== '' && CSSSyntaxScanner::hasUnclosedCommentOrString($prefixRun)) {
					throw new InvalidArgumentException('CSSVariableReferenceValue cannot follow an unclosed string, comment, or URL.');
				}
				$prefixRun = '';
				$fb = $segment->getFallback();
				$text = $fb === null
					? 'var(' . $segment->getVariable() . ')'
					: self::stringifyIteratively($fb->values, $maxBytes, 'var(' . $segment->getVariable() . ', ', ')');
			} else {
				$prefixRun .= $segment;
				$text = $segment;
			}
			$totalBytes += strlen($text);
			if ($maxBytes !== null && $totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxBytes));
			}
			$joinedParts[] = $text;
		}

		$joined = implode('', $joinedParts);
		if ($joined !== '') {
			CSSSyntaxScanner::assertValidFragment($joined, 'CSS unparsed value', [';', '{', '}']);
		}
	}

	/** @return list<string|CSSVariableReferenceValue> */
	private static function parseFragmentsIterative(string $source, CSSParserOptions $options, int $rootDepth, ?int $maxBytes): array
	{
		if (stripos($source, 'var(') === false) {
			return [$source];
		}

		$length = strlen($source);
		// Single O(N) pre-pass: map matching '(' => ')' and mark '(' whose direct body contains ';', '{', or '}'.
		$matchingParens = [];
		$parenHasForbidden = [];
		$delimStack = [];
		$offset = 0;
		$lastEscapeOffset = -1;
		$mask = "\\\"'/();{}[]";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$ch = $source[$offset];
			if ($ch === '"' || $ch === "'") {
				$valid = false;
				$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}
			if ($ch === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$cEnd = strpos($source, '*/', $offset + 2);
					if ($cEnd === false) {
						break;
					}
					$offset = $cEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}
			if ($ch === '\\') {
				$lastEscapeOffset = $offset;
				$offset = self::consumeCssEscapeEnd($source, $offset, $length);
				continue;
			}
			if ($ch === ';' || $ch === '{' || $ch === '}' || $ch === '[' || $ch === ']') {
				$topDelim = $delimStack === [] ? null : end($delimStack);
				if (($ch === ';' || $ch === '{' || $ch === '}') && $topDelim !== null && $topDelim[0] === '(') {
					$parenHasForbidden[$topDelim[1]] = true;
				}
				if ($ch === '{' || $ch === '[') {
					$delimStack[] = [$ch, $offset];
				} elseif (($ch === '}' && $topDelim !== null && $topDelim[0] === '{')
					|| ($ch === ']' && $topDelim !== null && $topDelim[0] === '[')
				) {
					array_pop($delimStack);
				}
				$offset++;
				continue;
			}
			if ($ch === '(') {
				if (CSSSyntaxScanner::isUnquotedUrlStartInternal($source, $offset, 0, $length, $lastEscapeOffset)) {
					$valid = false;
					$urlEnd = CSSSyntaxScanner::scanUnquotedUrlEnd($source, $offset, $length, $valid);
					$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
					continue;
				}
				$delimStack[] = ['(', $offset];
				$offset++;
				continue;
			}
			if ($ch === ')' && $delimStack !== [] && end($delimStack)[0] === '(') {
				$open = array_pop($delimStack);
				$matchingParens[$open[1]] = $offset;
			}
			$offset++;
		}

		$rootFragments = [];
		$identRegex = '/\G--(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')+/u';
		$workStack = [[0, $length, $rootDepth, null]];

		while ($workStack !== []) {
			/** @var array{0: int, 1: int, 2: int, 3: ?CSSUnparsedValue} $job */
			$job = array_pop($workStack);
			[$sliceStart, $sliceEnd, $depth, $targetObj] = $job;

			$fragments = [];
			$fragmentStart = $sliceStart;
			$pos = $sliceStart;
			$lastEsc = -1;
			$lastEscEnd = -1;

			while ($pos < $sliceEnd) {
				$pos += strcspn($source, $mask, $pos, $sliceEnd - $pos);
				if ($pos >= $sliceEnd) {
					break;
				}

				$ch = $source[$pos];
				if ($ch === '"' || $ch === "'") {
					$valid = false;
					$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($source, $pos, $sliceEnd, $valid);
					$pos = $valid ? $strEnd + 1 : $strEnd;
					continue;
				}
				if ($ch === '/') {
					if ($pos + 1 < $sliceEnd && $source[$pos + 1] === '*') {
						$cEnd = strpos($source, '*/', $pos + 2);
						if ($cEnd === false || $cEnd + 1 >= $sliceEnd) {
							break;
						}
						$pos = $cEnd + 2;
						continue;
					}
					$pos++;
					continue;
				}
				if ($ch === '\\') {
					$lastEsc = $pos;
					$lastEscEnd = self::consumeCssEscapeEnd($source, $pos, $sliceEnd);
					$pos = $lastEscEnd;
					continue;
				}

				if ($ch !== '(') {
					$pos++;
					continue;
				}

				if ($pos >= $sliceStart + 3 && ($source[$pos - 1] === 'r' || $source[$pos - 1] === 'R')
					&& substr_compare($source, 'var', $pos - 3, 3, true) === 0
					&& $lastEscEnd !== $pos - 3
					&& ($pos === $sliceStart + 3 || !self::isIdentByte($source[$pos - 4]))
					&& isset($matchingParens[$pos])
				) {
					$varStart = $pos - 3;
					$close = $matchingParens[$pos];
					if ($close < $sliceEnd) {
						$cursor = self::skipCssTrivia($source, $pos + 1, $close);
						if ($cursor + 1 < $close && $source[$cursor] === '-' && $source[$cursor + 1] === '-'
							&& preg_match($identRegex, $source, $idMatch, 0, $cursor) === 1
							&& ($cursor + strlen($idMatch[0])) <= $close
						) {
							$ident = $idMatch[0];
							$afterIdent = self::skipCssTrivia($source, $cursor + strlen($ident), $close);
							if ($afterIdent === $close || ($afterIdent < $close && $source[$afterIdent] === ',')) {
								$hasFallback = $afterIdent < $close && $source[$afterIdent] === ',';
								if ($hasFallback && isset($parenHasForbidden[$pos])) {
									$pos++;
									continue;
								}
								try {
									$normalizedVariable = CSSStyleDeclaration::normalizePropertyName($ident);
								} catch (InvalidArgumentException) {
									$pos++;
									continue;
								}
								$maxAllowedDepth = $options->maxDepth;
								if ($maxAllowedDepth !== null && ($depth + 1) > $maxAllowedDepth) {
									throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxAllowedDepth));
								}

								$fbObj = null;
								if ($hasFallback) {
									$fbStart = $afterIdent + 1;
									$fbEnd = $close;
									while ($fbStart < $fbEnd && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$fbStart])) {
										$fbStart++;
									}
									while ($fbEnd > $fbStart && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$fbEnd - 1])) {
										$fbEnd--;
									}
									$fbObj = self::createShell($maxBytes);
									if ($fbStart < $fbEnd) {
										$workStack[] = [$fbStart, $fbEnd, $depth + 1, $fbObj];
									}
								}

								if ($varStart > $fragmentStart) {
									$fragments[] = substr($source, $fragmentStart, $varStart - $fragmentStart);
								}
								$fragments[] = CSSVariableReferenceValue::createParsed($normalizedVariable, $fbObj, $maxBytes);
								$pos = $close + 1;
								$fragmentStart = $pos;
								continue;
							}
						}
					}
					$pos++;
					continue;
				}

				if (CSSSyntaxScanner::isUnquotedUrlStartInternal($source, $pos, $sliceStart, $sliceEnd, $lastEsc)) {
					$valid = false;
					$urlEnd = CSSSyntaxScanner::scanUnquotedUrlEnd($source, $pos, $sliceEnd, $valid);
					$pos = $urlEnd < $sliceEnd ? $urlEnd + 1 : $sliceEnd;
					continue;
				}

				$pos++;
			}

			if ($fragmentStart < $sliceEnd || $fragments === []) {
				$fragments[] = substr($source, $fragmentStart, $sliceEnd - $fragmentStart);
			}

			if ($targetObj === null) {
				$rootFragments = $fragments;
			} else {
				$targetObj->setParsedValuesInternal($fragments);
			}
		}

		return $rootFragments;
	}

	private static function skipCssTrivia(string $source, int $offset, int $end): int
	{
		while ($offset < $end) {
			$offset += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $offset, $end - $offset);
			if ($offset + 1 < $end && $source[$offset] === '/' && $source[$offset + 1] === '*') {
				$cEnd = strpos($source, '*/', $offset + 2);
				if ($cEnd === false || $cEnd + 1 >= $end) {
					return $end;
				}
				$offset = $cEnd + 2;
				continue;
			}
			break;
		}

		return $offset;
	}

	private static function consumeCssEscapeEnd(string $source, int $backslashPos, int $limit): int
	{
		$next = $backslashPos + 1;
		if ($next >= $limit) {
			return $limit;
		}
		if (ctype_xdigit($source[$next])) {
			$hexEnd = $next + strspn($source, '0123456789abcdefABCDEF', $next, min(6, $limit - $next));
			if ($hexEnd < $limit && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$hexEnd])) {
				if ($source[$hexEnd] === "\r" && $hexEnd + 1 < $limit && $source[$hexEnd + 1] === "\n") {
					return $hexEnd + 2;
				}
				return $hexEnd + 1;
			}
			return $hexEnd;
		}

		return $next + 1;
	}

	private static function isIdentByte(string $byte): bool
	{
		$ord = ord($byte);
		return ($ord >= 0x61 && $ord <= 0x7A)
			|| ($ord >= 0x41 && $ord <= 0x5A)
			|| ($ord >= 0x30 && $ord <= 0x39)
			|| $ord === 0x5F
			|| $ord === 0x2D
			|| $ord >= 0x80;
	}
}
