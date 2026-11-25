<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Fuzz;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

final class CSSFuzzTest extends TestCase
{
	private const array TOKENS = [
		'a', 'div', '.card', '#id', '@media', '@layer', '@supports', '@import', '@page', '@keyframes', '@font-feature-values',
		'@scope', '@container', '@starting-style', '@function', '@counter-style', '@function --f(--a <number>: 1) returns <number> { result: var(--a); }',
		'@font-feature-values Demo { @styleset { alt: 1 2; } }',
		'screen', 'color', 'margin', 'width', 'red', '10px', 'calc(', 'min(', 'max(', 'var(', 'var(/*c*/--a, 1px)',
		'var(--b, var(--c, 1px))', 'layer', 'layer(x)', 'supports(display: grid)',
		'url(', 'rgb(', ')', '{', '}', ';', ':', ',', '/', ' ', "\t", "\n", "\x00", "\x07", "\x0B", "\x7F",
		'/*', '*/', '/*c*/', '/* comment */', '"', "'", '\\', '\\61 ', '\\75 ', '\\7 ', '\\2d\\2d ', '\\000031 ', '!important', '--x', '<style>', '</style>',
		'url(a[b)', 'layer(', 'supports(', "\xFF",
	];

	public function testSeededStylesheetScannerAndTypedOmFuzzingIsSafeAndDeterministic(): void
	{
		$rng = new Randomizer(new Xoshiro256StarStar(0x5EED_C550));
		$options = new CSSParserOptions(maxBytes: 4096, maxDepth: 16, maxRules: 64, maxDeclarations: 256, maxMathDepth: 12, maxErrors: 32);

		for ($i = 0; $i < 150; $i++) {
			$input = $this->randomCss($rng, $rng->getInt(4, 36));

			// Scanner primitives must always terminate in linear time and uphold structural invariants
			CSSSyntaxScanner::isBalanced($input);
			self::assertSame(
				str_contains($input, "\x00") || str_contains($input, "\x07") || str_contains($input, "\x0B") || str_contains($input, "\x7F"),
				CSSSyntaxScanner::hasControlCharacters($input),
			);
			$stripped = CSSSyntaxScanner::removeComments($input);
			self::assertSame($stripped, CSSSyntaxScanner::removeComments($stripped));
			self::assertSame($input, implode(';', CSSSyntaxScanner::splitTopLevel($input, ';')));
			CSSSyntaxScanner::findLastTopLevelImportant($input);

			// Stylesheet parsing must never crash and must obey error, rule, and declaration caps
			$sheet = CSSStyleSheet::parse($input, $options);
			self::assertLessThanOrEqual($options->maxErrors + 1, count($sheet->getParseErrors()));
			self::assertLessThanOrEqual($options->maxRules, $sheet->getTotalRuleCount());
			self::assertLessThanOrEqual($options->maxDeclarations, $sheet->getTotalDeclarationCount());
			foreach ($sheet->cssRules as $topRule) {
				if ($topRule instanceof CSSFunctionRule) {
					self::assertLessThanOrEqual(CSSStyleValue::MAX_LIST_ITEMS, count($topRule->getParameters()));
				}
			}

			$clonedSheet = clone $sheet;
			self::assertSame($sheet->getTotalRuleCount(), $clonedSheet->getTotalRuleCount());

			$rendered = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
			$escaped = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Pretty, escapeHtmlStyleTag: true));
			$noComments = CSSRenderer::render($sheet, new CSSRenderOptions(
				CSSRenderMode::Minified,
				keepComments: false,
				ruleCallback: static fn (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule $rule, string $text): string => $text,
			));
			self::assertSame($noComments, CSSSyntaxScanner::removeComments($noComments));
			self::assertDoesNotMatchRegularExpression('/<\/style(?=[\t\n\f\r \/>]|$)/i', $escaped);
			$reparsed = CSSStyleSheet::parse($rendered, $options);
			self::assertSame($rendered, CSSRenderer::render($reparsed, new CSSRenderOptions(CSSRenderMode::Minified)));

			// Standalone declaration parsing also stays bounded and crash-free
			$fuzzDiags = [];
			$decl = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration::parse(
				substr($input, 0, 128),
				false,
				$fuzzDiags,
				new CSSParserOptions(maxBytes: 4096, maxDeclarations: 128, maxErrors: 32),
			);
			self::assertLessThanOrEqual(128, count($decl));
			self::assertLessThanOrEqual(32, count($fuzzDiags));

			// Typed OM entry points & CSS::supports/escape must only throw InvalidArgumentException or succeed
			$slice = substr($input, 0, 96);
			CSS::supports($slice, null, $options);
			CSS::supports('color', $slice, $options);
			self::assertNotSame('', CSS::escape($slice === '' ? 'a' : $slice));
			try {
				self::assertInstanceOf(CSSStyleValue::class, CSSStyleValue::parse('width', $slice, $options));
			} catch (InvalidArgumentException) {
				// Expected on malformed random CSS fragments
			}
			try {
				foreach (CSSStyleValue::parseAll('background-image', $slice, $options) as $item) {
					self::assertInstanceOf(CSSStyleValue::class, $item);
				}
			} catch (InvalidArgumentException) {
				// Expected on empty or malformed random CSS value lists
			}
			try {
				self::assertInstanceOf(
					CSSUnparsedValue::class,
					new CSSUnparsedValue([$slice], $options),
				);
			} catch (InvalidArgumentException) {
				// Expected on unbalanced/control-byte fragments
			}
			try {
				$parsedUnparsed = CSSUnparsedValue::fromCssText($slice, $options);
				self::assertInstanceOf(CSSUnparsedValue::class, $parsedUnparsed);
				self::assertLessThanOrEqual($options->maxBytes, strlen((string) $parsedUnparsed));
			} catch (InvalidArgumentException) {
				// Expected on unbalanced/over-limit/control-byte fragments
			}
			foreach ([
				static fn () => CSSNumericValue::parse($slice, null, $options),
				static fn () => CSSCalcParser::parse($slice, $options),
				static fn () => CSSColorValue::parse($slice, null, $options),
				static fn () => CSSTransformValue::fromCssText($slice, $options),
				static fn () => CSSPositionValue::fromCssText($slice, $options),
			] as $call) {
				try {
					$val = $call();
					self::assertInstanceOf(CSSStyleValue::class, $val);
					self::assertLessThanOrEqual(CSSParserOptions::DEFAULT_MAX_BYTES, strlen((string) $val));
				} catch (InvalidArgumentException) {
					// Expected on malformed random CSS fragments
				}
			}

			foreach ([
				static fn () => new CSSImportRule($slice, $options->maxBytes),
				static fn () => CSSFunctionRule::fromPrelude($slice, maxBytes: $options->maxBytes),
				static fn () => new CSSScopeRule($slice, maxBytes: $options->maxBytes),
				static fn () => CSSParser::parseSingleRule($slice, $options),
				static fn () => new DOMMatrix($slice),
			] as $cssomCall) {
				try {
					$cssomCall();
				} catch (InvalidArgumentException) {
					// Expected on malformed or over-limit random CSS fragments
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
			$edgeRendered = CSSRenderer::render($edgeSheet, new CSSRenderOptions(CSSRenderMode::Minified, escapeHtmlStyleTag: true));
			self::assertDoesNotMatchRegularExpression('/<\/style(?=[\t\n\f\r \/>]|$)/i', $edgeRendered);
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
