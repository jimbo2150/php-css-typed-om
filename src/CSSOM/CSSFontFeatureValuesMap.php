<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Traversable;

/**
 * Map of named font feature values to their integer index lists.
 *
 * @property-read int $size
 *
 * @implements IteratorAggregate<string, list<int>>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFeatureValuesMap
 */
final class CSSFontFeatureValuesMap implements Countable, IteratorAggregate
{
	/** @var array<string, list<int>> */
	private array $values = [];

	/** @var array<string, int> */
	private array $entryBytes = [];

	private int $contentBytes = 0;

	private ?CSSFontFeatureValuesRule $ownerRule = null;
	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
	private ?int $maxDeclarations = CSSParserOptions::DEFAULT_MAX_DECLARATIONS;

	/** @internal */
	public function setOwnerRuleInternal(?CSSFontFeatureValuesRule $ownerRule): void
	{
		$this->ownerRule = $ownerRule;
	}

	/** @internal */
	public function resolveMaxBytesInternal(): ?int
	{
		return CSSRule::minLimitInternal(
			$this->maxBytes,
			$this->ownerRule !== null ? $this->ownerRule->resolveMaxBytesInternal() : null,
		);
	}

	/** @internal */
	public function resolveMaxDeclarationsInternal(): ?int
	{
		return CSSRule::minLimitInternal(
			$this->maxDeclarations,
			$this->ownerRule !== null ? $this->ownerRule->resolveMaxDeclarationsInternal() : null,
		);
	}

	/** @internal */
	public function setLimitsInternal(?int $maxBytes, ?int $maxDeclarations): void
	{
		$this->maxBytes = $maxBytes;
		$this->maxDeclarations = $maxDeclarations;
	}

	/** @internal */
	public function clampLimitsInternal(?int $maxBytes, ?int $maxDeclarations): void
	{
		$this->maxBytes = CSSRule::minLimitInternal($this->maxBytes, $maxBytes);
		$this->maxDeclarations = CSSRule::minLimitInternal($this->maxDeclarations, $maxDeclarations);
	}

	/** @internal */
	public function setMaxBytesInternal(?int $maxBytes): void
	{
		$this->maxBytes = $maxBytes;
	}

	/** @internal */
	public function getContentBytesInternal(): int
	{
		return $this->contentBytes;
	}

	/** @internal */
	public function contentBytesInternal(?int $maxBytes = null): int
	{
		if ($maxBytes !== null && $this->contentBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS font feature map exceeds maximum size of %d bytes.', $maxBytes));
		}

		return $this->contentBytes;
	}

	public function __clone(): void
	{
		$this->maxBytes = $this->resolveMaxBytesInternal();
		$this->maxDeclarations = $this->resolveMaxDeclarationsInternal();
		$this->ownerRule = null;
	}

	/** @return list<int>|null */
	public function get(string $name): ?array
	{
		return $this->values[$name] ?? null;
	}

	/**
	 * @internal Direct array access for hot internal loops without entries() copy overhead.
	 * @return array<string, list<int>>
	 */
	public function getEntriesRaw(): array
	{
		return $this->values;
	}

	/**
	 * @param int|list<int> $values
	 * @throws InvalidArgumentException When the feature name or values are invalid or exceed limits
	 */
	public function set(string $name, int|array $values): void
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();
		$maxBytes = $this->resolveMaxBytesInternal();
		[$name, $normalizedValues, $newBytes] = self::validateFeatureEntry($name, $values, $maxBytes);
		$isNewKey = !array_key_exists($name, $this->values);
		$oldBytes = $this->entryBytes[$name] ?? 0;

		$ruleBytes = $this->contentBytes;
		$ruleDecls = count($this->values);
		if ($this->ownerRule !== null) {
			$ruleBytes = 0;
			$ruleDecls = 0;
			foreach ($this->ownerRule->getFeatureMaps() as $map) {
				$ruleBytes += $map->contentBytes;
				$ruleDecls += count($map->values);
			}
		}

		if ($maxBytes !== null && ($ruleBytes - $oldBytes + $newBytes) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS font feature map exceeds maximum size of %d bytes.', $maxBytes));
		}

