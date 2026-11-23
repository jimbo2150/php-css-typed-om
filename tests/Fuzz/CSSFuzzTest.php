<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Fuzz;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

final class CSSFuzzTest extends TestCase
{
	private const array TOKENS = [
		'a', 'div', '.card', '#id', '@media', '@layer', '@supports', '@import', '@page', '@keyframes', '@font-feature-values',
		'screen', 'color', 'margin', 'width', 'red', '10px', 'calc(', 'min(', 'max(', 'var(', 'var(/*c*/--a, 1px)',
		'url(', 'rgb(', ')', '{', '}', ';', ':', ',', '/', ' ', "\t", "\n", "\x0B",
		'/*', '*/', '/*c*/', '"', "'", '\\', '\\61 ', '!important', '--x', '<style>', '</style>',
	];

	public function testSeededStylesheetScannerAndTypedOmFuzzingIsSafeAndDeterministic(): void
	{
		$rng = new Randomizer(new Xoshiro256StarStar(0x5EED_C550));
		$options = new CSSParserOptions(maxBytes: 4096, maxDepth: 16, maxRules: 64, maxMathDepth: 12, maxErrors: 32);

		for ($i = 0; $i < 150; $i++) {
			$input = $this->randomCss($rng, $rng->getInt(4, 36));

			// Scanner primitives must always terminate in linear time and respect bounds
			CSSSyntaxScanner::isBalanced($input);
			CSSSyntaxScanner::hasControlCharacters($input);
			CSSSyntaxScanner::removeComments($input);
			CSSSyntaxScanner::splitTopLevel($input, ';');
			CSSSyntaxScanner::findLastTopLevelImportant($input);

			// Stylesheet parsing must never crash and must obey error & rule caps
			$sheet = CSSStyleSheet::parse($input, $options);
			self::assertLessThanOrEqual($options->maxErrors + 1, count($sheet->getParseErrors()));
			self::assertLessThanOrEqual($options->maxRules + 1, $sheet->getTotalRuleCount());

			$rendered = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
			$escaped = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Pretty, escapeHtmlStyleTag: true));
			self::assertIsString($rendered);
			self::assertDoesNotMatchRegularExpression('/<\/style(?=[\t\n\f\r \/>]|$)/i', $escaped);

			// Typed OM entry points & CSS::supports must only throw InvalidArgumentException (or return bool)
			$slice = substr($input, 0, 96);
			self::assertIsBool(CSS::supports($slice, null, $options));
			try {
				self::assertInstanceOf(CSSStyleValue::class, CSSStyleValue::parse('width', $slice, $options));
			} catch (InvalidArgumentException) {
				// Expected on malformed random CSS fragments
			}
			try {
				self::assertInstanceOf(
					\Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::class,
					new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue([$slice]),
				);
			} catch (InvalidArgumentException) {
				// Expected on unbalanced/control-byte fragments
			}
			foreach ([
				static fn () => CSSNumericValue::parse($slice, null, $options),
				static fn () => CSSCalcParser::parse($slice, $options),
				static fn () => CSSColorValue::parse($slice, null, $options),
				static fn () => CSSTransformValue::fromCssText($slice, $options),
				static fn () => CSSPositionValue::fromCssText($slice, $options),
			] as $call) {
				try {
					self::assertInstanceOf(CSSStyleValue::class, $call());
				} catch (InvalidArgumentException) {
					// Expected on malformed random CSS fragments
				}
			}
		}

		// Targeted edge cases: hex escapes, unclosed url(), nested math depth, unparsed fragments
		foreach ([
			'a { content: "\\61 b"; background: url(http://example.com/"a))',
			'width: calc(min(10px, max(20px, 30px)));',
			"@media screen {\n  a\\2f  b { color: red; }\n}",
		] as $edge) {
			$edgeSheet = CSSStyleSheet::parse($edge, $options);
			self::assertIsString(CSSRenderer::render($edgeSheet, new CSSRenderOptions(CSSRenderMode::Minified, escapeHtmlStyleTag: true)));
		}
	}

	private function randomCss(Randomizer $rng, int $tokenCount): string
	{
		$out = '';
		$count = count(self::TOKENS) - 1;
		for ($i = 0; $i < $tokenCount; $i++) {
			$out .= self::TOKENS[$rng->getInt(0, $count)];
		}

		return $out;
	}
}
