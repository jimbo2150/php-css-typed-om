<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayIterator;
use Closure;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/** Mutable list of media query strings. */
final class MediaList implements Countable, IteratorAggregate
{
	/** @var list<string> */
	private array $media = [];

	public function __construct(string $mediaText = '', private readonly ?Closure $onChange = null)
	{
		$this->setMediaText($mediaText);
	}

	public function getMediaText(): string
	{
		return implode(', ', $this->media);
	}

	public function setMediaText(string $mediaText): void
	{
		$media = [];
		foreach (CSSSyntaxScanner::splitTopLevel($mediaText, ',') as $medium) {
			$medium = trim($medium);
			if ($medium !== '') {
				$media[] = $medium;
			}
		}
		$this->media = array_values(array_unique($media));
		$this->onChange?->__invoke($this->getMediaText());
	}

	public function appendMedium(string $medium): void
	{
		$medium = trim($medium);
		if ($medium === '' || str_contains($medium, ',')) {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}
		if (!$this->contains($medium)) {
			$this->media[] = $medium;
			$this->onChange?->__invoke($this->getMediaText());
		}
	}

	public function deleteMedium(string $medium): void
	{
		$medium = trim($medium);
		foreach ($this->media as $index => $existing) {
			if (strcasecmp($existing, $medium) === 0) {
				array_splice($this->media, $index, 1);
				$this->onChange?->__invoke($this->getMediaText());
				return;
			}
		}
		throw new InvalidArgumentException('Media query not found: ' . $medium);
	}

	public function contains(string $medium): bool
	{
		foreach ($this->media as $existing) {
			if (strcasecmp($existing, trim($medium)) === 0) {
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
			default => throw new \Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'mediaText' && is_string($value)) {
			$this->setMediaText($value);
			return;
		}
		throw new \Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
