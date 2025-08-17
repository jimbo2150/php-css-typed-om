<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tokenizer;

/**
 * CSS3 Tokenizer that parses CSS3 syntax according to CSS Syntax Module Level 3
 * This tokenizer handles CSS3 syntax including custom properties, calc(), and modern CSS features
 */
class CSS3Tokenizer
{
    private string $input;
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    private array $tokens = [];
    
    // Character constants
    private const EOF = '';
    private const WHITESPACE = " \t\n\r\f";
    private const NEWLINE = "\n\r\f";
    
    public function __construct(string $css)
    {
        $this->input = $css;
    }
    
    /**
     * Tokenize the entire CSS input
     */
    public function tokenize(): array
    {
        $this->tokens = [];
        $this->position = 0;
        $this->line = 1;
        $this->column = 1;
        
        while ($this->position < strlen($this->input)) {
            $token = $this->nextToken();
            if ($token !== null) {
                $this->tokens[] = $token;
            }
        }
        
        $this->tokens[] = CSS3Token::eof($this->line, $this->column);
        
        return $this->tokens;
    }
    
    /**
     * Get the next token from input
     */
    private function nextToken(): ?CSS3Token
    {
        $this->consumeWhitespace();
        
        if ($this->isAtEnd()) {
            return null;
        }
        
        $char = $this->peek();
        
        // Handle different token types
        return match (true) {
            $this->isCommentStart() => $this->consumeComment(),
            $this->isStringStart($char) => $this->consumeString($char),
            $this->isNumberStart($char) => $this->consumeNumber(),
            $char === '#' => $this->consumeHash(),
            $char === '@' => $this->consumeAtKeyword(),
            $char === '"' || $char === "'" => $this->consumeString($char),
            $this->isIdentifierStart($char) => $this->consumeIdentifier(),
            $this->isWhitespace($char) => $this->consumeWhitespace(),
            default => $this->consumeDelimiter($char)
        };
    }
    
