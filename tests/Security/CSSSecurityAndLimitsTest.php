<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Security;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesMap;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CSSSecurityAndLimitsTest extends TestCase
{
	/** @return iterable<string, array{string}> */
	public static function hostileDeclarationValuesProvider(): iterable
	{
		yield 'top-level semicolon injection' => ['red; background: url(https://evil.example/a)'];
		yield 'top-level closing brace injection' => ['red } body { color: blue'];
		yield 'top-level opening brace injection' => ['red { color: blue }'];
		yield 'unbalanced parenthesis' => ['rgb(1, 2, 3'];
		yield 'unclosed string' => ['"unterminated'];
		yield 'unclosed comment' => ['red /* unclosed'];
		yield 'newline in quoted string' => ["\"line1\nline2\""];
		yield 'null byte' => ["red\x00blue"];
		yield 'important with trailing comment' => ['red !important /* sneak */'];
	}

	#[DataProvider('hostileDeclarationValuesProvider')]
	public function testRejectsHostilePropertyValuesAtomically(string $hostileValue): void
	{
		$style = CSSStyleDeclaration::parse('color: green');
		$map = new StylePropertyMap($style);
		$before = $style->getCssText();

		try {
			$style->setProperty('color', $hostileValue);
			self::fail('Expected hostile setProperty value to be rejected.');
		} catch (InvalidArgumentException) {
			self::assertSame($before, $style->getCssText());
		}

		try {
			$map->set('color', $hostileValue);
			self::fail('Expected hostile StylePropertyMap::set value to be rejected.');
		} catch (InvalidArgumentException) {
			self::assertSame($before, $style->getCssText());
		}
	}

	/** @return iterable<string, array{string}> */
	public static function hostileCustomPropertyNamesProvider(): iterable
	{
		yield 'space in name' => ['--bad name'];
		yield 'colon in name' => ['--bad:name'];
		yield 'comment opener in name' => ['--a/*b'];
		yield 'comment closer in name' => ['--a*/b'];
		yield 'null byte in name' => ["--a\x00b"];
		yield 'quote in name' => ['--a"b'];
		yield 'parens in name' => ['--a()'];
	}

	#[DataProvider('hostileCustomPropertyNamesProvider')]
	public function testRejectsHostileCustomPropertyNames(string $name): void
	{
		$style = CSSStyleDeclaration::parse('color: red');

		try {
			$style->setProperty($name, '1px');
			self::fail('Expected invalid custom property name to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('color: red;', $style->getCssText());
		}
	}

	public function testAllowsBalancedStringsAndFunctionBlocksInValues(): void
	{
		$style = new CSSStyleDeclaration();
		$style->setProperty('content', '"a;b{c}"');
		$style->setProperty('--fn', 'fn({a;b})');

		self::assertSame('"a;b{c}"', $style->getPropertyValue('content'));
		self::assertSame('fn({a;b})', $style->getPropertyValue('--fn'));
	}

	public function testRejectsStructuralInjectionInRuleMetadata(): void
	{
		$sheet = CSSStyleSheet::parse('@media screen { a { color: red } } @keyframes fade { from { opacity: 0 } }');
		/** @var CSSMediaRule $media */
		$media = $sheet->cssRules[0];
		/** @var CSSStyleRule $styleRule */
		$styleRule = $media->cssRules[0];
		/** @var CSSKeyframesRule $keyframes */
		$keyframes = $sheet->cssRules[1];

		foreach ([
			static fn () => $styleRule->setSelectorText('a; b { color: evil }'),
			static fn () => $styleRule->setSelectorText('a } body { color: evil'),
			static fn () => $media->setConditionText('screen { a { color: evil } }'),
			static fn () => $keyframes->setNameText('fade { from { opacity: 1 } }'),
			static fn () => (new CSSPageRule(':first', new CSSStyleDeclaration()))->setSelectorText(':first } * { color: red'),
			static fn () => (new CSSCounterStyleRule('thumbs', new CSSStyleDeclaration()))->setNameText('a; b'),
			static fn () => (new CSSFontFeatureValuesRule('Inter'))->setFontFamily('Inter { }'),
			static fn () => (new CSSFontFeatureValuesMap())->set('bad;name', [1]),
			static fn () => CSSFunctionRule::fromPrelude('--fn(--x <number>: 1; --y: 2)'),
			static fn () => new CSSImageValue('url(a.png); background: url(b.png)'),
			static fn () => new CSSVariableReferenceValue('--x', '1px; color: red'),
		] as $attempt) {
			try {
				$attempt();
				self::fail('Expected structural CSS injection attempt to throw.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}
		}

		self::assertSame('a', $styleRule->selectorText);
		self::assertSame('screen', $media->conditionText);
		self::assertSame('fade', $keyframes->name);
	}

	public function testMediaListRollbackIsTransactionalWhenCallbackFails(): void
	{
		$media = new MediaList('screen', static function (string $text): void {
			if (str_contains($text, 'print')) {
				throw new InvalidArgumentException('Rejected medium.');
			}
		});

		try {
			$media->appendMedium('print');
			self::fail('Expected callback failure to bubble.');
		} catch (InvalidArgumentException) {
			self::assertSame('screen', $media->mediaText);
			self::assertCount(1, $media);
		}
	}

	public function testEnforcesMaxBytesDepthAndRuleLimitsSafely(): void
	{
		$smallLimit = new CSSParserOptions(maxBytes: 20, maxDepth: 2, maxRules: 2, maxMathDepth: 2);

		$exactBytes = CSSStyleSheet::parse('a { color: red; }', new CSSParserOptions(maxBytes: 17));
		self::assertSame([], $exactBytes->getParseErrors());

		$overBytes = CSSStyleSheet::parse('a { color: red; } /* extra */', $smallLimit);
		self::assertCount(0, $overBytes->cssRules);
		self::assertNotEmpty($overBytes->getParseErrors());

		$deepCss = '@media a { @media b { @media c { a { color: red } } } }';
		$deepSheet = CSSStyleSheet::parse($deepCss, new CSSParserOptions(maxDepth: 2));
		self::assertNotEmpty($deepSheet->getParseErrors());

		$manyRules = CSSStyleSheet::parse('a{color:red} b{color:blue} c{color:green}', new CSSParserOptions(maxRules: 2));
		self::assertCount(2, $manyRules->cssRules);
		self::assertNotEmpty($manyRules->getParseErrors());

		$base = CSSStyleSheet::parse('a { color: red }');
		$before = $base->cssText;
		try {
			$base->insertRule('b { color: blue }', 1, new CSSParserOptions(maxBytes: 5));
			self::fail('Expected strict insertRule to reject over-limit rule.');
		} catch (InvalidArgumentException) {
			self::assertSame($before, $base->cssText);
		}

		try {
			$base->replaceSync('b { color: blue }', new CSSParserOptions(maxBytes: 5));
			self::fail('Expected strict replaceSync to reject over-limit stylesheet.');
		} catch (InvalidArgumentException) {
			self::assertSame($before, $base->cssText);
		}

		try {
			CSSNumericValue::parse('min(1px, min(2px, min(3px, 4px)))', null, new CSSParserOptions(maxMathDepth: 2));
			self::fail('Expected excessive math depth to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		$unlimited = CSSStyleSheet::parse($deepCss, CSSParserOptions::unlimited());
		self::assertSame([], $unlimited->getParseErrors());
	}

	public function testRejectsNonFiniteNumbersInvalidContextsAndImpossibleMath(): void
	{
		foreach ([
			static fn () => CSSNumericValue::parse('1e999px'),
			static fn () => new CSSUnitValue(INF, 'px'),
			static fn () => new CSSUnitValue(NAN, 'px'),
			static fn () => CSS::px(1e999),
			static fn () => new CSSContext(0.0),
			static fn () => new CSSContext(16.0, -10.0),
			static fn () => (new CSSContext())->setViewportHeight(NAN),
			static fn () => CSSNumericValue::parse('calc(1px + 1s)'),
			static fn () => CSSNumericValue::parse('min(10px, 2deg)'),
			static fn () => new CSSPerspective('0px'),
			static fn () => new CSSPerspective('-10px'),
			static fn () => new CSSRotate(0, 0, 0, '45deg'),
			static fn () => CSSStyleValue::parse('color', 'red; evil: 1'),
		] as $invalidCall) {
			try {
				$invalidCall();
				self::fail('Expected invalid numeric/context/transform call to throw.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}
		}
	}
}
