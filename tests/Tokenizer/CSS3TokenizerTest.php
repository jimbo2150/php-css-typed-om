<?php

declare(strict_types=1);

namespace Tests\Tokenizer;

use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Tokenizer;
use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3TokenType;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSS3 Tokenizer
 */
class CSS3TokenizerTest extends TestCase
{
    public function testBasicTokenization()
    {
        $css = 'color: red; font-size: 16px;';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        $this->assertCount(9, $tokens); // ident, delim, whitespace, ident, semicolon, whitespace, ident, semicolon, eof

        $this->assertEquals(CSS3TokenType::IDENT, $tokens[0]->type);
        $this->assertEquals('color', $tokens[0]->value);

        $this->assertEquals(CSS3TokenType::DELIM, $tokens[1]->type);
        $this->assertEquals(':', $tokens[1]->value);
    }
    
    public function testNumbers()
    {
        $css = 'width: 100px; height: 50%;';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        // Find dimension token
        $dimensionToken = null;
        foreach ($tokens as $token) {
            if ($token->type === CSS3TokenType::DIMENSION) {
                $dimensionToken = $token;
                break;
            }
        }

        $this->assertNotNull($dimensionToken);
        $this->assertEquals('100', $dimensionToken->value);
        $this->assertEquals('px', $dimensionToken->unit);
    }
    
    public function testStrings()
    {
        $css = 'content: "Hello World";';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        // Find string token
        $stringToken = null;
        foreach ($tokens as $token) {
            if ($token->type === CSS3TokenType::STRING) {
                $stringToken = $token;
                break;
            }
        }

        $this->assertNotNull($stringToken);
        $this->assertEquals('Hello World', $stringToken->value);
    }
    
    public function testComments()
    {
        $css = '/* This is a comment */ color: blue;';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        // Comments are skipped in tokenization
        $this->assertCount(6, $tokens); // ident, colon, whitespace, ident, semicolon, eof
    }
    
    public function testComplexCSS()
    {
        $css = '.class { margin: 10px 20px; color: #ff0000; }';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        $this->assertGreaterThan(5, count($tokens));

        // Check for hash token
        $hashToken = null;
        foreach ($tokens as $token) {
            if ($token->type === CSS3TokenType::HASH) {
                $hashToken = $token;
                break;
            }
        }

        $this->assertNotNull($hashToken);
        $this->assertEquals('ff0000', $hashToken->value);
    }
}