<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tokenizer;

use Jimbo2150\PhpCssTypedOm\Tokenizer\TokenizerState;


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
    /** @var TokenizerState[] */
    private array $stateStack = [TokenizerState::Data];
    /**
     * History of consumed positions for reconsume support. Each entry is
     * ['position'=>int,'line'=>int,'column'=>int].
     *
     * Note: this may grow for large inputs; it's used to correctly restore
     * line/column on reconsume per-spec.
     */
    private array $history = [];
    /**
     * Event listeners: map event name to array of callables
     */
    private array $listeners = [];

    /**
     * Optional PDO handle for property validation DB
     */
    private ?\PDO $propertyDb = null;

    /**
     * Optional path to property DB (constructor configurable)
     */
    private ?string $propertyDbPath = null;

    /**
     * Strict validation: if true, missing DB causes properties to be treated as invalid
     */
    private bool $strictValidation = false;
    
    
    // Character constants
    private const EOF = '';
    private const WHITESPACE = " \t\n\r\f";
    private const NEWLINE = "\n\r\f";
    
    public function __construct(string $css, bool $emitWhitespace = true, ?string $propertyDbPath = null, bool $strictValidation = false)
    {
        $this->input = $css;
        $this->emitWhitespace = $emitWhitespace;
        $this->propertyDbPath = $propertyDbPath;
        $this->strictValidation = $strictValidation;
        $this->loadPropertyDatabase();
    }

    private function validateProperty(string $name): bool
    {
        // If no DB and strictValidation is enabled, treat as invalid
        if ($this->propertyDb === null) {
            return !$this->strictValidation;
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
                // emit error event for listener exceptions but do not break tokenization
                if (!empty($this->listeners['error'])) {
                    foreach ($this->listeners['error'] as $errCb) {
                        try {
                            $errCb($e);
                        } catch (\Throwable) {
                        }
                    }
                }
            }
        }
    }

    private function loadPropertyDatabase(): void
    {
        $path = $this->propertyDbPath ?? (__DIR__ . '/../../dist/CSSProperties/CSSProperties.sqlite');
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

    private function pushState(TokenizerState $state): void
    {
        $this->stateStack[] = $state;
        $this->emit('state-enter', ['state' => $state->value]);
    }

    private function popState(): ?string
    {
        if (count($this->stateStack) <= 1) {
            $val = $this->stateStack[0] ?? null;
            $this->emit('state-exit', ['state' => $val?->value]);
            return $val?->value ?? null;
        }
        $val = array_pop($this->stateStack);
        $this->emit('state-exit', ['state' => $val->value]);
        return $val->value;
    }

    private function currentState(): TokenizerState
    {
        $val = end($this->stateStack);
        return $val instanceof TokenizerState ? $val : TokenizerState::Data;
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
    // reset history for reconsume support per tokenization run
    $this->history = [];
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

        $state = $this->currentState();

        return match ($state) {
            TokenizerState::Data => $this->nextTokenInData(),
            TokenizerState::AtRule => $this->nextTokenInAtRule(),
            TokenizerState::Block => $this->nextTokenInBlock(),
            TokenizerState::Paren => $this->nextTokenInParen(),
            TokenizerState::String => $this->nextTokenInString(),
            TokenizerState::Comment => $this->nextTokenInComment(),
            TokenizerState::Url => $this->nextTokenInUrl(),
            TokenizerState::IdentLike => $this->nextTokenInIdentLike(),
            TokenizerState::Number => $this->nextTokenInNumber(),
            TokenizerState::FunctionState => $this->nextTokenInFunction(),
            TokenizerState::Tag => $this->nextTokenInTag(),
            TokenizerState::UrlEscape => $this->nextTokenInUrlEscape(),
            default => $this->nextTokenInData(),
        };
    }

    /**
     * Handle tokens when in STRING state per the spec scaffold.
     */
    private function nextTokenInString(): ?CSS3Token
    {
        // consumeString expects the opening quote to be present
        return $this->consumeString($this->peek());
    }

    /**
     * Handle tokens when in COMMENT state per the spec scaffold.
     */
    private function nextTokenInComment(): ?CSS3Token
    {
        $this->consumeComment();
        // consumeComment typically consumes until end of comment; pop the comment state
        $this->popState();
        return null;
    }

    /**
     * Handle tokens when in URL state per the spec scaffold.
     */
    private function nextTokenInUrl(): ?CSS3Token
    {
        return $this->consumeURL($this->line, $this->column) ?? null;
    }

    /**
     * Ident-like state handler (ident or function)
     */
    private function nextTokenInIdentLike(): ?CSS3Token
    {
    // Enter ident-like state for observability per-spec
    $this->pushState(TokenizerState::IdentLike);
            $token = $this->consumeIdentifier();
            $this->popState();
            if ($token !== null) {
                return $token;
            }
            return null;
    }

    /**
     * Number state handler
     */
    private function nextTokenInNumber(): ?CSS3Token
    {
    // Enter number state for observability per-spec
    $this->pushState(TokenizerState::Number);
            $token = $this->consumeNumber();
            $this->popState();
            if ($token !== null) {
                return $token;
            }
            return null;
    }

    private function nextTokenInFunction(): ?CSS3Token
    {
        // Function state behaves like paren (content inside the function)
        return $this->nextTokenInParen();
    }

    private function nextTokenInTag(): ?CSS3Token
    {
        // Tag state placeholder for future mapping (e.g., HTML-ish tokenization)
        return $this->nextTokenInData();
    }

    private function nextTokenInUrlEscape(): ?CSS3Token
    {
        // Url-escape state will be used when parsing escaped url sequences
        return $this->nextTokenInUrl();
    }

    /**
     * Tokenization behavior in the data state (main state per spec)
     */
    private function nextTokenInData(): ?CSS3Token
    {
        // Spec-like checks for CDO/CDC sequences
        $next4 = $this->peek(0) . $this->peek(1) . $this->peek(2) . $this->peek(3);
        if ($next4 === '<!--') {
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

        $char = $this->peek();

        if ($this->isCommentStart()) {
            $this->pushState(TokenizerState::Comment);
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
            $this->pushState(TokenizerState::String);
            $tok = $this->consumeString($char);
            $this->popState();
            return $tok;
        }

        if ($this->isNumberStart($char)) {
            return $this->nextTokenInNumber();
        }

        if ($char === '#') {
            return $this->consumeHash();
        }

        if ($char === '@') {
            return $this->consumeAtKeyword();
        }

        if ($this->isIdentifierStart($char)) {
            return $this->nextTokenInIdentLike();
        }

        return $this->consumeDelimiter($char);
    }

    private function nextTokenInAtRule(): ?CSS3Token
    {
        // At-rule mostly follows data state tokenization but semicolon ends the at-rule
        $token = $this->nextTokenInData();
        if ($token !== null) {
            // end at-rule prelude on semicolon
            if ($token->type === CSS3TokenType::SEMICOLON) {
                $this->popState();
            }

            // Opening brace begins a block which ends the at-rule prelude
            if ($token->type === CSS3TokenType::LEFT_BRACE) {
                $this->popState();
            }
        }

        return $token;
    }

    private function nextTokenInBlock(): ?CSS3Token
    {
        // If the next character is '}', consume it and pop the block state immediately
        if ($this->peek() === '}') {
            $startLine = $this->line;
            $startColumn = $this->column;
            $this->advance();
            $this->popState();
            return new CSS3Token(CSS3TokenType::RIGHT_BRACE, '}', null, '}', $startLine, $startColumn);
        }

        // Otherwise behave like data state
        return $this->nextTokenInData();
    }

    private function nextTokenInParen(): ?CSS3Token
    {
        // If the next character is ')', consume it and pop the paren state
        if ($this->peek() === ')') {
            $startLine = $this->line;
            $startColumn = $this->column;
            $this->advance();
            $this->popState();
            return new CSS3Token(CSS3TokenType::RIGHT_PAREN, ')', null, ')', $startLine, $startColumn);
        }

        // Otherwise behave like data state
        return $this->nextTokenInData();
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
                $value .= $this->consumeEscapeSequence();
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
            if ($unitToken !== null) {
                $unit = $unitToken->value;
                return CSS3Token::dimension($numericValue, $unit, $value . $unit, $startLine, $startColumn);
            }
            // If no unit token produced, fall through to number token
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
            if ($this->peek() === '\\') {
                $value .= $this->consumeEscapeSequence();
                continue;
            }
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
    $this->pushState(TokenizerState::AtRule);
		return new CSS3Token(CSS3TokenType::AT_KEYWORD, $value, null, '@' . $value, $startLine, $startColumn);
    }
    
    /**
     * Consume and return an identifier token
     */
    private function consumeIdentifier(): ?CSS3Token
    {
        $startLine = $this->line;
        $startColumn = $this->column;
        $startPos = $this->position;

        $value = '';
        // Accumulate up to the token max length to avoid unbounded memory growth.
        while ($this->isIdentifierPart($this->peek())) {
            if (strlen($value) < \Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Token::$maxTokenLength) {
                $value .= $this->advance();
                continue;
            }

            // We've reached the cap: consume remaining identifier characters without storing them.
            $this->advance();
        }
        $consumedLen = $this->position - $startPos;

        // If nothing was consumed, return null to avoid emitting empty IDENT tokens
        if ($consumedLen === 0) {
            return null;
        }

        // Debugging: if we produced an empty identifier but consumed characters,
        // or consumed length is zero, log context to stderr for diagnosis.
        if ($value === '' || $consumedLen === 0) {
            $context = substr($this->input, $this->position, 16);
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

            // We already consumed the '('. Enter paren state so ')' will pop it.
            $this->pushState(TokenizerState::Paren);
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

        $token = CSS3Token::ident($value, $startLine, $startColumn);

        // If we consumed more than the max, mark token as truncated in metadata.
        if ($consumedLen > \Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Token::$maxTokenLength) {
            $meta = $token->metadata;
            $meta['truncated'] = true;
            $meta['originalLength'] = $consumedLen;
            $token = new \Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Token($token->type, $token->value, $token->unit, $token->representation, $token->line, $token->column, $meta);
        }

        return $token;
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
                    $this->pushState(TokenizerState::Block);
                    return new CSS3Token(CSS3TokenType::LEFT_BRACE, '{', null, '{', $startLine, $startColumn);
                })(),
                '}' => (function() use ($startLine, $startColumn) {
                    // leaving a block
                    $this->popState();
                    return new CSS3Token(CSS3TokenType::RIGHT_BRACE, '}', null, '}', $startLine, $startColumn);
                })(),
                '(' => (function() use ($startLine, $startColumn) {
                    $this->pushState(TokenizerState::Paren);
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
                    if ($this->currentState() === TokenizerState::AtRule) {
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
        $stallCount = 0;
        $lastPosition = $this->position;
        while (!$this->isAtEnd()) {
            $token = $this->nextToken();

            // detect if tokenizer made no forward progress to avoid infinite loops
            if ($this->position === $lastPosition) {
                $stallCount++;
            } else {
                $stallCount = 0;
                $lastPosition = $this->position;
            }

            if ($stallCount > 1000) {
                // Emit an error event and break out to avoid runaway memory growth.
                $this->emit('error', ['message' => 'tokenizer stalled; aborting after repeated non-advancing reads', 'line' => $this->line, 'column' => $this->column]);
                break;
            }

            if ($token !== null) {
                // emit token event
                $this->emit('token', $token);
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
                $value .= $this->consumeEscapeSequence();
                continue;
            }

            $value .= $this->advance();
        }

        // EOF reached before closing ) -> bad-url
        return CSS3Token::badUrl($value, $startLine, $startColumn);
    }

    /**
     * Consume an escape sequence and return the resulting string.
     * Handles hex escapes (up to 6 hex digits) and single-character escapes.
     */
    private function consumeEscapeSequence(): string
    {
        // Enter a transient "url-escape" state for observability
        $this->pushState(TokenizerState::UrlEscape);

        // We expect the backslash has not yet been consumed; consume it now
        $bs = $this->advance();

        if ($this->isAtEnd()) {
            $this->popState();
            return '';
        }

        $next = $this->peek();
        // Hex escape
        if (ctype_xdigit($next)) {
            $hex = '';
            $i = 0;
            while ($i < 6 && ctype_xdigit($this->peek($i))) {
                $hex .= $this->peek($i);
                $i++;
            }
            $this->advance(strlen($hex));
            // optional whitespace after hex escape
            if ($this->isWhitespace($this->peek())) {
                $this->advance();
            }
            $decoded = html_entity_decode('&#x' . $hex . ';');
            $this->popState();
            return $decoded;
        }

        // Single char escape: consume next char literally
        $ch = $this->advance();
        $this->popState();
        return $ch;
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

        // Backslash introduces an escape which can start an identifier
        if ($char === '\\') {
            return true;
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

        // Backslash may start an escape sequence which is part of an identifier
        if ($char === '\\') {
            return true;
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
     * Reconsume the previously-read character by stepping back one position.
     * This supports spec-style "reconsume" behavior inside state handlers.
     */
    private function reconsume(): void
    {
        if (empty($this->history)) {
            return;
        }

        $last = array_pop($this->history);
        $this->position = $last['position'];
        $this->line = $last['line'];
        $this->column = $last['column'];
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
            // push history snapshot so we can reconsume accurately
            $this->history[] = ['position' => $this->position, 'line' => $this->line, 'column' => $this->column];
            // cap history size to avoid unbounded memory usage on long inputs
            if (count($this->history) > 4096) {
                array_shift($this->history);
            }

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