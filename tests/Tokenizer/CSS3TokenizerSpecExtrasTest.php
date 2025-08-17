<?php

declare(strict_types=1);

namespace Tests\Tokenizer;

use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Tokenizer;
use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3TokenType;
use PHPUnit\Framework\TestCase;

class CSS3TokenizerSpecExtrasTest extends TestCase
{
    public function testUnicodeRangeToken()
    {
        $css = 'u+00A-00FF {}';
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        $found = false;
        foreach ($tokens as $t) {
            if ($t->type === CSS3TokenType::UNICODE_RANGE) {
                $this->assertEquals('00A-00FF', $t->value);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'unicode-range token should be produced');
    }

    public function testRawUrlToken()
    {
        $css = "background: url(http://example.com/a.png);";
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        $found = null;
        foreach ($tokens as $t) {
            if ($t->type === CSS3TokenType::URL) {
                $found = $t;
                break;
            }
        }

        $this->assertNotNull($found, 'URL token should be produced');
        $this->assertEquals('http://example.com/a.png', $found->value);
    }
}
