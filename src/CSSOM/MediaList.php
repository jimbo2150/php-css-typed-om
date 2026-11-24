<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Traversable;
use WeakReference;

/**
 * Transactional, mutable list of CSS media query strings.
 *
 * @property string $mediaText
 * @property-read int $length
 *
 * @implements ArrayAccess<int, string>
 * @implements IteratorAggregate<int, string>
 */
final class MediaList implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<string> */
	private array $media = [];

	/** @var array<string, true> */
	private array $mediaIndex = [];

	private int $serializedBytes = 0;

	private ?Closure $onChange;

	/** @var ?WeakReference<CSSRule|CSSStyleSheet> */
	private ?WeakReference $owner = null;

	/**
	 * @throws InvalidArgumentException When $mediaText contains invalid syntax
	 */
	public function __construct(string $mediaText = '', ?Closure $onChange = null)
	{
		$this->onChange = $onChange;
		$this->setMediaText($mediaText);
	}

	/** @internal */
	public function setOwnerInternal(CSSRule|CSSStyleSheet|null $owner): void
	{
		$this->owner = $owner !== null ? WeakReference::create($owner) : null;
	}

	public function __clone(): void
	{
		$this->onChange = null;
		$this->owner = null;
	}

	/**
	 * @internal Creates an independent copy bound to a new change callback without re-parsing.
	 */
	public function cloneWithCallback(?Closure $onChange): self
	{
		$copy = clone $this;
		$copy->onChange = $onChange;

		return $copy;
	}

	private function resolveMaxBytes(): ?int
	{
		$owner = $this->owner?->get();
		if ($owner instanceof CSSStyleSheet) {
			return $owner->getMaxBytesInternal();
		}
		if ($owner instanceof CSSRule) {
			$sheet = $owner->getParentStyleSheet();
			return $sheet !== null ? $sheet->getMaxBytesInternal() : CSSParserOptions::DEFAULT_MAX_BYTES;
		}

		return CSSParserOptions::DEFAULT_MAX_BYTES;
	}

	public function getMediaText(): string
	{
		return implode(', ', $this->media);
	}

	/**
	 * Replaces the media query list atomically.
	 *
	 * @throws InvalidArgumentException When any media query contains disallowed structural syntax or exceeds size limits
	 */
	public function setMediaText(string $mediaText): void
	{
		$maxBytes = $this->resolveMaxBytes();
		if ($maxBytes !== null && strlen($mediaText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($mediaText !== '') {
			CSSSyntaxScanner::assertValidFragment($mediaText, 'Media query list', [';', '{', '}'], ['{', '}']);
		}

		$media = [];
		$seen = [];
		foreach (CSSSyntaxScanner::splitTopLevel($mediaText, ',', 0, null, CSSStyleValue::MAX_LIST_ITEMS) as $medium) {
			$medium = CSSSyntaxScanner::trimCssWhitespace($medium);
			if ($medium === '') {
				continue;
			}
			CSSSyntaxScanner::assertValidFragment($medium, 'Media query', [';', '{', '}'], ['{', '}']);
			if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($medium)) === '') {
				continue;
			}
			$lower = strtolower($medium);
			if (!isset($seen[$lower])) {
				if (count($media) >= CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				$seen[$lower] = true;
				$media[] = $medium;
			}
		}

		$nextText = implode(', ', $media);
		$nextBytes = strlen($nextText);
		if ($maxBytes !== null && $nextBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->onChange?->__invoke($nextText);
		$this->media = $media;
		$this->mediaIndex = $seen;
		$this->serializedBytes = $nextBytes;
	}

	/**
	 * Appends a single media query atomically if not already present.
	 *
	 * @throws InvalidArgumentException When $medium is empty, invalid, or exceeds size limits
	 */
	public function appendMedium(string $medium): void
	{
		$maxBytes = $this->resolveMaxBytes();
		if ($maxBytes !== null && strlen($medium) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query exceeds maximum size of %d bytes.', $maxBytes));
		}
		$medium = CSSSyntaxScanner::trimCssWhitespace($medium);
		if ($medium === '') {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}
		CSSSyntaxScanner::assertValidFragment($medium, 'Media query', [',', ';', '{', '}'], ['{', '}']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($medium)) === '') {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}
		$lower = strtolower($medium);
		if (isset($this->mediaIndex[$lower])) {
			return;
		}
		if (count($this->media) >= CSSStyleValue::MAX_LIST_ITEMS) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
		}

		$nextBytes = $this->media === [] ? strlen($medium) : ($this->serializedBytes + 2 + strlen($medium));
		if ($maxBytes !== null && $nextBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($this->onChange !== null) {
			$nextMedia = [...$this->media, $medium];
			($this->onChange)(implode(', ', $nextMedia));
			$this->media = $nextMedia;
		} else {
			$this->media[] = $medium;
		}
		$this->mediaIndex[$lower] = true;
		$this->serializedBytes = $nextBytes;
	}

	/**
	 * Removes a matching media query atomically.
	 *
	 * @throws InvalidArgumentException When $medium is not present
	 */
	public function deleteMedium(string $medium): void
	{
		$maxBytes = $this->resolveMaxBytes();
		if ($maxBytes !== null && strlen($medium) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query exceeds maximum size of %d bytes.', $maxBytes));
		}
		$medium = CSSSyntaxScanner::trimCssWhitespace($medium);
		$lower = strtolower($medium);
		if (!isset($this->mediaIndex[$lower])) {
			throw new InvalidArgumentException('Media query not found: ' . $medium);
		}
		foreach ($this->media as $index => $existing) {
			if (strcasecmp($existing, $medium) === 0) {
				$nextMedia = $this->media;
				array_splice($nextMedia, $index, 1);
				$nextText = implode(', ', $nextMedia);
				$this->onChange?->__invoke($nextText);
				$this->media = $nextMedia;
				unset($this->mediaIndex[$lower]);
				$this->serializedBytes = strlen($nextText);
				return;
			}
		}
		throw new InvalidArgumentException('Media query not found: ' . $medium);
	}

	public function contains(string $medium): bool
	{
		$needle = strtolower(CSSSyntaxScanner::trimCssWhitespace($medium));
		return $needle !== '' && isset($this->mediaIndex[$needle]);
	}

	public function item(int $index): string
	{
		return $this->media[$index] ?? '';
	}

	public function count(): int
	{
		return count($this->media);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && isset($this->media[$offset]);
	}

	public function offsetGet(mixed $offset): ?string
	{
		return is_int($offset) ? ($this->media[$offset] ?? null) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new InvalidArgumentException('Use appendMedium() or mediaText to modify a MediaList.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('Use deleteMedium() or mediaText to modify a MediaList.');
	}

	/** @return Traversable<int, string> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->media);
	}

	public function __toString(): string
	{
		return $this->getMediaText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'mediaText' => $this->getMediaText(),
			'length' => $this->count(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'mediaText' || $name === 'length';
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'mediaText' && is_string($value)) {
			$this->setMediaText($value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