		if ($isNewKey) {
			$maxDecls = $this->resolveMaxDeclarationsInternal();
			if ($maxDecls !== null && ($ruleDecls + 1) > $maxDecls) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $maxDecls));
			}
			if ($sheet !== null) {
				$sheetMaxDecls = $sheet->getMaxDeclarationsInternal();
				if ($sheetMaxDecls !== null && ($sheet->getTotalDeclarationCount() + 1) > $sheetMaxDecls) {
					throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $sheetMaxDecls));
				}
			}
		}

		$this->values[$name] = $normalizedValues;
		$this->entryBytes[$name] = $newBytes;
		$this->contentBytes += $newBytes - $oldBytes;
		if ($isNewKey && $sheet !== null) {
			$sheet->adjustTotalDeclarationCount(1);
		}
	}

	/**
	 * @internal Stores an entry during stylesheet parsing using caller-configured $maxBytes.
	 * @param int|list<int> $values
	 */
	public function setParsedInternal(string $name, int|array $values, ?int $maxBytes = null): void
	{
		[$name, $normalizedValues, $newBytes] = self::validateFeatureEntry($name, $values, $maxBytes);
		$oldBytes = $this->entryBytes[$name] ?? 0;
		$this->values[$name] = $normalizedValues;
		$this->entryBytes[$name] = $newBytes;
		$this->contentBytes += $newBytes - $oldBytes;
	}

	public function has(string $name): bool
	{
		return array_key_exists($name, $this->values);
	}

	public function delete(string $name): void
	{
		if (!array_key_exists($name, $this->values)) {
			return;
		}
		$this->contentBytes -= $this->entryBytes[$name] ?? 0;
		unset($this->values[$name], $this->entryBytes[$name]);
		$this->ownerRule?->getParentStyleSheet()?->adjustTotalDeclarationCount(-1);
	}

	public function clear(): void
	{
		$removed = count($this->values);
		if ($removed === 0) {
			return;
		}
		$this->values = [];
		$this->entryBytes = [];
		$this->contentBytes = 0;
		$this->ownerRule?->getParentStyleSheet()?->adjustTotalDeclarationCount(-$removed);
	}

	/**
	 * @param int|list<int> $values
	 * @return array{0: string, 1: list<int>, 2: int}
	 */
	private static function validateFeatureEntry(string $name, int|array $values, ?int $maxBytes): array
	{
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS font feature name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$name = CSSSyntaxScanner::trimCssWhitespace($name);
		if ($name === '' || CSSSyntaxScanner::hasControlCharacters($name)) {
			throw new InvalidArgumentException('A valid non-empty font feature identifier is required.');
		}
		$decoded = CSSSyntaxScanner::decodeIdentAt($name, 0, strlen($name));
		if ($decoded === null || $decoded['end'] !== strlen($name)) {
			throw new InvalidArgumentException('A valid non-empty font feature identifier is required.');
		}
		if (is_int($values)) {
			$values = [$values];
		}
		if ($values === []) {
			throw new InvalidArgumentException('A font feature value list requires at least one integer.');
		}
		if (count($values) > CSSStyleValue::MAX_LIST_ITEMS) {
			throw new CSSParserLimitException(sprintf('CSS font feature value list exceeds maximum item count of %d.', CSSStyleValue::MAX_LIST_ITEMS));
		}
		$normalized = array_values($values);
		foreach ($normalized as $value) {
			if (!is_int($value) || $value < 0) {
				throw new InvalidArgumentException('A feature name and non-negative integer value list are required.');
			}
		}
		$entryBytes = strlen($name) + 3;
		$first = true;
		foreach ($normalized as $value) {
			$entryBytes += ($first ? 0 : 1) + strlen((string) $value);
			$first = false;
			if ($maxBytes !== null && $entryBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS font feature entry exceeds maximum size of %d bytes.', $maxBytes));
			}
		}

		return [$name, $normalized, $entryBytes];
	}

	public function count(): int
	{
		return count($this->values);
	}

	public function getSize(): int
	{
		return count($this->values);
	}

	/** @return list<string> */
	public function keys(): array
	{
		return array_keys($this->values);
	}

	/** @return list<list<int>> */
	public function values(): array
	{
		return array_values($this->values);
	}

	/** @return list<array{string, list<int>}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->values as $key => $value) {
			$entries[] = [$key, $value];
		}

		return $entries;
	}

	/**
	 * Invokes $callback($values, $name, $this) for each entry.
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->values as $name => $values) {
			$callback($values, $name, $this);
		}
	}

	/** @return Traversable<string, list<int>> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'size') {
			return $this->getSize();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'size';
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
