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
use Jimbo2150\PhpCssTypedOm\Parser\CSSMediaQueryParser;
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

	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;

	/**
	 * @throws InvalidArgumentException When $mediaText contains invalid syntax
	 */
	public function __construct(
		string $mediaText = '',
		?Closure $onChange = null,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $browserErrorRecovery = false,
	) {
		CSSRule::assertValidMaxBytesInternal($maxBytes);
		$this->onChange = $onChange;
		$this->maxBytes = $maxBytes;
		$this->applyMediaText($mediaText, $browserErrorRecovery);
	}

	/** @internal */
	public function setOwnerInternal(CSSRule|CSSStyleSheet|null $owner): void
	{
		$this->owner = $owner !== null ? WeakReference::create($owner) : null;
	}

	/** @internal */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	/** @internal */
	public function setMaxBytesInternal(?int $maxBytes): void
	{
		$this->maxBytes = $maxBytes;
	}

	public function __clone(): void
	{
		$this->maxBytes = $this->resolveMaxBytes();
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
			return CSSRule::minLimitInternal($this->maxBytes, $owner->getMaxBytesInternal());
		}
		if ($owner instanceof CSSRule) {
			return CSSRule::minLimitInternal($this->maxBytes, $owner->resolveMaxBytesInternal());
		}

		return $this->maxBytes;
	}

	private function resolveMaxDepth(): ?int
	{
		$owner = $this->owner?->get();
		if ($owner instanceof CSSStyleSheet) {
			return $owner->getMaxDepthInternal();
		}
		if ($owner instanceof CSSRule) {
			return $owner->getParentStyleSheet()?->getMaxDepthInternal() ?? CSSParserOptions::DEFAULT_MAX_DEPTH;
		}

		return CSSParserOptions::DEFAULT_MAX_DEPTH;
	}

	private function buildParserOptions(?int $maxBytes): CSSParserOptions
	{
		return new CSSParserOptions(
			maxBytes: $maxBytes,
			maxDepth: $this->resolveMaxDepth(),
		);
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
		$this->applyMediaText($mediaText, false);
	}

	private function applyMediaText(string $mediaText, bool $browserErrorRecovery): void
	{
		$maxBytes = $this->resolveMaxBytes();
		if ($maxBytes !== null && strlen($mediaText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $maxBytes));
		}
		if (!$browserErrorRecovery && $mediaText !== '') {
			CSSSyntaxScanner::assertValidFragment($mediaText, 'Media query list', [';', '{', '}'], ['{', '}']);
		}

		$parsed = CSSMediaQueryParser::parseList($mediaText, $this->buildParserOptions($maxBytes), $browserErrorRecovery);

		$media = [];
		$seen = [];
		foreach ($parsed as $canonical) {
			if (!isset($seen[$canonical])) {
				if (count($media) >= CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				$seen[$canonical] = true;
				$media[] = $canonical;
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
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($medium);
		if ($trimmed === '') {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, 'Media query', [',', ';', '{', '}'], ['{', '}']);
		$canonical = CSSMediaQueryParser::parseSingle($trimmed, $this->buildParserOptions($maxBytes), false);
		if (isset($this->mediaIndex[$canonical])) {
			return;
		}
		if (count($this->media) >= CSSStyleValue::MAX_LIST_ITEMS) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
		}

		$nextBytes = $this->media === [] ? strlen($canonical) : ($this->serializedBytes + 2 + strlen($canonical));
		if ($maxBytes !== null && $nextBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($this->onChange !== null) {
			$nextMedia = [...$this->media, $canonical];
			($this->onChange)(implode(', ', $nextMedia));
			$this->media = $nextMedia;
		} else {
			$this->media[] = $canonical;
		}
		$this->mediaIndex[$canonical] = true;
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
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($medium);
		if ($trimmed === '') {
			throw new InvalidArgumentException('Media query not found: ' . $trimmed);
		}
		$canonical = CSSMediaQueryParser::parseSingle($trimmed, $this->buildParserOptions($maxBytes), false);
		if (!isset($this->mediaIndex[$canonical])) {
			throw new InvalidArgumentException('Media query not found: ' . $trimmed);
		}
		foreach ($this->media as $index => $existing) {
			if ($existing === $canonical) {
				$nextMedia = $this->media;
				array_splice($nextMedia, $index, 1);
				$nextText = implode(', ', $nextMedia);
				$this->onChange?->__invoke($nextText);
				$this->media = $nextMedia;
				unset($this->mediaIndex[$canonical]);
				$this->serializedBytes = strlen($nextText);
				return;
			}
		}
		throw new InvalidArgumentException('Media query not found: ' . $trimmed);
	}

	public function contains(string $medium): bool
	{
		$maxBytes = $this->resolveMaxBytes();
		if ($maxBytes !== null && strlen($medium) > $maxBytes) {
			return false;
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($medium);
		if ($trimmed === '') {
			return false;
		}
		try {
			$canonical = CSSMediaQueryParser::parseSingle($trimmed, $this->buildParserOptions($maxBytes), false);
		} catch (CSSParserLimitException) {
			return false;
		} catch (InvalidArgumentException) {
			return false;
		}

		return isset($this->mediaIndex[$canonical]);
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
