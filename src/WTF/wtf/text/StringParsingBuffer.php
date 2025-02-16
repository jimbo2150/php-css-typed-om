<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text;

use Jimbo2150\PhpCssTypedOm\Parser\StringType;

class StringParsingBuffer
{
	use StringType;

	private array $intlCharArray;
	private int $offset = 0;

	public function __construct(resource|string $characters)
	{
		$intlCharIterator = \IntlBreakIterator::createCharacterInstance();
		$intlCharIterator->setText(
			is_resource($characters) ?
				fread($characters, -1) :
				$characters
		);
		$this->intlCharArray = iterator_to_array($intlCharIterator->getPartsIterator());
		assert(count($this->intlCharArray) > 0);
	}

	public function position(): int
	{
		return $this->offset;
	}

	public function size(): int
	{
		return count($this->intlCharArray);
	}

	public function offsetSize(): int
	{
		return $this->size() - 1;
	}

	public function end(): mixed
	{
		$this->offset = $this->offsetSize();

		return $this->intlCharArray[$this->offset];
	}

	public function hasCharactersRemaining(): bool
	{
		return $this->lengthRemaining() > 0;
	}

	public function atEnd(): bool
	{
		return $this->lengthRemaining() <= 0;
	}

	public function lengthRemaining(): int
	{
		return $this->offsetSize() - $this->offset;
	}

	public function setPosition(int $position): void
	{
		assert($position > -1 && $position <= $this->offsetSize());
		$this->offset = $position % $this->offsetSize();
	}

	public function stringViewOfCharactersRemaining(): StringView
	{
		return $this->span();
	}

	public function span(): StringType
	{
		$c = new static((string) array_slice(
			$this->intlCharArray,
			$this->offset, -1
		));
		$this->offset = $this->offsetSize();

		return $c;
	}

	public function consume(int $count = -1): ?StringType
	{
		if (0 == $count) {
			return null;
		} elseif ($count < 0) {
			$count = $this->lengthRemaining() - $count + 1;
		}
		assert($count <= $this->lengthRemaining());
		$result = new static((string) array_slice(
			$this->intlCharArray,
			$this->offset,
			$count
		));
		$this->offset += $count;

		return $result;
	}

	public function advance(): void
	{
		$this->advanceBy(1);
	}

	public function advanceBy(int $places = 0): void
	{
		assert($places > 0);
		assert($places <= $this->lengthRemaining());
		$this->offset += $places;
	}

	public function __toString(): string
	{
		return (string) $this->intlCharArray;
	}
}
