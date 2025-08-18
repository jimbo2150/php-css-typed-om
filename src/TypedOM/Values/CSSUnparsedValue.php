<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents a CSS value that has not yet been parsed.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnparsedValue
 */
class CSSUnparsedValue extends CSSStyleValue implements \IteratorAggregate
{
	/** @var array<string|CSSVariableReferenceValue> */
	private array $members;

	public function __construct(array $members)
	{
		$this->members = $members;
		parent::__construct('unparsed');
	}

	public function getMembers(): array
	{
		return $this->members;
	}

	public function toString(): string
	{
		$result = '';
		foreach ($this->members as $member) {
			$result .= (string) $member;
		}

		return $result;
	}

	public function isValid(): bool
	{
		return true; // Unparsed values are always considered valid.
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->members);
	}

	public function getIterator(): \Traversable
	{
		return new \ArrayIterator($this->members);
	}

	/**
	 * Returns an iterator that yields key-value pairs for each member.
	 *
	 * @return \Traversable<int, array{int, string|CSSVariableReferenceValue}>
	 */
	public function entries(): \Traversable
	{
		foreach ($this->members as $key => $value) {
			yield [$key, $value];
		}
	}

	/**
	 * Executes a provided function once for each member.
	 *
	 * @param callable $callback function to execute for each member
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->members as $key => $value) {
			$callback($value, $key, $this);
		}
	}

	/**
	 * Returns an iterator that yields the keys (indices) for each member.
	 *
	 * @return \Traversable<int, int>
	 */
	public function keys(): \Traversable
	{
		foreach ($this->members as $key => $value) {
			yield $key;
		}
	}

	/**
	 * Magic getter for property access.
	 */
	public function __get(string $name): mixed
	{
		return match ($name) {
			'length' => count($this->members),
			'type' => $this->type,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}

	/**
	 * Magic setter for property access.
	 */
	public function __set(string $name, mixed $value): void
	{
		throw new \Error("Cannot set property: {$name}");
	}
}
