<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tokenizer;

/**
 * Represents a single CSS3 token
 * Based on CSS Syntax Module Level 3 specification
 */
class CSS3Token
{
    public function __construct(
        public readonly CSS3TokenType $type,
        public readonly string $value,
        public readonly ?string $unit = null,
        public readonly ?string $representation = null,
        public readonly int $line = 1,
        public readonly int $column = 1,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Whether tokens should be normalized on creation
     */
    public static bool $normalize = false;

    /**
     * Check if this token is a numeric token (number, percentage, or dimension)
     */
    public function isNumeric(): bool
    {
        return in_array($this->type, [
            CSS3TokenType::NUMBER,
            CSS3TokenType::PERCENTAGE,
            CSS3TokenType::DIMENSION
        ], true);
    }

    /**
     * Check if this token is a whitespace token
     */
    public function isWhitespace(): bool
    {
        return $this->type === CSS3TokenType::WHITESPACE;
    }

    /**
     * Check if this token is a comment token
     */
    public function isComment(): bool
    {
        return $this->type === CSS3TokenType::COMMENT;
    }

    /**
     * Check if this token is an identifier
     */
    public function isIdentifier(): bool
    {
        return $this->type === CSS3TokenType::IDENT;
    }

    /**
     * Check if this token is a string
     */
    public function isString(): bool
    {
        return $this->type === CSS3TokenType::STRING;
    }

    /**
     * Get the numeric value if this is a numeric token
     */
    public function getNumericValue(): ?float
    {
        if (!$this->isNumeric()) {
            return null;
        }

        return (float) $this->value;
    }

    /**
     * Get the unit if this is a dimension or percentage
     */
    public function getUnit(): ?string
    {
        return $this->unit;
    }

    /**
     * Convert token to string representation
     */
    public function __toString(): string
    {
        $repr = $this->representation ?? $this->value;
        
        if ($this->type === CSS3TokenType::STRING) {
            return '"' . str_replace('"', '\\"', $repr) . '"';
        }
        
        return $repr;
    }

    /**
     * Create a whitespace token
     */
    public static function whitespace(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::WHITESPACE, $value, null, $value, $line, $column);
    }

    /**
     * Create an identifier token
     */
    public static function ident(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::IDENT, $value, null, $value, $line, $column);
    }

    /**
     * Create a property token
     */
    public static function property(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::PROPERTY, $value, null, $value, $line, $column);
    }

    /**
     * Create a number token
     */
    public static function number(float $value, string $representation, int $line = 1, int $column = 1): self
    {
    $isInt = (string)((int) $value) === (string) $value;
        $meta = ['raw' => $representation, 'isInteger' => $isInt];
        if (self::$normalize) {
            $meta['normalized'] = (string) $value;
        }
        return new self(CSS3TokenType::NUMBER, (string) $value, null, $representation, $line, $column, $meta);
    }

    /**
     * Create a dimension token (number with unit)
     */
    public static function dimension(float $value, string $unit, string $representation, int $line = 1, int $column = 1): self
    {
        $isInt = (string)((int) $value) === (string) $value;
        $meta = ['raw' => $representation, 'isInteger' => $isInt, 'unit' => $unit];
        if (self::$normalize) {
            $meta['normalized'] = (string) $value . $unit;
        }
        return new self(CSS3TokenType::DIMENSION, (string) $value, $unit, $representation, $line, $column, $meta);
    }

    /**
     * Create a percentage token
     */
    public static function percentage(float $value, string $representation, int $line = 1, int $column = 1): self
    {
        $isInt = (string)((int) $value) === (string) $value;
        $meta = ['raw' => $representation, 'isInteger' => $isInt];
        if (self::$normalize) {
            $meta['normalized'] = (string) $value . '%';
        }
        return new self(CSS3TokenType::PERCENTAGE, (string) $value, '%', $representation, $line, $column, $meta);
    }

    /**
     * Create a string token
     */
    public static function string(string $value, string $representation, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::STRING, $value, null, $representation, $line, $column);
    }

    /**
     * Create a bad-string token
     */
    public static function badString(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::BAD_STRING, $value, null, $value, $line, $column);
    }

    /**
     * Create a url token
     */
    public static function url(string $value, int $line = 1, int $column = 1): self
    {
        $meta = ['raw' => $value];
        if (self::$normalize) {
            $meta['normalized'] = trim($value);
        }
        return new self(CSS3TokenType::URL, $value, null, $value, $line, $column, $meta);
    }

    /**
     * Create a bad-url token
     */
    public static function badUrl(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::BAD_URL, $value, null, $value, $line, $column, ['raw' => $value]);
    }

    public static function unicodeRange(string $value, int $line = 1, int $column = 1): self
    {
        $meta = ['raw' => $value];
        if (self::$normalize) {
            $meta['normalized'] = strtoupper($value);
        }
        return new self(CSS3TokenType::UNICODE_RANGE, $value, null, $value, $line, $column, $meta);
    }

    /**
     * Create a hash token
     */
    public static function hash(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::HASH, $value, null, '#' . $value, $line, $column);
    }

    /**
     * Create a delimiter token
     */
    public static function delim(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::DELIM, $value, null, $value, $line, $column);
    }

    /**
     * Create a comment token
     */
    public static function comment(string $value, int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::COMMENT, $value, null, "/*{$value}*/", $line, $column);
    }

    /**
     * Create an EOF token
     */
    public static function eof(int $line = 1, int $column = 1): self
    {
        return new self(CSS3TokenType::EOF, '', null, '', $line, $column);
    }
}