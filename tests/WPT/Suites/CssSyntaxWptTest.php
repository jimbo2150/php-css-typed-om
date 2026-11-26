<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\WPT\Suites;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptAssertions;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptContractTestCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureCase;
use Jimbo2150\PhpCssTypedOm\Tests\WPT\Harness\WptFixtureLoader;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * WPT conformance suite for css/css-syntax.
 */
final class CssSyntaxWptTest extends WptContractTestCase
{
	/**
	 * @return array<string, array{WptFixtureCase}>
	 */
	public static function provideCases(): array
	{
		return WptFixtureLoader::loadDomain('css/css-syntax');
	}

	#[DataProvider('provideCases')]
	public function testConformance(WptFixtureCase $case): void
	{
		$this->assertCase($case, $this->verifyCase(...));
	}

	private function verifyCase(WptFixtureCase $case): void
	{
		match ($case->operation) {
			'syntax.ident.escape' => $this->runIdentEscape($case),
			'syntax.stylesheet.recover' => $this->runStylesheetRecover($case),
			'syntax.declaration.recover' => $this->runDeclarationRecover($case),
			'syntax.scanner.split' => $this->runScannerSplit($case),
			'syntax.eof' => $this->runEof($case),
			'syntax.preprocess' => $this->runPreprocess($case),
			'syntax.tokenize' => $this->runTokenize($case),
			default => throw new LogicException(sprintf('Unsupported css/css-syntax operation "%s" in case "%s".', $case->operation, $case->id)),
		};
	}

	private function runIdentEscape(WptFixtureCase $case): void
	{
		$raw = $case->input['raw'] ?? null;
		if (!is_string($raw)) {
			throw new LogicException(sprintf('Case "%s" requires string input.raw.', $case->id));
		}
		$mode = $case->input['mode'] ?? 'decode';
		$expected = $case->expected;

		if ($mode === 'decode') {
			$expectedValue = $expected['value'] ?? null;
			if (!is_string($expectedValue)) {
				throw new LogicException(sprintf('Case "%s" requires string expected.value.', $case->id));
			}
			self::assertSame($expectedValue, CSSSyntaxScanner::decodeCssIdentEscapes($raw), $case->id);
			return;
		}

		if ($mode === 'normalizeProperty') {
			$expectedName = $expected['value'] ?? null;
			if (!is_string($expectedName)) {
				throw new LogicException(sprintf('Case "%s" requires string expected.value.', $case->id));
			}
			self::assertSame($expectedName, CSSStyleDeclaration::normalizePropertyName($raw), $case->id);
			return;
		}

		throw new LogicException(sprintf('Unsupported ident escape mode "%s".', (string) $mode));
	}

