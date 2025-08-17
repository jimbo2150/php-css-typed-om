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
    private bool $emitWhitespace = true;
    /**
     * State stack for tokenizer (scaffold for spec state-machine)
     * Values can be: 'data', 'string', 'url', 'comment', etc.
     * We'll default to 'data'.
     */
    private array $stateStack = ['data'];
    /**
     * Event listeners: map event name to array of callables
     */
    private array $listeners = [];

    /**
     * Optional PDO handle for property validation DB
     */
    private ?\PDO $propertyDb = null;
    
    
    // Character constants
    private const EOF = '';
    private const WHITESPACE = " \t\n\r\f";
    private const NEWLINE = "\n\r\f";
    
    public function __construct(string $css, bool $emitWhitespace = true)
    {
        $this->input = $css;
        $this->emitWhitespace = $emitWhitespace;
        $this->loadPropertyDatabase();
    }

    private function validateProperty(string $name): bool
    {
        // If no DB, assume valid to avoid false negatives
        if ($this->propertyDb === null) {
            return true;
        }

        try {
            $stmt = $this->propertyDb->prepare('SELECT COUNT(1) FROM properties WHERE name = :name');
            $stmt->execute([':name' => $name]);
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\Throwable $e) {
            return true;
        }
    }

    public function on(string $event, callable $cb): void
    {
        $this->listeners[$event][] = $cb;
    }

    private function emit(string $event, mixed $payload = null): void
    {
        if (empty($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $cb) {
            try {
                $cb($payload);
            } catch (\Throwable $e) {
                // swallow listener exceptions to not break tokenization
            }
        }
    }

    private function loadPropertyDatabase(): void
    {
        $path = __DIR__ . '/../../dist/CSSProperties/CSSProperties.sqlite';
        if (!file_exists($path)) {
            $this->propertyDb = null;
            return;
        }

        try {
            $this->propertyDb = new \PDO('sqlite:' . $path);
            $this->propertyDb->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable $e) {
            $this->propertyDb = null;
        }
    }

    /**
     * Consume a unicode-range starting after the '+' has been consumed
     */
    private function consumeUnicodeRange(int $startLine, int $startColumn): ?CSS3Token
    {
        $value = '';

        // read up to 6 hex digits or '*' wildcards
        while (true) {
            $ch = $this->peek();
            if (ctype_xdigit($ch) && strlen($value) < 6) {
                $value .= $this->advance();
                continue;
            }

            if ($ch === '*') {
                // consume sequence of wildcards
                while ($this->peek() === '*') {
                    $value .= $this->advance();
                }
                break;
            }

            break;
        }

        // possible range indicated by '-'
        if ($this->peek() === '-') {
            $value .= $this->advance();
            // read second part (up to 6 hex digits)
            $part = '';
            while (ctype_xdigit($this->peek()) && strlen($part) < 6) {
                $part .= $this->advance();
            }
            if ($part === '') {
                return null;
            }
            $value .= $part;
        }

        if ($value === '') {
            return null;
        }

        return CSS3Token::unicodeRange($value, $startLine, $startColumn);
    }

    private function pushState(string $state): void
    {
        $this->stateStack[] = $state;
    }

    private function popState(): ?string
    {
        if (count($this->stateStack) <= 1) {
            return $this->stateStack[0] ?? null;
        }

        return array_pop($this->stateStack);
    }

    private function currentState(): string
    {
        return end($this->stateStack) ?: 'data';
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
        foreach ($this->tokenizeStream() as $token) {
            $this->tokens[] = $token;
        }

        return $this->tokens;
    }
    
    /**
     * Get the next token from input
     */
    private function nextToken(): ?CSS3Token
    {
        if ($this->isAtEnd()) {
            return null;
        }

        $char = $this->peek();

        // Spec-like checks for CDO/CDC sequences
        $next3 = $this->peek(0) . $this->peek(1) . $this->peek(2) . $this->peek(3);
        if ($next3 === '<!--') {
            // CDO token
            $startLine = $this->line;
            $startColumn = $this->column;
            $this->advance(4);
            return new CSS3Token(CSS3TokenType::CDO, '<!--', null, '<!--', $startLine, $startColumn);
        }

        $next3 = $this->peek(0) . $this->peek(1) . $this->peek(2);
        if ($next3 === '-->') {
            $startLine = $this->line;
            $startColumn = $this->column;
            $this->advance(3);
            return new CSS3Token(CSS3TokenType::CDC, '-->', null, '-->', $startLine, $startColumn);
        }

        // Handle different token types (order is important)
        if ($this->isCommentStart()) {
            // Comments are dropped by the tokenizer (per CSS Syntax)
            $this->pushState('comment');
            $this->consumeComment();
            $this->popState();
            return null;
        }

        if ($this->isWhitespace($char)) {
            if ($this->emitWhitespace) {
                return $this->consumeWhitespaceToken();
            }

            $this->skipWhitespace();
            return null;
        }

        if ($this->isStringStart($char)) {
            $this->pushState('string');
            $tok = $this->consumeString($char);
            $this->popState();
            return $tok;
        }

        if ($this->isNumberStart($char)) {
            return $this->consumeNumber();
        }

        if ($char === '#') {
            return $this->consumeHash();
        }

        if ($char === '@') {
            return $this->consumeAtKeyword();
        }

        if ($this->isIdentifierStart($char)) {
            return $this->consumeIdentifier();
        }

        return $this->consumeDelimiter($char);
    }
    
    /**
     * Consume and return a comment token (or null since comments are not emitted)
     */
    private function consumeComment(): ?CSS3Token
    {
        // Skip opening /*
        $this->advance(2);

        while (!$this->isAtEnd()) {
            if ($this->peek() === '*' && $this->peek(1) === '/') {
                $this->advance(2);
                return null; // comments are not emitted as tokens
            }

            $char = $this->advance();
            if ($char === "\n") {
                $this->line++;
                $this->column = 1;
            }
        }

        // Unterminated comment: treat as finished
        return null;
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
                // Newline in a string is a bad string per the spec
                // Consume to here and return a bad-string token
                return CSS3Token::badString($representation . $value, $startLine, $startColumn);
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
        
        // If we reached EOF without closing quote, it's a bad string
        if ($this->isAtEnd()) {
            return CSS3Token::badString($representation . $value, $startLine, $startColumn);
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
            $unitToken = $this->consumeIdentifier();
            $unit = $unitToken->value;
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

		// Enter at-rule state: we'll remain in at-rule until a '{' or ';' is encountered
		$this->pushState('at-rule');
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
            // Consume '('
            $this->advance();

            // Special-case: url( ... ) should produce a URL or BAD_URL token per spec
            if (strtolower($value) === 'url') {
                $urlToken = $this->consumeURL($startLine, $startColumn);
                if ($urlToken !== null) {
                    return $urlToken;
                }

                // If consumeURL returned null (shouldn't), fall back to FUNCTION
                return new CSS3Token(CSS3TokenType::FUNCTION, $value, null, $value . '(', $startLine, $startColumn);
            }

            return new CSS3Token(CSS3TokenType::FUNCTION, $value, null, $value . '(', $startLine, $startColumn);
        }

        // Detect unicode-range like u+00A-00FF or U+1234
        if (strtolower($value) === 'u' && $this->peek() === '+') {
            // consume '+'
            $this->advance();
            $range = $this->consumeUnicodeRange($startLine, $startColumn);
            if ($range !== null) {
                return $range;
            }
        }
        
        // Peek ahead to see if a colon follows, which would indicate a property declaration
        $peekPosition = $this->position;
        while ($peekPosition < strlen($this->input) && $this->isWhitespace($this->input[$peekPosition])) {
            $peekPosition++;
        }

        if ($peekPosition < strlen($this->input) && $this->input[$peekPosition] === ':') {
            $token = CSS3Token::property($value, $startLine, $startColumn);

            // validate property if not a custom property (doesn't start with --)
            if (!str_starts_with($value, '--')) {
                $isValid = $this->validateProperty($value);
                if (!$isValid) {
                    // attach metadata by creating a new token instance with extra metadata
                    $meta = $token->metadata;
                    $meta['invalidProperty'] = true;
                    $token = new \Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Token($token->type, $token->value, $token->unit, $token->representation, $token->line, $token->column, $meta);
                    $this->emit('invalid-property', ['name' => $value, 'line' => $startLine, 'column' => $startColumn]);
                }
            }

            return $token;
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
            default => match ($char) {
                '{' => (function() use ($startLine, $startColumn) {
                    // entering a block
                    $this->pushState('block');
                    return new CSS3Token(CSS3TokenType::LEFT_BRACE, '{', null, '{', $startLine, $startColumn);
                })(),
                '}' => (function() use ($startLine, $startColumn) {
                    // leaving a block
                    $this->popState();
                    return new CSS3Token(CSS3TokenType::RIGHT_BRACE, '}', null, '}', $startLine, $startColumn);
                })(),
                '(' => (function() use ($startLine, $startColumn) {
                    $this->pushState('paren');
                    return new CSS3Token(CSS3TokenType::LEFT_PAREN, '(', null, '(', $startLine, $startColumn);
                })(),
                ')' => (function() use ($startLine, $startColumn) {
                    $this->popState();
                    return new CSS3Token(CSS3TokenType::RIGHT_PAREN, ')', null, ')', $startLine, $startColumn);
                })(),
                '[' => new CSS3Token(CSS3TokenType::LEFT_BRACKET, '[', null, '[', $startLine, $startColumn),
                ']' => new CSS3Token(CSS3TokenType::RIGHT_BRACKET, ']', null, ']', $startLine, $startColumn),
                ',' => new CSS3Token(CSS3TokenType::COMMA, ',', null, ',', $startLine, $startColumn),
                ':' => new CSS3Token(CSS3TokenType::COLON, ':', null, ':', $startLine, $startColumn),
                ';' => (function() use ($startLine, $startColumn) {
                    // semicolon closes at-rule prelude
                    if ($this->currentState() === 'at-rule') {
                        $this->popState();
                    }
                    return new CSS3Token(CSS3TokenType::SEMICOLON, ';', null, ';', $startLine, $startColumn);
                })(),
                default => CSS3Token::delim($char, $startLine, $startColumn)
            }
        };
    }

    /**
     * Streaming tokenizer: yields tokens as they are produced.
     */
    public function tokenizeStream(): \Generator
    {
        while (!$this->isAtEnd()) {
            $token = $this->nextToken();
            if ($token !== null) {
                yield $token;
            }
        }

        yield CSS3Token::eof($this->line, $this->column);
    }

    /**
     * Consume a url token (assumes the '(' has already been consumed)
     */
    private function consumeURL(int $startLine, int $startColumn): ?CSS3Token
    {
        // Skip leading whitespace
        while ($this->isWhitespace($this->peek())) {
            $this->advance();
        }

        // Empty url
        if ($this->peek() === ')') {
            $this->advance();
            return CSS3Token::url('', $startLine, $startColumn);
        }

        // Quoted url -> treat like function with string inside
        if ($this->isStringStart($this->peek())) {
            $str = $this->consumeString($this->peek());
            if ($str->type === CSS3TokenType::STRING) {
                // After string, skip whitespace then expect ')'
                while ($this->isWhitespace($this->peek())) {
                    $this->advance();
                }

                if ($this->peek() === ')') {
                    $this->advance();
                    return CSS3Token::url($str->value, $startLine, $startColumn);
                }

                return CSS3Token::badUrl('', $startLine, $startColumn);
            }

            // Bad string inside url -> bad-url
            return CSS3Token::badUrl('', $startLine, $startColumn);
        }

        // Raw url: read until ')' but fail on whitespace or EOF
        $value = '';
        while (!$this->isAtEnd()) {
            $ch = $this->peek();
            if ($ch === ')') {
                $this->advance();
                return CSS3Token::url($value, $startLine, $startColumn);
            }

            if ($this->isWhitespace($ch)) {
                // whitespace in unquoted url is invalid -> bad-url
                return CSS3Token::badUrl($value, $startLine, $startColumn);
            }

            if ($ch === '\\') {
                // handle escape sequences in url
                $this->advance();
                if ($this->isAtEnd()) {
                    return CSS3Token::badUrl($value, $startLine, $startColumn);
                }

                $value .= $this->advance();
                continue;
            }

            $value .= $this->advance();
        }

        // EOF reached before closing ) -> bad-url
        return CSS3Token::badUrl($value, $startLine, $startColumn);
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
        if ($char === self::EOF || $char === '') {
            return false;
        }

        $ord = ord($char);
        return ctype_alpha($char) || $char === '_' || $char === '-' || $ord >= 128;
    }
    
    /**
     * Check if character is identifier part
     */
    private function isIdentifierPart(string $char): bool
    {
        if ($char === self::EOF || $char === '') {
            return false;
        }

        $ord = ord($char);
        return ctype_alnum($char) || $char === '_' || $char === '-' || $ord >= 128;
    }
    
    /**
     * Check if character is whitespace
     */
    private function isWhitespace(string $char): bool
    {
        if ($char === self::EOF || $char === '') {
            return false;
        }

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
        $result = '';
        for ($i = 0; $i < $count; $i++) {
            $c = $this->peek($i);
            if ($c === self::EOF) {
                break;
            }
            $result .= $c;
        }

        // Update position and column/line counters for the actual characters consumed
        for ($i = 0; $i < strlen($result); $i++) {
            if ($result[$i] === "\n") {
                $this->line++;
                $this->column = 1;
            } else {
                $this->column++;
            }
            $this->position++;
        }

        return $result;
    }
    
    /**
     * Check if at end of input
     */
     private function isAtEnd(): bool
     {
         return $this->position >= strlen($this->input);
     }

    
}