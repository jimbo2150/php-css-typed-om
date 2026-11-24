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
use Throwable;
use Traversable;
use WeakReference;

/**
 * Represents a sequence of CSS text fragments and `CSSVariableReferenceValue` objects.
 *
 * @implements ArrayAccess<int, string|CSSVariableReferenceValue>
 * @implements IteratorAggregate<int, string|CSSVariableReferenceValue>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnparsedValue
 */
final class CSSUnparsedValue extends CSSStyleValue implements ArrayAccess, Countable, IteratorAggregate
{
	public const int MAX_UNPARSED_DEPTH = 512;

	private const int MAX_PREPASS_DELIM_DEPTH = 4096;

	private const int MAX_PREPASS_INDEXED_PARENS = 65_536;

	/** @var list<string|CSSVariableReferenceValue> */
	private array $values;

	private ?int $maxBytes;

	private int $maxDepth;

	/** @var ?WeakReference<CSSVariableReferenceValue> */
	private ?WeakReference $parentRef = null;

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
		$effectiveMaxDepth = $options->maxDepth !== null
			? min(self::MAX_UNPARSED_DEPTH, $options->maxDepth)
			: self::MAX_UNPARSED_DEPTH;
		$this->maxDepth = $effectiveMaxDepth;

		if (is_string($value)) {
			if ($this->maxBytes !== null && strlen($value) > $this->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $this->maxBytes));
			}
			if ($depth > $effectiveMaxDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
			}
			if ($value !== '') {
				CSSSyntaxScanner::assertValidFragment($value, 'CSS unparsed value', [';', '{', '}']);
			}
			$this->values = self::parseFragmentsIterative($value, $depth, $this->maxBytes, $effectiveMaxDepth);
			foreach ($this->values as $item) {
				if ($item instanceof CSSVariableReferenceValue) {
					$item->setParentUnparsedInternal($this);
				}
			}
			return;
		}

		if ($depth > $effectiveMaxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
		}
		if (count($value) > CSSStyleValue::MAX_LIST_ITEMS) {
			throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
		}

		$normalized = [];
		foreach ($value as $fragment) {
			if (is_string($fragment)) {
				if (CSSSyntaxScanner::hasControlCharacters($fragment)) {
					throw new InvalidArgumentException('CSS unparsed fragment contains disallowed control characters.');
				}
				$normalized[] = $fragment;
			} elseif ($fragment instanceof CSSVariableReferenceValue) {
				$this->assertNoReferenceCycleAndDepth($fragment, $depth, $effectiveMaxDepth);
				$clonedRef = $fragment->clone();
				$clonedRef->clampMaxBytesInternal($this->maxBytes);
				$normalized[] = $clonedRef;
			} else {
				throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
			}
		}
		self::validateFragmentArray($normalized, $this->maxBytes);
		$this->values = $normalized;
		foreach ($this->values as $item) {
			if ($item instanceof CSSVariableReferenceValue) {
				$item->setParentUnparsedInternal($this);
			}
		}
	}

	/** @internal */
	public static function createShell(?int $maxBytes, int $maxDepth = self::MAX_UNPARSED_DEPTH): self
	{
		$shell = new self([], CSSParserOptions::unlimited());
		$shell->maxBytes = $maxBytes;
		$shell->maxDepth = $maxDepth;
		return $shell;
	}

	/**
	 * @internal
	 * @param list<string|CSSVariableReferenceValue> $fragments
	 */
	public function setParsedValuesInternal(array $fragments): void
	{
		$this->values = $fragments;
		foreach ($this->values as $item) {
			if ($item instanceof CSSVariableReferenceValue) {
				$item->setParentUnparsedInternal($this);
			}
		}
	}

	/** @internal */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	/** @internal */
	public function getMaxDepthInternal(): int
	{
		return $this->maxDepth;
	}

	/** @internal */
	public function setParentRefInternal(?CSSVariableReferenceValue $parent): void
	{
		$this->parentRef = $parent !== null ? WeakReference::create($parent) : null;
	}

	/** @internal */
	public function getParentRefInternal(): ?CSSVariableReferenceValue
	{
		return $this->parentRef?->get();
	}

	/**
	 * @internal
	 * @return array{root: CSSUnparsedValue|CSSVariableReferenceValue, depth: int, maxBytes: ?int, maxDepth: int}
	 */
	public function resolveRootBudgetInternal(): array
	{
		$current = $this;
		$varDepth = 0;
		$maxBytes = $this->maxBytes;
		$maxDepth = $this->maxDepth;
		$visited = [spl_object_id($this) => true];

		while (true) {
			if ($current instanceof self) {
				$parent = $current->getParentRefInternal();
				if ($parent === null) {
					break;
				}
				$id = spl_object_id($parent);
				if (isset($visited[$id])) {
					break;
				}
				$visited[$id] = true;
				$varDepth++;
				$pBytes = $parent->getMaxBytesInternal();
				if ($pBytes !== null && ($maxBytes === null || $pBytes < $maxBytes)) {
					$maxBytes = $pBytes;
				}
				$pDepth = $parent->getMaxDepthInternal();
				if ($pDepth < $maxDepth) {
					$maxDepth = $pDepth;
				}
				$current = $parent;
			} else {
				$parent = $current->getParentUnparsedInternal();
				if ($parent === null) {
					break;
				}
				$id = spl_object_id($parent);
				if (isset($visited[$id])) {
					break;
				}
				$visited[$id] = true;
				$pBytes = $parent->getMaxBytesInternal();
				if ($pBytes !== null && ($maxBytes === null || $pBytes < $maxBytes)) {
					$maxBytes = $pBytes;
				}
				$pDepth = $parent->getMaxDepthInternal();
				if ($pDepth < $maxDepth) {
					$maxDepth = $pDepth;
				}
				$current = $parent;
			}
		}

		return [
			'root' => $current,
			'depth' => $varDepth,
			'maxBytes' => $maxBytes,
			'maxDepth' => $maxDepth,
		];
	}

	/** @internal Updates only this CSSUnparsedValue node's scalar byte limit. */
	public function clampMaxBytesScalarInternal(?int $limit): void
	{
		if ($limit !== null && ($this->maxBytes === null || $limit < $this->maxBytes)) {
			$this->maxBytes = $limit;
		}
	}

	/** @internal Propagates a stricter parent maxBytes limit down unparsed trees iteratively. */
	public function clampMaxBytesInternal(?int $limit): void
	{
		if ($limit === null) {
			return;
		}
		$stack = [$this];
		$visited = [];
		while ($stack !== []) {
			$node = array_pop($stack);
			$id = spl_object_id($node);
			if (isset($visited[$id])) {
				continue;
			}
			$visited[$id] = true;
			$node->clampMaxBytesScalarInternal($limit);
			foreach ($node->values as $item) {
				if ($item instanceof CSSVariableReferenceValue) {
					$item->clampMaxBytesScalarInternal($limit);
					$fb = $item->getFallback();
					if ($fb !== null) {
						$stack[] = $fb;
					}
				}
			}
		}
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
		return $this->values;
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
		if ($offset !== null && (!is_int($offset) || !array_key_exists($offset, $this->values))) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		}
		if ($offset === null && count($this->values) >= CSSStyleValue::MAX_LIST_ITEMS) {
			throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
		}

		$budget = $this->resolveRootBudgetInternal();
		$root = $budget['root'];
		$ancestorVarDepth = $budget['depth'];
		$strictestMaxBytes = $budget['maxBytes'];
		$strictestMaxDepth = $budget['maxDepth'];

		if (is_string($value)) {
			if (CSSSyntaxScanner::hasControlCharacters($value)) {
				throw new InvalidArgumentException('CSS unparsed fragment contains disallowed control characters.');
			}
		} elseif ($value instanceof CSSVariableReferenceValue) {
			$this->assertNoReferenceCycleAndDepth($value, $ancestorVarDepth, $strictestMaxDepth);
			$value = $value->clone();
			$value->clampMaxBytesInternal($strictestMaxBytes);
		} else {
			throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
		}

		$previous = $offset === null ? null : $this->values[$offset];
		$next = $this->values;
		if ($offset === null) {
			$next[] = $value;
		} else {
			$next[$offset] = $value;
		}

		self::validateFragmentArray($next, $strictestMaxBytes);

		$oldValues = $this->values;
		$this->values = $next;
		if ($root !== $this) {
			try {
				if ($root instanceof self) {
					self::stringifyIteratively($root->values, $strictestMaxBytes);
				} else {
					$root->assertRenderedWithinMaxBytesInternal($strictestMaxBytes);
				}
			} catch (Throwable $e) {
				$this->values = $oldValues;
				throw $e;
			}
		}

		if ($previous instanceof CSSVariableReferenceValue && $previous !== $value) {
			$previous->setParentUnparsedInternal(null);
		}
		if ($value instanceof CSSVariableReferenceValue) {
			$value->setParentUnparsedInternal($this);
		}
	}

	public function offsetUnset(mixed $offset): void
	{
		if (!is_int($offset) || !array_key_exists($offset, $this->values)) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		}
		$budget = $this->resolveRootBudgetInternal();
		$removed = $this->values[$offset];
		$next = $this->values;
		array_splice($next, $offset, 1);
		self::validateFragmentArray($next, $budget['maxBytes']);

		$oldValues = $this->values;
		$this->values = $next;
		if ($budget['root'] !== $this) {
			try {
				if ($budget['root'] instanceof self) {
					self::stringifyIteratively($budget['root']->values, $budget['maxBytes']);
				} else {
					$budget['root']->assertRenderedWithinMaxBytesInternal($budget['maxBytes']);
				}
			} catch (Throwable $e) {
				$this->values = $oldValues;
				throw $e;
			}
		}

		if ($removed instanceof CSSVariableReferenceValue) {
			$removed->setParentUnparsedInternal(null);
		}
	}

	/** @return Traversable<int, string|CSSVariableReferenceValue> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function __clone(): void
	{
		$this->parentRef = null;
		// Iteratively clone descendant var() / unparsed trees with scalar limit propagation (O(N), O(1) PHP frames).
		$stack = [[&$this->values, 0, $this->maxBytes, $this]];
		while ($stack !== []) {
			$entry = array_pop($stack);
			/** @var list<string|CSSVariableReferenceValue> $list */
			$list = &$entry[0];
			$depth = $entry[1];
			$effectiveLimit = $entry[2];
			$ownerUnparsed = $entry[3];
			if ($depth > self::MAX_UNPARSED_DEPTH) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', self::MAX_UNPARSED_DEPTH));
			}
			foreach ($list as $i => $item) {
				if ($item instanceof CSSVariableReferenceValue) {
					$clonedRef = $item->shallowCloneInternal();
					$clonedRef->clampMaxBytesScalarInternal($effectiveLimit);
					$clonedRef->setParentUnparsedInternal($ownerUnparsed);
					$refLimit = $clonedRef->getMaxBytesInternal();
					$list[$i] = $clonedRef;
					$fb = $clonedRef->getFallback();
					if ($fb !== null) {
						$clonedFb = $fb->shallowCloneInternal();
						$clonedFb->clampMaxBytesScalarInternal($refLimit);
						$clonedFb->setParentRefInternal($clonedRef);
						$clonedRef->setFallbackInternal($clonedFb);
						$stack[] = [&$clonedFb->values, $depth + 1, $clonedFb->maxBytes, $clonedFb];
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
		$copy->maxDepth = $this->maxDepth;
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

	/**
	 * @internal Verifies that assigning an existing CSSUnparsedValue tree stays within $maxAllowedDepth and has no cycles.
	 */
	public static function assertTreeMaxDepthInternal(
		self $root,
		int $baseDepth = 0,
		int $maxAllowedDepth = self::MAX_UNPARSED_DEPTH,
	): void {
		if ($baseDepth > $maxAllowedDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxAllowedDepth));
		}
		self::assertUnparsedDepthAndCycles($root, $baseDepth, $maxAllowedDepth, false, null);
	}

	private function assertNoReferenceCycleAndDepth(
		CSSVariableReferenceValue $candidate,
		int $baseDepth = 0,
		int $maxAllowedDepth = self::MAX_UNPARSED_DEPTH,
	): void {
		$rootFb = $candidate->getFallback();
		if ($rootFb === null) {
			if (($baseDepth + 1) > $maxAllowedDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxAllowedDepth));
			}
			return;
		}

		self::assertUnparsedDepthAndCycles($rootFb, $baseDepth, $maxAllowedDepth, true, $this);
	}

	/**
	 * Shared iterative O(N) post-order DFS for cycle detection and subtree depth verification.
	 */
	private static function assertUnparsedDepthAndCycles(
		self $root,
		int $baseDepth,
		int $maxAllowedDepth,
		bool $includeOuterWrapper,
		?self $forbiddenNode,
	): void {
		$heightMemo = [];
		$onStack = [];
		$stack = [[0, $root]];

		while ($stack !== []) {
			[$phase, $fb] = array_pop($stack);
			if ($forbiddenNode !== null && $fb === $forbiddenNode) {
				throw new InvalidArgumentException('Cannot insert a CSSVariableReferenceValue creating a fallback cycle.');
			}
			$id = spl_object_id($fb);
			if ($phase === 1) {
				unset($onStack[$id]);
				$maxChild = 0;
				foreach ($fb->values as $nested) {
					if (!$nested instanceof CSSVariableReferenceValue) {
						continue;
					}
					$nestedFb = $nested->getFallback();
					$childHeight = $nestedFb === null ? 1 : ($heightMemo[spl_object_id($nestedFb)] ?? 0);
					if ($childHeight > $maxChild) {
						$maxChild = $childHeight;
					}
				}
				$height = ($fb === $root && !$includeOuterWrapper) ? $maxChild : (1 + $maxChild);
				$heightMemo[$id] = $height;
				if (($baseDepth + $height) > $maxAllowedDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxAllowedDepth));
				}
				continue;
			}

			if (isset($heightMemo[$id])) {
				if (($baseDepth + $heightMemo[$id]) > $maxAllowedDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $maxAllowedDepth));
				}
				continue;
			}
			if (isset($onStack[$id])) {
				throw new InvalidArgumentException('Cannot insert a CSSVariableReferenceValue creating a fallback cycle.');
			}
			$onStack[$id] = true;
			$stack[] = [1, $fb];
			foreach ($fb->values as $nested) {
				if ($nested instanceof CSSVariableReferenceValue && ($nestedFb = $nested->getFallback()) !== null) {
					$nestedId = spl_object_id($nestedFb);
					if (isset($onStack[$nestedId]) || ($forbiddenNode !== null && $nestedFb === $forbiddenNode)) {
						throw new InvalidArgumentException('Cannot insert a CSSVariableReferenceValue creating a fallback cycle.');
					}
					if (!isset($heightMemo[$nestedId])) {
						$stack[] = [0, $nestedFb];
					}
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
	private static function parseFragmentsIterative(string $source, int $rootDepth, ?int $maxBytes, int $effectiveMaxDepth): array
	{
		if (stripos($source, 'var(') === false && !str_contains($source, '\\')) {
			return [$source];
		}

		$length = strlen($source);
		// Single O(N) pre-pass: map matching '(' => ')' and mark '(' whose direct body contains ';', '{', or '}'.
		$matchingParens = [];
		$parenHasForbidden = [];
		$delimStack = [];
		$maxDelimDepth = max(self::MAX_PREPASS_DELIM_DEPTH, $effectiveMaxDepth * 4);
		$indexedParens = 0;
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
					if (count($delimStack) > $maxDelimDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDelimDepth));
					}
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
				if (++$indexedParens > self::MAX_PREPASS_INDEXED_PARENS) {
					throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d parenthesized groups.', self::MAX_PREPASS_INDEXED_PARENS));
				}
				$delimStack[] = ['(', $offset];
				if (count($delimStack) > $maxDelimDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $maxDelimDepth));
				}
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

				$varStart = null;
				if ($pos >= $sliceStart + 3 && ($source[$pos - 1] === 'r' || $source[$pos - 1] === 'R')
					&& substr_compare($source, 'var', $pos - 3, 3, true) === 0
					&& $lastEscEnd !== $pos - 3
					&& ($pos === $sliceStart + 3 || (!self::isIdentByte($source[$pos - 4]) && $source[$pos - 4] !== '#' && $source[$pos - 4] !== '@'))
					&& isset($matchingParens[$pos])
				) {
					$varStart = $pos - 3;
				} elseif ($lastEsc !== -1 && isset($matchingParens[$pos])) {
					$varStart = CSSSyntaxScanner::findPrecedingEscapedIdentStart($source, $pos, $sliceStart, 'var');
				}

				if ($varStart !== null) {
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
								if (($depth + 1) > $effectiveMaxDepth) {
									throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
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
									$fbObj = self::createShell($maxBytes, $effectiveMaxDepth);
									if ($fbStart < $fbEnd) {
										$workStack[] = [$fbStart, $fbEnd, $depth + 1, $fbObj];
									}
								}

								if ($varStart > $fragmentStart) {
									if (count($fragments) >= CSSStyleValue::MAX_LIST_ITEMS) {
										throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
									}
									$fragments[] = substr($source, $fragmentStart, $varStart - $fragmentStart);
								}
								if (count($fragments) >= CSSStyleValue::MAX_LIST_ITEMS) {
									throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
								}
								$fragments[] = CSSVariableReferenceValue::createParsed($normalizedVariable, $fbObj, $maxBytes, $effectiveMaxDepth);
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
				if (count($fragments) >= CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
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