	private function runStylesheetRecover(WptFixtureCase $case): void
	{
		$css = $case->input['css'] ?? null;
		if (!is_string($css)) {
			throw new LogicException(sprintf('Case "%s" requires string input.css.', $case->id));
		}

		$sheet = CSSStyleSheet::parse($css);
		$expected = $case->expected;

		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
		}
		if (array_key_exists('ruleCount', $expected)) {
			self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
		}
		if (array_key_exists('hasErrors', $expected)) {
			if ($expected['hasErrors']) {
				self::assertNotEmpty($sheet->getParseErrors(), $case->id);
			} else {
				self::assertSame([], $sheet->getParseErrors(), $case->id);
			}
		}
		if (array_key_exists('firstRuleColor', $expected)) {
			$rule = $sheet->cssRules[0] ?? null;
			self::assertInstanceOf(CSSStyleRule::class, $rule, $case->id);
			self::assertSame((string) $expected['firstRuleColor'], $rule->style->getPropertyValue('color'), $case->id);
		}
	}

	private function runDeclarationRecover(WptFixtureCase $case): void
	{
		$css = $case->input['css'] ?? null;
		if (!is_string($css)) {
			throw new LogicException(sprintf('Case "%s" requires string input.css.', $case->id));
		}
		$strict = (bool) ($case->input['strict'] ?? false);
		$expected = $case->expected;

		if ($strict && ($expected['throws'] ?? null) === 'InvalidArgumentException') {
			WptAssertions::assertThrowsException(
				InvalidArgumentException::class,
				static function () use ($css): void {
					CSSStyleDeclaration::parse($css, true);
				},
				$case->id,
			);
			return;
		}

		$diagnostics = [];
		$decl = CSSStyleDeclaration::parse($css, $strict, $diagnostics);

		if (array_key_exists('cssText', $expected)) {
			self::assertSame((string) $expected['cssText'], $decl->getCssText(), $case->id);
		}
		if (array_key_exists('length', $expected)) {
			self::assertSame((int) $expected['length'], $decl->getLength(), $case->id);
		}
		if (array_key_exists('hasDiagnostics', $expected)) {
			if ($expected['hasDiagnostics']) {
				self::assertNotEmpty($diagnostics, $case->id);
			} else {
				self::assertSame([], $diagnostics, $case->id);
			}
		}
		if (isset($expected['properties']) && is_array($expected['properties'])) {
			foreach ($expected['properties'] as $prop => $val) {
				self::assertSame((string) $val, $decl->getPropertyValue((string) $prop), $case->id . " ($prop)");
			}
		}
	}

	private function runScannerSplit(WptFixtureCase $case): void
	{
		$source = $case->input['source'] ?? null;
		$action = $case->input['action'] ?? null;
		if (!is_string($source) || !is_string($action)) {
			throw new LogicException(sprintf('Case "%s" requires string input.source and input.action.', $case->id));
		}

		$expected = $case->expected;
		match ($action) {
			'splitTopLevel' => self::assertSame(
				$expected['parts'] ?? throw new LogicException('Missing expected.parts.'),
				CSSSyntaxScanner::splitTopLevel($source, (string) ($case->input['delimiter'] ?? ';')),
				$case->id,
			),
			'removeComments' => self::assertSame(
				(string) ($expected['value'] ?? throw new LogicException('Missing expected.value.')),
				CSSSyntaxScanner::removeComments($source),
				$case->id,
			),
			'isBalanced' => self::assertSame(
				(bool) ($expected['balanced'] ?? throw new LogicException('Missing expected.balanced.')),
				CSSSyntaxScanner::isBalanced($source),
				$case->id,
			),
			'findMatchingUrlEnd' => self::assertSame(
				$expected['offset'] ?? null,
				CSSSyntaxScanner::findMatchingUrlEnd($source, (int) ($case->input['openParenOffset'] ?? 3)),
				$case->id,
			),
			default => throw new LogicException(sprintf('Unsupported scanner action "%s".', $action)),
		};
	}

	private function runEof(WptFixtureCase $case): void
	{
		$source = $case->input['source'] ?? null;
		$mode = $case->input['mode'] ?? 'stylesheet';
		if (!is_string($source) || !is_string($mode)) {
			throw new LogicException(sprintf('Case "%s" requires string input.source and input.mode.', $case->id));
		}

		$expected = $case->expected;
		if ($mode === 'unclosedCheck') {
			if (array_key_exists('hasUnclosed', $expected)) {
				self::assertSame((bool) $expected['hasUnclosed'], CSSSyntaxScanner::hasUnclosedCommentOrString($source), $case->id);
			}
			if (array_key_exists('isBalanced', $expected)) {
				self::assertSame((bool) $expected['isBalanced'], CSSSyntaxScanner::isBalanced($source), $case->id);
			}
			return;
		}

		if ($mode === 'stylesheet') {
			$sheet = CSSStyleSheet::parse($source);
			if (array_key_exists('ruleCount', $expected)) {
				self::assertCount((int) $expected['ruleCount'], $sheet->cssRules, $case->id);
			}
			if (array_key_exists('cssText', $expected)) {
				self::assertSame((string) $expected['cssText'], $sheet->getCssText(), $case->id);
			}
			if (array_key_exists('hasErrors', $expected)) {
				if ($expected['hasErrors']) {
					self::assertNotEmpty($sheet->getParseErrors(), $case->id);
				} else {
					self::assertSame([], $sheet->getParseErrors(), $case->id);
				}
			}
			return;
		}

		throw new LogicException(sprintf('Unsupported EOF mode "%s".', $mode));
	}

	private function runPreprocess(WptFixtureCase $case): void
	{
		if (isset($case->input['rawHex']) && is_string($case->input['rawHex'])) {
			$raw = hex2bin($case->input['rawHex']);
			if ($raw === false) {
				throw new LogicException(sprintf('Invalid hex in case "%s".', $case->id));
			}
		} elseif (isset($case->input['raw']) && is_string($case->input['raw'])) {
			$raw = $case->input['raw'];
		} else {
			throw new LogicException(sprintf('Case "%s" requires input.raw or input.rawHex.', $case->id));
		}

		$expected = $case->expected['value'] ?? null;
		if (!is_string($expected)) {
			throw new LogicException(sprintf('Case "%s" requires string expected.value.', $case->id));
		}

		self::assertSame($expected, CSSInputPreprocessor::preprocess($raw), $case->id);
	}

	private function runTokenize(WptFixtureCase $case): void
	{
		$source = $case->input['source'] ?? null;
		if (!is_string($source)) {
			throw new LogicException(sprintf('Case "%s" requires string input.source.', $case->id));
		}
		$expectedTokens = $case->expected['tokens'] ?? null;
		if (!is_array($expectedTokens)) {
			throw new LogicException(sprintf('Case "%s" requires array expected.tokens.', $case->id));
		}

		$actual = CSSTokenizer::tokenize($source);
		self::assertCount(count($expectedTokens), $actual, $case->id);
		foreach ($expectedTokens as $idx => $spec) {
			if (!is_array($spec) || !isset($spec['type'])) {
				throw new LogicException(sprintf('Case "%s" token #%d missing type.', $case->id, $idx));
			}
			$tok = $actual[$idx];
			self::assertSame((string) $spec['type'], $tok->type->value, $case->id . " token #$idx type");
			if (array_key_exists('value', $spec)) {
				self::assertSame((string) $spec['value'], $tok->value, $case->id . " token #$idx value");
			}
			if (array_key_exists('flag', $spec)) {
				self::assertSame($spec['flag'], $tok->flag, $case->id . " token #$idx flag");
			}
			if (array_key_exists('numericValue', $spec)) {
				self::assertSame((float) $spec['numericValue'], $tok->numericValue, $case->id . " token #$idx numericValue");
			}
		}
	}
}
