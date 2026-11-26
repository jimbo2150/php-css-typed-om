<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

/**
 * Immutable representation of a single CSS Syntax Level 3 §4.2 token.
 *
 * Note on numeric semantics: `$numericValue` uses IEEE-754 PHP `float` semantics
 * (e.g. `1e999` produces `INF`, matching CSS Syntax §4.3.11) and is distinct from
 * `CSSUnitValue`'s strictly finite WebIDL `double` invariant.
 */
final readonly class CSSToken
{
	public const string HASH_ID = 'id';
	public const string HASH_UNRESTRICTED = 'unrestricted';
	public const string NUMERIC_INTEGER = 'integer';
	public const string NUMERIC_NUMBER = 'number';

	/**
	 * @param CSSTokenType $type W3C CSS Syntax L3 token type
	 * @param string $value Decoded string value (or delim code point; empty for whitespace/structural/bad/EOF tokens)
	 * @param int $start Inclusive byte offset in the tokenized source string
	 * @param int $end Exclusive byte offset in the tokenized source string
	 * @param ?string $flag `'id'|'unrestricted'` for `Hash`; `'integer'|'number'` for `Number` and `Dimension`; `null` for `Percentage` and all others
	 * @param ?float $numericValue Numeric value for `Number`, `Percentage`, and `Dimension` tokens
	 * @param ?string $unit Decoded unit identifier for `Dimension` tokens
	 * @param ?string $repr Raw numeric representation for `Number`, `Percentage`, and `Dimension` tokens
	 */
	public function __construct(
		public CSSTokenType $type,
		public string $value,
		public int $start,
		public int $end,
		public ?string $flag = null,
		public ?float $numericValue = null,
		public ?string $unit = null,
		public ?string $repr = null,
	) {
	}

	public function isHashId(): bool
	{
		return $this->type === CSSTokenType::Hash && $this->flag === self::HASH_ID;
	}

	public function isInteger(): bool
	{
		return ($this->type === CSSTokenType::Number || $this->type === CSSTokenType::Dimension)
			&& $this->flag === self::NUMERIC_INTEGER;
	}

	public function isEof(): bool
	{
		return $this->type === CSSTokenType::Eof;
	}

	public function getRawSlice(string $source): string
	{
		return substr($source, $this->start, $this->end - $this->start);
	}
}
