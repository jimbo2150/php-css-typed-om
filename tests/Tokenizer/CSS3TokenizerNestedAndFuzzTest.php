<?php

declare(strict_types=1);

namespace Tests\Tokenizer;

use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3Tokenizer;
use Jimbo2150\PhpCssTypedOm\Tokenizer\CSS3TokenType;

use PHPUnit\Framework\TestCase;

class CSS3TokenizerNestedAndFuzzTest extends TestCase
{
    public function testNestedBlocksAndAtRules()
    {
        $css = "@media screen and (min-width: 900px) { .foo { color: red; @supports (display: grid) { .bar { display: grid; } } } }";
        $tokenizer = new CSS3Tokenizer($css);
        $tokens = $tokenizer->tokenize();

        // Ensure we have at-keyword tokens and braces
        $hasAt = false;
        $leftBraces = 0;
        foreach ($tokens as $t) {
            if ($t->type === CSS3TokenType::AT_KEYWORD) {
                $hasAt = true;
            }
            if ($t->type === CSS3TokenType::LEFT_BRACE) {
                $leftBraces++;
            }
        }

        $this->assertTrue($hasAt, 'Expected at-keyword tokens');
        $this->assertGreaterThanOrEqual(3, $leftBraces, 'Expected multiple nested left braces');
    }

    public function testSimpleFuzzing()
    {
        // Simple fuzzing: generate many small random snippets and ensure tokenizer doesn't throw
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789:{}()@#\"' .,-_;\/\n";

        // Reduced iterations/length to avoid intermittent memory spikes in CI
        for ($i = 0; $i < 100; $i++) {
            $len = rand(1, 40);
            $s = '';
            for ($j = 0; $j < $len; $j++) {
                $s .= $chars[rand(0, strlen($chars) - 1)];
            }

            $t = new CSS3Tokenizer($s);
            $tokens = $t->tokenize();

            $this->assertIsArray($tokens);
            // basic sanity: last token should be EOF
            $this->assertNotEmpty($tokens);
            $last = end($tokens);
            $this->assertEquals(CSS3TokenType::EOF, $last->type);

            // free token memory immediately to avoid accumulating large arrays
            unset($tokens, $last);
            gc_collect_cycles();
        }
    }
}
