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
use Traversable;

/**
 * Transactional, mutable list of CSS media query strings.
 *
 * @implements ArrayAccess<int, string>
 * @implements IteratorAggregate<int, string>
 */
final class MediaList implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<string> */
	private array $media = [];

	/**
	 * @throws InvalidArgumentException When $mediaText contains invalid syntax
	 */
	public function __construct(string $mediaText = '', private readonly ?Closure $onChange = null)
	{
		$this->setMediaText($mediaText);
	}

	public function getMediaText(): string
	{
		return implode(', ', $this->media);
	}

	/**
	 * Replaces the media query list atomically.
	 *
	 * @throws InvalidArgumentException When any media query contains disallowed structural syntax
	 */
	public function setMediaText(string $mediaText): void
	{
		if ($mediaText !== '') {
			CSSSyntaxScanner::assertValidFragment($mediaText, 'Media query list', [';', '{', '}'], ['{', '}']);
		}

		$media = [];
		$seen = [];
		foreach (CSSSyntaxScanner::splitTopLevel($mediaText, ',') as $medium) {
			$medium = trim($medium);
			if ($medium === '') {
				continue;
			}
			CSSSyntaxScanner::assertValidFragment($medium, 'Media query', [';', '{', '}'], ['{', '}']);
			$lower = strtolower($medium);
			if (!isset($seen[$lower])) {
				$seen[$lower] = true;
				$media[] = $medium;
			}
		}

		$nextText = implode(', ', $media);
		$this->onChange?->__invoke($nextText);
		$this->media = $media;
	}

	/**
	 * Appends a single media query atomically if not already present.
	 *
	 * @throws InvalidArgumentException When $medium is empty or invalid
	 */
	public function appendMedium(string $medium): void
	{
		$medium = trim($medium);
		if ($medium === '') {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}
		CSSSyntaxScanner::assertValidFragment($medium, 'Media query', [',', ';', '{', '}'], ['{', '}']);
		if ($this->contains($medium)) {
			return;
		}

		$nextMedia = [...$this->media, $medium];
		$this->onChange?->__invoke(implode(', ', $nextMedia));
		$this->media = $nextMedia;
	}

	/**
	 * Removes a matching media query atomically.
	 *
	 * @throws InvalidArgumentException When $medium is not present
	 */
	public function deleteMedium(string $medium): void
	{
		$medium = trim($medium);
		foreach ($this->media as $index => $existing) {
			if (strcasecmp($existing, $medium) === 0) {
				$nextMedia = $this->media;
				array_splice($nextMedia, $index, 1);
				$this->onChange?->__invoke(implode(', ', $nextMedia));
				$this->media = $nextMedia;
				return;
			}
		}
		throw new InvalidArgumentException('Media query not found: ' . $medium);
	}

	public function contains(string $medium): bool
	{
		$needle = trim($medium);
		foreach ($this->media as $existing) {
			if (strcasecmp($existing, $needle) === 0) {
				return true;
			}
		}
		return false;
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

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'mediaText' && is_string($value)) {
			$this->setMediaText($value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