    /**
     * Consume and return a comment token
     */
    private function consumeComment(): CSS3Token
    {
        $start = $this->position;
        $startLine = $this->line;
        $startColumn = $this->column;
        
        // Skip /*
        $this->advance(2);
        
        $value = '';
        while (!$this->isAtEnd()) {
            if ($this->peek() === '*' && $this->peek(1) === '/') {
                $this->advance(2);
                break;
            }
            
            if ($this->peek() === "\n") {
                $this->line++;
                $this->column = 1;
            }
            
            $value .= $this->advance();
        }
        
        return CSS3Token::comment($value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return a string token
     */
    private function consumeString(string $quote): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        // Skip opening quote
        $this->advance();
        
        $value = '';
        $representation = $quote;
        
        while (!$this->isAtEnd()) {
            $char = $this->peek();
            
            if ($char === $quote) {
                $representation .= $quote;
                $this->advance();
                break;
            }
            
            if ($char === "\n") {
                return CSS3Token::string($value, $representation, $startLine, $startColumn);
            }
            
            if ($char === '\\') {
                $this->advance();
                $next = $this->peek();
                
                if ($next === "\n") {
                    $this->advance();
                    continue;
                }
                
                if (ctype_xdigit($next)) {
                    $hex = '';
                    $i = 0;
                    while ($i < 6 && ctype_xdigit($this->peek($i))) {
                        $hex .= $this->peek($i);
                        $i++;
                    }
                    $this->advance(strlen($hex));
                    $value .= html_entity_decode('&#x' . $hex . ';');
                    continue;
                }
                
                $value .= $this->advance();
                continue;
            }
            
            $value .= $this->advance();
        }
        
        return CSS3Token::string($value, $representation, $startLine, $startColumn);
    }
    
    /**
     * Consume and return a number token
     */
    private function consumeNumber(): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $value = '';
        $hasDecimal = false;
        $hasExponent = false;
        
        // Handle sign
        if ($this->peek() === '+' || $this->peek() === '-') {
            $value .= $this->advance();
        }
        
        // Handle digits before decimal
        while (ctype_digit($this->peek())) {
            $value .= $this->advance();
        }
        
        // Handle decimal
        if ($this->peek() === '.' && ctype_digit($this->peek(1))) {
            $value .= $this->advance();
            $hasDecimal = true;
            while (ctype_digit($this->peek())) {
                $value .= $this->advance();
            }
        }
        
        // Handle exponent
        if (strtolower($this->peek()) === 'e') {
            $value .= $this->advance();
            $hasExponent = true;
            
            if ($this->peek() === '+' || $this->peek() === '-') {
                $value .= $this->advance();
            }
            
            while (ctype_digit($this->peek())) {
                $value .= $this->advance();
            }
        }
        
        $numericValue = (float) $value;
        
        // Check for unit (dimension)
        if ($this->isIdentifierStart($this->peek())) {
            $unit = $this->consumeIdentifier()->value;
            return CSS3Token::dimension($numericValue, $unit, $value . $unit, $startLine, $startColumn);
        }
        
        // Check for percentage
        if ($this->peek() === '%') {
            $this->advance();
            return CSS3Token::percentage($numericValue, $value . '%', $startLine, $startColumn);
        }
        
        return CSS3Token::number($numericValue, $value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return a hash token
     */
    private function consumeHash(): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $this->advance(); // Skip #
        
        $value = '';
        while ($this->isIdentifierPart($this->peek())) {
            $value .= $this->advance();
        }
        
        return CSS3Token::hash($value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return an at-keyword token
     */
    private function consumeAtKeyword(): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $this->advance(); // Skip @
        
        $value = '';
        while ($this->isIdentifierPart($this->peek())) {
            $value .= $this->advance();
        }
        
        return new CSS3Token(CSS3TokenType::AT_KEYWORD, $value, null, '@' . $value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return an identifier token
     */
    private function consumeIdentifier(): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $value = '';
        while ($this->isIdentifierPart($this->peek())) {
            $value .= $this->advance();
        }
        
        // Check if this is a function
        if ($this->peek() === '(') {
            $this->advance();
            return new CSS3Token(CSS3TokenType::FUNCTION, $value, null, $value . '(', $startLine, $startColumn);
        }
        
        return CSS3Token::ident($value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return a delimiter token
     */
    private function consumeDelimiter(string $char): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $this->advance();
        
        // Handle multi-character delimiters
        $twoChar = $char . $this->peek();
        
        return match ($twoChar) {
            '~=' => new CSS3Token(CSS3TokenType::INCLUDE_MATCH, '~=', null, '~=', $startLine, $startColumn),
            '|=' => new CSS3Token(CSS3TokenType::DASH_MATCH, '|=', null, '|=', $startLine, $startColumn),
            '^=' => new CSS3Token(CSS3TokenType::PREFIX_MATCH, '^=', null, '^=', $startLine, $startColumn),
            '$=' => new CSS3Token(CSS3TokenType::SUFFIX_MATCH, '$=', null, '$=', $startLine, $startColumn),
            '*=' => new CSS3Token(CSS3TokenType::SUBSTRING_MATCH, '*=', null, '*=', $startLine, $startColumn),
            '||' => new CSS3Token(CSS3TokenType::COLUMN, '||', null, '||', $startLine, $startColumn),
            default => CSS3Token::delim($char, $startLine, $startColumn)
        };
    }
    
    /**
     * Consume and return a whitespace token
     */
    private function consumeWhitespaceToken(): CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        
        $value = '';
        while ($this->isWhitespace($this->peek())) {
            $char = $this->advance();
            $value .= $char;
            
            if ($char === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
        }
        
        return CSS3Token::whitespace($value, $startLine, $startColumn);
    }
    
    /**
     * Check if current position is at comment start
     */
    private function isCommentStart(): bool
    {
        return $this->peek() === '/' && $this->peek(1) === '*';
    }
    
    /**
     * Check if character is string start
     */
    private function isStringStart(string $char): bool
    {
        return $char === '"' || $char === "'";
    }
    
    /**
     * Check if character is number start
     */
    private function isNumberStart(string $char): bool
    {
        return ctype_digit($char) ||
               ($char === '.' && ctype_digit($this->peek(1))) ||
               (($char === '+' || $char === '-') && (ctype_digit($this->peek(1)) ||
                                                     ($this->peek(1) === '.' && ctype_digit($this->peek(2)))));
    }
    
    /**
     * Check if character is identifier start
     */
    private function isIdentifierStart(string $char): bool
    {
        return ctype_alpha($char) || $char === '_' || $char === '-' || ord($char) >= 128;
    }
    
    /**
     * Check if character is identifier part
     */
    private function isIdentifierPart(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-' || ord($char) >= 128;
    }
    
    /**
     * Check if character is whitespace
     */
    private function isWhitespace(string $char): bool
    {
        return strpos(self::WHITESPACE, $char) !== false;
    }
    
    /**
     * Skip whitespace without creating tokens
     */
    private function skipWhitespace(): void
    {
        while ($this->isWhitespace($this->peek())) {
            $char = $this->advance();
            if ($char === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
        }
    }
    
    /**
     * Consume whitespace characters
     */
    private function consumeWhitespace(): void
    {
        $this->skipWhitespace();
    }
    
    /**
     * Get current character
     */
    private function peek(int $offset = 0): string
    {
        $pos = $this->position + $offset;
        return $pos < strlen($this->input) ? $this->input[$pos] : self::EOF;
    }
    
    /**
     * Advance position and return character
     */
    private function advance(int $count = 1): string
    {
        $char = $this->peek();
        $this->position += $count;
        $this->column += $count;
        return $char;
    }
    
    /**
     * Check if at end of input
     */
    private function isAtEnd(): bool
    {
        return $this->position >= strlen($this->input);
    }
}