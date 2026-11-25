<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Security;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCharsetRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSContainerRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesMap;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMarginRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNestedDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSOpaqueRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRuleList;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSSupportsRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSViewTransitionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\FontFace;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\CSSOM\StyleSheetList;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMapReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHWB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSRGB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
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
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('color: green;', $style->getCssText());
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
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
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
		} catch (CSSParserLimitException) {
			self::assertSame($before, $base->cssText);
		}

		try {
			$base->replaceSync('b { color: blue }', new CSSParserOptions(maxBytes: 5));
			self::fail('Expected strict replaceSync to reject over-limit stylesheet.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('a { color: red; }', $base->getCssText());
		}

		try {
			CSSNumericValue::parse('min(1px, min(2px, min(3px, 4px)))', null, new CSSParserOptions(maxMathDepth: 2));
			self::fail('Expected excessive math depth to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
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
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testPreventsUnquotedUrlBreakoutHtmlStyleTagEscapeAndCycles(): void
	{
		// SEC-H1: escaped \75 rl(...) is recognized as unquoted URL and <bad-url-token> rejects quotes
		self::assertFalse(CSSSyntaxScanner::isBalanced('\75 rl(https://example.test/a?q="quoted")'));
		self::assertTrue(CSSSyntaxScanner::isBalanced('\75 rl(https://example.test/a?b=1)'));
		self::assertFalse(CSSSyntaxScanner::isBalanced('url(a)b)'));

		// SEC-M1: over-depth known at-rule is dropped cleanly, never retained as opaque rule
		$deepKnown = CSSStyleSheet::parse('@media a { @media b { @media c { p { color: red } } } }', new CSSParserOptions(maxDepth: 2));
		self::assertNotEmpty($deepKnown->getParseErrors());
		self::assertInstanceOf(CSSMediaRule::class, $deepKnown->cssRules[0]);
		self::assertInstanceOf(CSSMediaRule::class, $deepKnown->cssRules[0]->cssRules[0]);
		self::assertCount(0, $deepKnown->cssRules[0]->cssRules[0]->cssRules);

		// Opt-in HTML </style> escaping
		$styleSheet = CSSStyleSheet::parse('.x { content: "</style><script>alert(1)</script></STYLE >"; } .y { content: "</stylesheet>"; }');
		$raw = CSSRenderer::render($styleSheet, new CSSRenderOptions(CSSRenderMode::Minified));
		self::assertStringContainsString('</style>', $raw);
		$escaped = CSSRenderer::render($styleSheet, new CSSRenderOptions(CSSRenderMode::Minified, escapeHtmlStyleTag: true));
		self::assertStringNotContainsString('</style>', strtolower($escaped));
		self::assertStringContainsString('<\/style>', $escaped);
		self::assertStringContainsString('<\/STYLE >', $escaped);
		self::assertStringContainsString('</stylesheet>', $escaped);

		// Cycle prevention in grouping rule tree (using detached rules so single-parent check doesn't mask assertNoCycle)
		$detachedOuter = new CSSMediaRule('screen');
		$detachedInner = new CSSMediaRule('all');
		$detachedOuter->getCssRules()->appendDetached($detachedInner);
		$sheet = new CSSStyleSheet();
		$detachedSheet = new CSSStyleSheet();
		$detachedSheet->getCssRules()->append($detachedOuter);
		try {
			$detachedInner->getCssRules()->append($detachedOuter);
			self::fail('Expected cycle insertion to throw InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('already belongs', $e->getMessage());
		}

		$detachedOuter->getCssRules()->remove(0); // detach $detachedInner ($detachedInner->parent = null)
		$cycleRoot = new CSSMediaRule('screen');
		$cycleChild = new CSSMediaRule('all');
		$cycleRoot->getCssRules()->appendDetached($cycleChild);
		try {
			$cycleChild->getCssRules()->appendDetached($cycleRoot);
			self::fail('Expected cycle insertion via appendDetached to throw InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('ancestor', $e->getMessage());
		}
	}

	public function testEnforcesMaxErrorsMaxDeclarationsRenderDepthAndMatrixOverflow(): void
	{
		// maxErrors truncation
		$badCss = str_repeat('color; ', 30);
		$errSheet = CSSStyleSheet::parse('a { ' . $badCss . ' }', new CSSParserOptions(maxErrors: 3));
		self::assertCount(4, $errSheet->getParseErrors());

		// maxDeclarations limit
		$declsSheet = CSSStyleSheet::parse('a { color: red; background: blue; margin: 0; }', new CSSParserOptions(maxDeclarations: 2));
		self::assertInstanceOf(CSSStyleRule::class, $declsSheet->cssRules[0]);
		self::assertCount(2, $declsSheet->cssRules[0]->style);
		self::assertNotEmpty($declsSheet->getParseErrors());

		// Strict replaceSync throws CSSParserLimitException (subclass of InvalidArgumentException)
		$target = CSSStyleSheet::parse('a { color: red }');
		try {
			$target->replaceSync('a { color: red; } b { color: blue; }', new CSSParserOptions(maxRules: 1));
			self::fail('Expected CSSParserLimitException.');
		} catch (CSSParserLimitException) {
			self::assertCount(1, $target->cssRules);
		}

		// CSSRenderer depth limit (>512), constructed iteratively using detached subtree append
		$root = new CSSMediaRule('all');
		$cursor = $root;
		for ($i = 0; $i < 514; $i++) {
			$child = new CSSMediaRule('all');
			$cursor->getCssRules()->appendDetached($child);
			$cursor = $child;
		}
		$prevNesting = ini_get('xdebug.max_nesting_level');
		if ($prevNesting !== false) {
			@ini_set('xdebug.max_nesting_level', '512');
		}
		$sheet = CSSStyleSheet::parse('', CSSParserOptions::unlimited());
		$sheet->getCssRules()->append($root);
		try {
			CSSRenderer::render($root);
			self::fail('Expected CSSParserLimitException for deep render.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		} finally {
			if ($prevNesting !== false) {
				@ini_set('xdebug.max_nesting_level', $prevNesting);
			}
		}

		// StyleSheet metadata control bytes (tested via CSSStyleSheet)
		try {
			new CSSStyleSheet(href: "https://example.test/\x00a.css", title: 'title');
			self::fail('Expected control character rejection in StyleSheet.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// DOMMatrixReadOnly transformPoint finite output check
		$big = new DOMMatrixReadOnly([1e308, 0, 0, 1e308, 0, 0]);
		try {
			$big->transformPoint(['x' => 1e10]);
			self::fail('Expected non-finite transformed point to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
	}

	public function testEnforcesTypedOmMultiArgAndNestedLimitsAndCycleGuards(): void
	{
		$tight = new CSSParserOptions(maxBytes: 32, maxMathDepth: 4, maxDepth: 2, maxRules: 3);

		// StylePropertyMap multi-arg set enforces default maxBytes (16 MiB) and property validation
		$map = new StylePropertyMap(new CSSStyleDeclaration());
		try {
			$map->set('--big', str_repeat('a', 9_000_000), str_repeat('b', 9_000_000));
			self::fail('Expected CSSParserLimitException on multi-arg set exceeding 16 MiB.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			$map->set('color', 'red; background: blue');
			self::fail('Expected InvalidArgumentException on injected declaration value.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Nested calc(min(...)) respects maxMathDepth
		try {
			CSSNumericValue::parse('calc(min(1px, min(2px, min(3px, min(4px, 5px)))))', null, $tight);
			self::fail('Expected CSSParserLimitException on deep nested math functions.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSPositionValue, CSSColorValue, CSSTransformValue propagate CSSParserLimitException
		foreach ([
			static fn () => \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue::fromCssText(str_repeat('10px ', 10), $tight),
			static fn () => \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue::parse('rgb(1, 2, ' . str_repeat(' ', 25) . '3)', null, $tight),
			static fn () => \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue::fromCssText('translate(' . str_repeat('1', 40) . 'px)', $tight),
		] as $limitCall) {
			try {
				$limitCall();
				self::fail('Expected CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// CSSUnparsedValue per-fragment control chars & circular fallback cycle
		try {
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue(["\x00"]);
			self::fail('Expected control char rejection in CSSUnparsedValue.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$ref = new CSSVariableReferenceValue('--self', 'a');
		$fallback = $ref->fallback;
		try {
			$fallback?->offsetSet(0, $ref);
			self::fail('Expected cycle rejection in CSSUnparsedValue.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSKeyframesRule respects maxRules and maxDepth
		try {
			$kf = new CSSKeyframesRule('fade');
			$kfSheet = new CSSStyleSheet();
			$kfSheet->getCssRules()->append($kf);
			$kf->appendRule('from { opacity: 0; }');
			$kf->appendRule('50% { opacity: 0.5; }');
			$kf->appendRule('to { opacity: 1; }', $tight);
			self::fail('Expected CSSParserLimitException when exceeding maxRules on @keyframes.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Skew setIs2D(false) & Perspective setIs2D(true) & non-finite matrix rejections
		try {
			(new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX(CSS::deg(45)))->setIs2D(false);
			self::fail('Expected CSSSkewX setIs2D(false) to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			(new CSSPerspective(200))->setIs2D(true);
			self::fail('Expected CSSPerspective setIs2D(true) to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			(new CSSPerspective(1e-310))->toMatrix();
			self::fail('Expected subnormal perspective overflow to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$skew90Matrix = (new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX(CSS::deg(90)))->toMatrix();
		self::assertTrue(is_finite($skew90Matrix[4])); // tan(deg2rad(90)) is large finite float (~1.63e16)
		try {
			(new CSSRotate(CSS::turn(1e308)))->toMatrix();
			self::fail('Expected angle multiplication overflow in scalar() to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$this->assertSecurityAndLimitRegressions();
	}

	private function assertSecurityAndLimitRegressions(): void
	{
		// 1. Nested style rules propagate CSSParserLimitException atomically in replaceSync & insertRule
		$atomicTarget = CSSStyleSheet::parse('a { color: red; }');
		$atomicBefore = $atomicTarget->cssText;
		try {
			$atomicTarget->replaceSync('a { b { c { color: red; } } }', new CSSParserOptions(maxDepth: 2));
			self::fail('Expected CSSParserLimitException for nested rule maxDepth in replaceSync.');
		} catch (CSSParserLimitException) {
			self::assertSame($atomicBefore, $atomicTarget->cssText);
		}
		try {
			$atomicTarget->insertRule('a { b { color: red; background: blue; } }', 0, new CSSParserOptions(maxDeclarations: 1));
			self::fail('Expected CSSParserLimitException for nested rule maxDeclarations in insertRule.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('a { color: red; }', $atomicTarget->getCssText());
		}

		// 2. Escaped URL differential (\ url(...) and 40-byte hex-escaped ident) rejected cleanly
		foreach ([
			'\ url(/*) "*/); } .injected { color: red } a { x: ("',
			"\\000061\\r\\n\\000075\\r\\n\\000072\\r\\n\\00006c\\r\\n(/*) \"*/); } .injected { color: red } a { x: (\"",
		] as $payload) {
			try {
				CSSSyntaxScanner::assertValidFragment($payload, 'declaration value', [';', '{', '}']);
				self::fail('Expected structural injection payload to be rejected.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 3. ReDoS-resistant custom property / keyword / counter-style / container identifier regexes
		$redosInput = '--' . str_repeat('\\aaaaaa', 12) . '!';
		foreach ([
			static fn () => (new CSSStyleDeclaration())->setProperty($redosInput, '1px'),
			static fn () => new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue($redosInput),
			static fn () => new CSSCounterStyleRule($redosInput, new CSSStyleDeclaration()),
		] as $call) {
			try {
				$call();
				self::fail('Expected invalid ReDoS-shaped ident to be rejected quickly.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
		self::assertSame('', (new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSContainerRule($redosInput . ' (width > 1px)'))->getContainerName());

		// 4. CSSUnparsedValue string constructor, offsetUnset, quote-context, cloning, and O(N) parseFragments
		foreach ([
			static fn () => \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText("a; } body { color: red\x00"),
			static fn () => new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue(['"', new CSSVariableReferenceValue('--x', 'safe'), '"']),
			static fn () => new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue(['url(', new CSSVariableReferenceValue('--x'), ')']),
		] as $badUnparsed) {
			try {
				$badUnparsed();
				self::fail('Expected CSSUnparsedValue invariant failure.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$unsetCheck = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue(['"', '; } body { color: red', '"']);
		try {
			unset($unsetCheck[0]);
			self::fail('Expected offsetUnset exposing unbalanced/top-level delimiters to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('"; } body { color: red"', (string) $unsetCheck);
		}

		// Shared DAG protection via clone-on-insert
		$sharedChild = new CSSVariableReferenceValue('--child', 'x');
		$dagParent = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue([$sharedChild, ' ', $sharedChild]);
		self::assertNotSame($dagParent[0], $dagParent[2]);

		// Deep var() nesting maxDepth & maxBytes on CSSUnparsedValue / CSSVariableReferenceValue
		try {
			\Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText('var(--a, var(--b, var(--c, 1px)))', new CSSParserOptions(maxDepth: 1));
			self::fail('Expected CSSParserLimitException for var() depth.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			new CSSVariableReferenceValue('--a', 'var(--b, var(--c, 1px))', new CSSParserOptions(maxDepth: 1));
			self::fail('Expected CSSParserLimitException for CSSVariableReferenceValue depth.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 5. Iterative deep attach / reparent (>512 deep without recursion overflow) and getCssText() depth limit
		$deepRoot = new CSSMediaRule('all');
		$cursor = $deepRoot;
		for ($i = 0; $i < 600; $i++) {
			$child = new CSSMediaRule('all');
			$cursor->getCssRules()->appendDetached($child);
			$cursor = $child;
		}
		$deepSheet = CSSStyleSheet::parse('', CSSParserOptions::unlimited());
		$prevNesting = ini_get('xdebug.max_nesting_level');
		if ($prevNesting !== false) {
			@ini_set('xdebug.max_nesting_level', '512');
		}
		try {
			$deepSheet->getCssRules()->append($deepRoot);
			self::assertSame(601, $deepSheet->getTotalRuleCount());
		} finally {
			if ($prevNesting !== false) {
				@ini_set('xdebug.max_nesting_level', $prevNesting);
			}
		}
		try {
			$deepRoot->getCssText();
			self::fail('Expected CSSParserLimitException on deep getCssText().');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			\Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer::render($deepSheet);
			self::fail('Expected CSSParserLimitException on deep CSSRenderer::render().');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 6. Leading/trailing NUL & VT control bytes cannot be stripped by trim() before validation
		$nulSheet = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse("\x00@media screen { a { color: red; } }");
		self::assertNotEmpty($nulSheet->getParseErrors());
		self::assertFalse(CSS::supports("\x00(color: red)\x00"));
		self::assertFalse(CSS::supports('color', "\x0Bred\x0B"));

		// Comment-only declaration slice containing NUL throws cleanly, and standalone maxErrors caps strictly
		try {
			CSSStyleDeclaration::parse("/* \x00 */; color: red;", true);
			self::fail('Expected control byte in leading comment slice to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$standaloneDiags = [];
		CSSStyleDeclaration::parse('a; b; c; d; e;', false, $standaloneDiags, new CSSParserOptions(maxErrors: 2));
		self::assertCount(2, $standaloneDiags);

		// 7. Unknown block at-rule & @font-feature-values maxDepth, CSSPageRule::insertRule(), CSSCalcParser limits, CSSContext overflow
		$unknownDeep = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse('@media screen { @unknown { color: red; } }', new CSSParserOptions(maxDepth: 1));
		self::assertNotEmpty($unknownDeep->getParseErrors());

		$ffvDeep = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse('@media screen { @font-feature-values Foo { @styleset { a: 1; } } }', new CSSParserOptions(maxDepth: 1));
		self::assertNotEmpty($ffvDeep->getParseErrors());

		$pageSheet = CSSStyleSheet::parse('@page { size: A4; }');
		/** @var CSSPageRule $pageRule */
		$pageRule = $pageSheet->cssRules[0];
		$pageRule->insertRule('@top-left { content: "Header"; }', 0);
		self::assertCount(1, $pageRule->cssRules);
		try {
			$pageRule->insertRule('.bad { color: red; }', 0);
			self::fail('Expected non-margin rule in @page::insertRule to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		foreach ([
			static fn () => \Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser::parse('calc((((10px))))', new CSSParserOptions(maxMathDepth: 2)),
			static fn () => \Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser::parse('calc(-----10px)', new CSSParserOptions(maxMathDepth: 3)),
			static fn () => CSSNumericValue::parse('min(' . implode(',', array_fill(0, 1025, '1px')) . ')', null, new CSSParserOptions(maxMathDepth: 4)),
		] as $mathLimitCall) {
			try {
				$mathLimitCall();
				self::fail('Expected math limit CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// StylePropertyMap append maxBytes
		$appendMap = new StylePropertyMap(new CSSStyleDeclaration());
		$appendMap->set('--a', str_repeat('x', 9_000_000));
		try {
			$appendMap->append('--a', str_repeat('y', 9_000_000));
			self::fail('Expected CSSParserLimitException on StylePropertyMap::append exceeding 16 MiB.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSContext extreme fontSize overflow & underflow guards
		foreach ([
			[PHP_FLOAT_MAX, 'lh'],
			[1e-320, 'ex'],
		] as [$extremeFont, $unit]) {
			try {
				(new CSSContext($extremeFont))->getToPxFactor($unit);
				self::fail('Expected extreme fontSize overflow/underflow in CSSContext::getToPxFactor().');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// DAG amplification bomb in math AST is bounded linearly by MAX_MATH_NODES
		$dagA = CSSNumericValue::parse('1px');
		$dagB = CSSNumericValue::parse('1px');
		for ($i = 0; $i < 20; $i++) {
			$dagA = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([$dagA, $dagA]);
			$dagB = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([$dagB, $dagB]);
		}
		// Memoized O(V) type() and identity fast-path equals($dagA) succeed in linear/constant time
		self::assertSame('length', $dagA->getNumericCategory());
		self::assertTrue($dagA->equals($dagA));
		foreach ([
			static fn () => (string) $dagA,
			static fn () => clone $dagA,
			static fn () => $dagA->equals($dagB),
		] as $dagOp) {
			try {
				$dagOp();
				self::fail('Expected DAG math bomb to throw CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// CSSUnparsedValue handles var(/* c */ --foo /* c2 */, fallback) and large O(N) nested parens
		$unparsedCommentVar = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText('var(/* c */ --foo /* c2 */, red)');
		self::assertCount(1, $unparsedCommentVar);
		self::assertSame('--foo', $unparsedCommentVar[0]->variable);
		$deepParens = str_repeat('(', 5000) . '1' . str_repeat(')', 5000);
		$unparsedLinear = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText($deepParens, CSSParserOptions::unlimited());
		self::assertSame($deepParens, (string) $unparsedLinear);

		// Batch 1 & 2 security and correctness regressions
		$this->assertBatch1And2SecurityRegressions();
	}

	private function assertBatch1And2SecurityRegressions(): void
	{
		// 1. Control bytes inside comments: strict rules throw, stylesheet drops rule & records parse error
		foreach ([
			static fn () => \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser::parseSingleRule("a { /* \x00 */ color: red; }"),
			static fn () => \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser::parseSingleMarginRule("@top-left { /* \x01 */ content: 'x'; }"),
		] as $strictCommentCall) {
			try {
				$strictCommentCall();
				self::fail('Expected control byte inside comment to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString('control', strtolower($e->getMessage()));
			}
		}

		$ctrlSheet = CSSStyleSheet::parse("a /* \x00 */ { color: red; } b { color: blue; }", new CSSParserOptions());
		self::assertNotEmpty($ctrlSheet->getParseErrors());
		self::assertCount(1, $ctrlSheet->cssRules);
		self::assertInstanceOf(CSSStyleRule::class, $ctrlSheet->cssRules[0]);
		self::assertSame('b', $ctrlSheet->cssRules[0]->selectorText);

		$ctrlLeadingCommentSheet = CSSStyleSheet::parse("a { /* \x00 */ color: red; }");
		self::assertNotEmpty($ctrlLeadingCommentSheet->getParseErrors());
		self::assertCount(1, $ctrlLeadingCommentSheet->cssRules);
		self::assertInstanceOf(CSSStyleRule::class, $ctrlLeadingCommentSheet->cssRules[0]);
		self::assertSame(1, $ctrlLeadingCommentSheet->cssRules[0]->style->length);

		$ctrlDeclSheet = CSSStyleSheet::parse("a { color: /* \x00 */ red; }");
		self::assertNotEmpty($ctrlDeclSheet->getParseErrors());
		self::assertCount(1, $ctrlDeclSheet->cssRules);
		self::assertInstanceOf(CSSStyleRule::class, $ctrlDeclSheet->cssRules[0]);
		self::assertSame(0, $ctrlDeclSheet->cssRules[0]->style->length);

		// @font-feature-values respects maxDeclarations limit and decodes escaped feature value names
		$ffvLimited = CSSStyleSheet::parse(
			'@font-feature-values Inter { @styleset { a: 1; b: 2; c: 3; } }',
			new CSSParserOptions(maxDeclarations: 1),
		);
		self::assertNotEmpty($ffvLimited->getParseErrors());
		$ffvEscaped = CSSStyleSheet::parse('@font-feature-values Inter { @styleset { \\61 lt: 1 2; } }');
		self::assertSame([], $ffvEscaped->getParseErrors());
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $ffvEscaped->cssRules[0]);
		self::assertSame([1, 2], $ffvEscaped->cssRules[0]->styleset->get('\\61 lt'));

		// 2. CSSUnparsedValue bracket tracking, hex+ws escape before var(, and maxBytes inheritance
		$bracketUnparsed = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText('var(--a, [a;b] var(--b))');
		self::assertCount(1, $bracketUnparsed);
		self::assertSame('--a', $bracketUnparsed[0]->variable);
		self::assertSame('[a;b] var(--b)', (string) $bracketUnparsed[0]->fallback);

		$escapedIdentVar = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText('\66 var(--a)');
		self::assertCount(1, $escapedIdentVar);
		self::assertIsString($escapedIdentVar[0]);

		$tightVar = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue(
			'--x',
			'red',
			new CSSParserOptions(maxBytes: 16),
		);
		try {
			$tightVar->variable = '--' . str_repeat('a', 32);
			self::fail('Expected oversized $variable assignment to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('maximum size', strtolower($e->getMessage()));
		}
		try {
			$tightVar->setFallback(str_repeat('a', 32));
			self::fail('Expected oversized setFallback() to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('maximum size', strtolower($e->getMessage()));
		}

		// Unlimited CSSUnparsedValue + unlimited CSSVariableReferenceValue avoids default 16 MiB cap in __toString()
		$unlim = CSSParserOptions::unlimited();
		$unlimRef = new CSSVariableReferenceValue('--x', 'abc', $unlim);
		self::assertSame('var(--x, abc)', (string) $unlimRef);

		// 3. CSS::supports duplicate !important & multi-line registerProperty syntax collapse
		self::assertFalse(CSS::supports('(color: red !important !important)'));
		CSS::clearRegisteredProperties();
		try {
			$reg = CSS::registerProperty([
				'name' => '--multi',
				'syntax' => "<length>\n|\t<percentage>",
				'inherits' => false,
				'initialValue' => '0px',
			]);
			self::assertSame('<length> | <percentage>', $reg->getSyntax());
		} finally {
			CSS::clearRegisteredProperties();
		}

		// 4. Keyword clone isolation across CSSPerspective, CSSPositionValue, and CSSColorValue subclasses
		$persp = new CSSPerspective('none');
		$len = $persp->length;
		self::assertInstanceOf(CSSKeywordValue::class, $len);
		$len->value = 'evil';
		$perspLen = $persp->getLength();
		self::assertInstanceOf(CSSKeywordValue::class, $perspLen);
		self::assertSame('none', $perspLen->getValue());
		$perspFirst = $persp->getValues()[0];
		self::assertInstanceOf(CSSKeywordValue::class, $perspFirst);
		$perspFirst->value = 'evil';
		self::assertSame('none', $perspLen->getValue());

		$pos = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue(
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue('left'),
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue('top'),
		);
		$px = $pos->x;
		self::assertInstanceOf(CSSKeywordValue::class, $px);
		$px->value = 'top';
		$posX = $pos->getX();
		self::assertInstanceOf(CSSKeywordValue::class, $posX);
		self::assertSame('left', $posX->getValue());

		try {
			$customHintType = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType::fromDimensions(['length' => 1, 'percentHint' => 'angle']);
			$synth = new class ($customHintType) extends CSSNumericValue {
				public function __construct(private readonly \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType $t)
				{
					$this->cachedNumericType = $t;
				}
				public function __toString(): string { return '10px'; }
				public function clone(): static { return clone $this; }
			};
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue($synth, CSS::px(0));
			self::fail('Expected invalid percentHint on CSSPositionValue length to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('length or percentage', strtolower($e->getMessage()));
		}

		$rgb = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue::parse('rgb(none 10% 20% / none)');
		self::assertInstanceOf(CSSRGB::class, $rgb);
		$rKw = $rgb->r;
		self::assertInstanceOf(CSSKeywordValue::class, $rKw);
		$rKw->value = 'evil';
		$rgbR = $rgb->getR();
		self::assertInstanceOf(CSSKeywordValue::class, $rgbR);
		self::assertSame('none', $rgbR->getValue());
		$aKw = $rgb->alpha;
		self::assertInstanceOf(CSSKeywordValue::class, $aKw);
		$aKw->value = 'evil';
		$rgbA = $rgb->getAlpha();
		self::assertInstanceOf(CSSKeywordValue::class, $rgbA);
		self::assertSame('none', $rgbA->getValue());

		$customColor = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue::parse('color(srgb none 0.5 0.5)');
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor::class, $customColor);
		$chans = $customColor->channels;
		self::assertInstanceOf(CSSKeywordValue::class, $chans[0]);
		$chans[0]->value = 'evil';
		$chan0 = $customColor->getChannels()[0];
		self::assertInstanceOf(CSSKeywordValue::class, $chan0);
		self::assertSame('none', $chan0->getValue());

		// 5. CSSTranslate bool $z sets is2D properly; DOMMatrixReadOnly & CSSMatrixComponent reject \x0B
		$tBool = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate(10, 20, false);
		self::assertFalse($tBool->is2D());
		self::assertSame('translate3d(10px, 20px, 0px)', (string) $tBool);

		foreach ([
			static fn () => new DOMMatrixReadOnly(["\x0B1", 0, 0, 1, 0, 0]),
			static fn () => new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent(["\x0B1", 0, 0, 1, 0, 0]),
			static fn () => CSS::px("\x0B10"),
		] as $vtabCall) {
			try {
				$vtabCall();
				self::fail('Expected vertical tab control byte to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 6. Depth-256 clone $sheet stays iterative without excessive stack nesting
		$deepCss = str_repeat('@media screen { ', 255) . 'a { color: red; }' . str_repeat(' }', 255);
		$deepSheet = CSSStyleSheet::parse($deepCss);
		self::assertSame([], $deepSheet->getParseErrors());
		$clonedDeep = clone $deepSheet;
		self::assertSame(256, $clonedDeep->getTotalRuleCount());
	}

	public function testEnforcesPlanV7Batch1And2CssomParserHardening(): void
	{
		// 1. DAG / cycle rejection on CSSGroupingRule serialization & CSSStyleSheet inspection via reflection
		$sheet = CSSStyleSheet::parse('@media screen { @supports (display: grid) { a { color: red; } } }');
		$outer = $sheet->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $outer);
		$inner = $outer->cssRules[0];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule::class, $inner);
		$listProp = new \ReflectionProperty($inner->cssRules, 'rules');
		$rawRules = $listProp->getValue($inner->cssRules);
		$rawRules[] = $outer; // Create cycle in underlying tree
		$listProp->setValue($inner->cssRules, $rawRules);
		try {
			CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
			self::fail('Expected cyclic CSSRule graph to be rejected during rendering.');
		} catch (\LogicException $e) {
			self::assertStringContainsString('cyclic', strtolower($e->getMessage()));
		}

		// Diamond DAG sharing same rule in two sibling branches via reflection fails cleanly
		$dagSheet = new CSSStyleSheet();
		$sharedLeaf = new CSSStyleRule('a');
		$branchA = new CSSMediaRule('screen');
		$branchB = new CSSMediaRule('print');
		$dagSheet->cssRules->append($branchA);
		$branchA->cssRules->append($sharedLeaf);
		$listPropB = new \ReflectionProperty($branchB->cssRules, 'rules');
		$listPropB->setValue($branchB->cssRules, [$sharedLeaf, $sharedLeaf]);
		try {
			$dagSheet->cssRules->append($branchB);
			self::fail('Expected diamond DAG rule graph to be rejected.');
		} catch (\LogicException $e) {
			self::assertStringContainsString('dag', strtolower($e->getMessage()));
		}

		// Diamond DAG across distinct branches is also caught by CSSRenderer
		$listPropB->setValue($branchB->cssRules, [$sharedLeaf]);
		$sheetListProp = new \ReflectionProperty($dagSheet->cssRules, 'rules');
		$sheetListProp->setValue($dagSheet->cssRules, [$branchA, $branchB]);
		try {
			CSSRenderer::render($dagSheet, new CSSRenderOptions(CSSRenderMode::Minified));
			self::fail('Expected diamond DAG rule graph to be rejected during rendering.');
		} catch (\LogicException $e) {
			self::assertStringContainsString('dag', strtolower($e->getMessage()));
		}

		// 2. CSSStyleDeclaration DEFAULT_MAX_BYTES & duplicate !important rejection
		unset($sheet, $dagSheet, $outer, $inner, $branchA, $branchB, $sharedLeaf, $e);
		$oversized = str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES + 1);
		$decl = new CSSStyleDeclaration();
		try {
			$decl->setProperty('color', $oversized);
			self::fail('Expected oversized property value to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			unset($e);
		}

		foreach ([
			static fn () => (new CSSStyleDeclaration())->setProperty('color', 'red', 'important !important'),
			static fn () => (new CSSStyleDeclaration())->setProperty('color', 'red !important !important'),
			static fn () => (new CSSStyleDeclaration())->setCssText('color: red !important !important;'),
		] as $dupImportant) {
			try {
				$dupImportant();
				self::fail('Expected duplicate !important to be rejected.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		foreach ([
			'a { color: red !important !important; }',
			'a { color: red !important /* c */ !important; }',
		] as $dupImportCss) {
			$dupSheet = CSSStyleSheet::parse($dupImportCss);
			self::assertNotEmpty($dupSheet->getParseErrors());
			self::assertInstanceOf(CSSStyleRule::class, $dupSheet->cssRules[0]);
			self::assertSame('', $dupSheet->cssRules[0]->style->getPropertyValue('color'));
		}

		self::assertFalse(CSS::supports('color: red !important !important'));
		self::assertFalse(CSS::supports('color: red !\\69 mportant !\\69 mportant'));

		// 3. MediaList size bounds and O(1) deduplication
		$ml = new MediaList();
		try {
			$ml->setMediaText($oversized);
			self::fail('Expected oversized MediaList::setMediaText() to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			unset($e);
		}
		$ml->setMediaText('screen, print, screen');
		self::assertSame('screen, print', $ml->mediaText);
		$ml->deleteMedium('print');
		self::assertSame('screen', $ml->mediaText);
		try {
			$ml->appendMedium($oversized);
			self::fail('Expected oversized MediaList::appendMedium() to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			unset($e);
		}
		unset($oversized);

		// 4. CSS escape smuggling in at-rules & leading function names
		$smuggledSheet = CSSStyleSheet::parse('@\\69 mport "a.css"; @m\\65 dia screen { a { color: red; } }');
		self::assertInstanceOf(CSSImportRule::class, $smuggledSheet->cssRules[0]);
		self::assertSame('a.css', $smuggledSheet->cssRules[0]->href);
		self::assertInstanceOf(CSSMediaRule::class, $smuggledSheet->cssRules[1]);

		$escapedUrl = new CSSImageValue('u\\72 l(https://example.com/a.png)');
		self::assertSame('u\\72 l(https://example.com/a.png)', $escapedUrl->getImage());
		$escapedGrad = new CSSImageValue('l\\69 near-gradient(red, blue)');
		self::assertSame('l\\69 near-gradient(red, blue)', $escapedGrad->getImage());
		$escapedColor = CSSColorValue::parse('r\\67 b(10, 20, 30)');
		self::assertInstanceOf(CSSRGB::class, $escapedColor);

		// CSSImageValue DEFAULT_MAX_BYTES cap
		$oversizedImage = 'url(' . str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES) . ')';
		try {
			new CSSImageValue($oversizedImage);
			self::fail('Expected oversized CSSImageValue to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			unset($e);
		}
		unset($oversizedImage);

		// 5. CSSPropertyRule clears empty initial-value flag on removeProperty() / clear()
		$propSheet = CSSStyleSheet::parse('@property --p1 { syntax: "*"; inherits: false; initial-value: ; } @property --p2 { syntax: "*"; inherits: false; initial-value: ; }');
		$p1 = $propSheet->cssRules[0];
		$p2 = $propSheet->cssRules[1];
		self::assertInstanceOf(CSSPropertyRule::class, $p1);
		self::assertInstanceOf(CSSPropertyRule::class, $p2);
		self::assertSame('', $p1->initialValue);
		self::assertStringContainsString('initial-value: ;', CSSRenderer::render($propSheet, new CSSRenderOptions(CSSRenderMode::Minified)));
		$p1->style->removeProperty('initial-value');
		self::assertNull($p1->initialValue);
		$p2->style->clear();
		self::assertNull($p2->initialValue);

		// 6. CSSFontFeatureValuesRule categories, CSSFontFeatureValuesMap validation, & O(N) large block
		$ffSheet = CSSStyleSheet::parse('@font-feature-values Example { @styleset { a: 1; } }');
		$ffRule = $ffSheet->cssRules[0];
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $ffRule);
		self::assertSame([1], $ffRule->getFeatureMap('styleset')->get('a'));
		try {
			$ffRule->getFeatureMap('invalid-category');
			self::fail('Expected unknown font-feature category to throw InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('Invalid font feature', $e->getMessage());
		}
		foreach ([
			static fn () => (new CSSFontFeatureValuesMap())->set('multi word', 1),
			static fn () => (new CSSFontFeatureValuesMap())->set('neg', -1),
			static fn () => (new CSSFontFeatureValuesMap())->set("bad\x00", 1),
		] as $badMapCall) {
			try {
				$badMapCall();
				self::fail('Expected invalid font feature map entry to fail.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Control byte inside @font-feature-values body records error cleanly without TypeError
		$badFfSheet = CSSStyleSheet::parse("@font-feature-values Foo { @styleset { \x00a: 1; } }");
		self::assertNotEmpty($badFfSheet->getParseErrors());

		// 500 entries parse cleanly in linear time
		$ffEntries = implode(' ', array_map(static fn (int $i): string => "f{$i}: {$i};", range(1, 500)));
		$bigFfSheet = CSSStyleSheet::parse("@font-feature-values BigFont { @styleset { {$ffEntries} } }");
		self::assertSame([], $bigFfSheet->getParseErrors());
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $bigFfSheet->cssRules[0]);
		self::assertSame(500, $bigFfSheet->cssRules[0]->styleset->count());

		// 7. CSSLayerStatementRule rejects empty prelude & empty segments
		foreach (['@layer ;', '@layer a, , b;'] as $badLayerCss) {
			$badLayerSheet = CSSStyleSheet::parse($badLayerCss);
			self::assertNotEmpty($badLayerSheet->getParseErrors(), 'Expected parse error for: ' . $badLayerCss);
			self::assertCount(0, $badLayerSheet->cssRules);
		}
		foreach ([
			static fn () => new CSSLayerStatementRule(''),
			static fn () => new CSSLayerStatementRule('a, , b'),
		] as $badLayer) {
			try {
				$badLayer();
				self::fail('Expected malformed @layer statement to fail.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 8. CSSSyntaxScanner bare hex + comment, CSSUnparsedValue #/@ before var(, CSSCalcParser edges & depth-256 min()
		self::assertSame('foo\\61  bar', CSSSyntaxScanner::removeComments('foo\\61/*c*/bar'));

		$hashUnparsed = CSSUnparsedValue::fromCssText('#var(--x) @var(--y)');
		self::assertCount(1, $hashUnparsed);
		self::assertSame('#var(--x) @var(--y)', $hashUnparsed[0]);

		// Unparsed nested var() respects $options->maxDepth
		try {
			CSSUnparsedValue::fromCssText(
				'var(--a, var(--b, var(--c, var(--d, 1px))))',
				new CSSParserOptions(maxDepth: 3),
			);
			self::fail('Expected nested var() exceeding maxDepth to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('depth', strtolower($e->getMessage()));
		}

		self::assertSame('calc(10px + 20px)', (string) CSSCalcParser::parse('calc(10px +/*c*/ 20px)'));
		foreach (['calc(--10px)', 'calc(10px + - 5px)', 'calc(+)'] as $badCalc) {
			try {
				CSSCalcParser::parse($badCalc);
				self::fail('Expected malformed calc() to throw InvalidArgumentException: ' . $badCalc);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Depth-256 nested min() with comments parses iteratively within limit
		$deepMin = str_repeat('min(/*c*/ ', 255) . '1px' . str_repeat(', 2px)', 255);
		self::assertNotSame('', (string) CSSNumericValue::parse($deepMin));
	}

	public function testEnforcesPlanV7Batch3TypedOmAndGeometryHardening(): void
	{
		// 1. DOMMatrixReadOnly / DOMMatrix control characters across entry points
		$zeros15 = array_fill(0, 15, 0.0);
		foreach ([
			static fn () => (new DOMMatrix())->setMatrixValue("translateX(\x0B1px)"),
			/** @phpstan-ignore argument.type */
			static fn () => DOMMatrixReadOnly::fromFloat32Array(["1\x00", ...$zeros15]),
			/** @phpstan-ignore argument.type */
			static fn () => DOMMatrixReadOnly::fromFloat64Array(["1\x00", ...$zeros15]),
			static fn () => DOMMatrixReadOnly::fromMatrix(['m11' => "1\x00"]),
			/** @phpstan-ignore argument.type */
			static fn () => (new DOMMatrixReadOnly())->transformPoint(['x' => "1\x00"]),
		] as $badMatrixCall) {
			try {
				$badMatrixCall();
				self::fail('Expected control character in matrix API to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString('control character', strtolower($e->getMessage()));
			}
		}

		// 2. Transform live sync, CSSTranslate z vs 2D/3D, CSSRotate 3-arg, CSSMatrixComponent independent is2D
		$translate3d = new CSSTranslate(CSS::px(1), CSS::px(2), CSS::px(3));
		self::assertFalse($translate3d->is2D());
		self::assertSame('translate3d(1px, 2px, 3px)', (string) $translate3d);

		$scale = new CSSScale(2, 3);
		$scale->z = 4;
		self::assertFalse($scale->is2D());
		self::assertSame('scale3d(2, 3, 4)', (string) $scale);

		$matrixComp = new CSSMatrixComponent(new DOMMatrixReadOnly([1, 0, 0, 1, 10, 20]));
		self::assertTrue($matrixComp->is2D());
		$matrixComp->is2D = false;
		self::assertFalse($matrixComp->is2D());
		self::assertTrue($matrixComp->getMatrix()->is2D());
		self::assertStringStartsWith('matrix3d(', (string) $matrixComp);

		try {
			new CSSRotate(1, 2, 3);
			self::fail('Expected 3-argument CSSRotate to throw InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 3. CSSTransformValue::MAX_COMPONENTS & CSSStyleValue::MAX_LIST_ITEMS
		try {
			new CSSTransformValue(array_fill(0, CSSTransformValue::MAX_COMPONENTS + 1, new CSSSkewX(CSS::deg(5))));
			self::fail('Expected transform component overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		try {
			CSSStyleValue::parseAll('animation-name', implode(', ', array_fill(0, CSSStyleValue::MAX_LIST_ITEMS + 1, 'fade')));
			self::fail('Expected CSSStyleValue list overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 4. CSSColor::MAX_CUSTOM_CHANNELS, CSSLCH/CSSOKLCH 'none', and CSSRGB::toHSL() achromatic
		try {
			new CSSColor('--custom', array_fill(0, CSSColor::MAX_CUSTOM_CHANNELS + 1, 0.5));
			self::fail('Expected excessive custom color channels to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$lchNone = new CSSLCH(new CSSKeywordValue('none'), 50, 120);
		$labNone = $lchNone->toLab();
		self::assertInstanceOf(CSSKeywordValue::class, $labNone->getL());
		self::assertSame('none', $labNone->getL()->getKeyword());

		$oklchNone = new CSSOKLCH(new CSSKeywordValue('none'), 0.2, 120);
		$oklabNone = $oklchNone->toOKLab();
		self::assertInstanceOf(CSSKeywordValue::class, $oklabNone->getL());
		self::assertSame('none', $oklabNone->getL()->getKeyword());

		$hslAchromatic = (new CSSRGB(CSS::number(0.5), CSS::number(0.5), CSS::number(0.5)))->toHSL();
		self::assertSame(0.0, $hslAchromatic->getS()->value);

		try {
			(new CSSLCH(CSS::percent(50)->add(CSS::em(1)), 50, 120))->toLab();
			self::fail('Expected unresolved math lightness in toLab() to throw InvalidArgumentException.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 5. StylePropertyMap array_values normalization & bare hex escape join spacing
		$decl = new CSSStyleDeclaration();
		$map = new StylePropertyMap($decl);
		$map->set('margin', ['first' => CSS::px(1), 'second' => CSS::px(2)]);
		self::assertSame('1px 2px', $decl->getPropertyValue('margin'));

		$map->set('animation-name', new CSSKeywordValue('foo\\61'), new CSSKeywordValue('bar'));
		self::assertSame('foo\\61  bar', $decl->getPropertyValue('animation-name'));

		// 6. CSS::registerProperty() clone isolation
		CSS::clearRegisteredProperties();
		try {
			$inputRule = CSSStyleSheet::parse('@property --isolated-gap { syntax: "<length>"; inherits: false; initial-value: 4px; }')->cssRules[0];
			self::assertInstanceOf(CSSPropertyRule::class, $inputRule);
			$stored = CSS::registerProperty($inputRule);
			$inputRule->style->setProperty('initial-value', '99px');
			$stored->style->setProperty('initial-value', '88px');
			$fetched = CSS::getRegisteredProperty('--isolated-gap');
			self::assertNotNull($fetched);
			self::assertSame('4px', $fetched->initialValue);
		} finally {
			CSS::clearRegisteredProperties();
		}
	}

	public function testEnforcesPlanV8RegressionHardening(): void
	{
		// 1. CSS::registerProperty() propagates CSSParserLimitException & CSS::supports() decodes keyword escapes
		CSS::clearRegisteredProperties();
		try {
			try {
				CSS::registerProperty([
					'name' => '--too-big',
					'syntax' => '*',
					'inherits' => false,
					'initialValue' => str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES + 1),
				]);
				self::fail('Expected oversized CSS::registerProperty() to throw CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		} finally {
			CSS::clearRegisteredProperties();
		}
		self::assertTrue(CSS::supports('n\\6ft (not-a-prop: 1)'));
		self::assertTrue(CSS::supports('(display: block) a\\6e d (color: red)'));
		self::assertTrue(CSS::supports('(display: block) a\\00006ed (color: red)'));
		self::assertTrue(CSS::supports('(not-a-prop: 1) \\6fr (display: grid)'));
		self::assertTrue(CSS::supports('s\\65lector(:has(a))'));

		// 2. CSSLCH & CSSOKLCH throw InvalidArgumentException (not missing-class Error) on math lightness
		foreach ([
			static fn () => (new CSSLCH(CSS::percent(50)->add(CSS::em(1)), 50, 120))->toLab(),
			static fn () => (new CSSOKLCH(CSS::percent(50)->add(CSS::em(1)), 0.2, 120))->toOKLab(),
		] as $mathLightnessCall) {
			try {
				$mathLightnessCall();
				self::fail('Expected unresolved math lightness to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 3. CSSNumericValue::mergeAdditiveTypes() isolates shared cache clones from external mutation
		$mergedType = CSSNumericValue::mergeAdditiveTypes([CSS::px(10)]);
		$mergedType->__set('length', 5);
		self::assertSame(1, CSSNumericValue::mergeAdditiveTypes([CSS::px(20)])->length);

		// 4. CSSFontFeatureValuesMap limits, totalDeclarationCount sync, clone decoupling, and parser limit propagation
		$ffvSheet = CSSStyleSheet::parse('@font-feature-values Fira { @styleset { a: 1; } }', new CSSParserOptions(maxDeclarations: 2));
		$ffvRule = $ffvSheet->cssRules[0];
		self::assertInstanceOf(CSSFontFeatureValuesRule::class, $ffvRule);
		$ffvRule->styleset->set('b', 2);
		try {
			$ffvRule->styleset->set('c', 3);
			self::fail('Expected attached CSSFontFeatureValuesMap::set() to enforce sheet maxDeclarations.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$ffvRule->styleset->delete('a');
		self::assertNull($ffvRule->styleset->get('a'));
		$ffvRule->styleset->set('c', 3);
		$ffvRule->styleset->clear();
		$ffvRule->styleset->set('d', 4);
		$ffvRule->styleset->set('e', 5);
		self::assertCount(2, $ffvRule->styleset);

		$clonedFfvMap = clone $ffvRule->styleset;
		$clonedFfvMap->set('extra', 6); // Detached after clone, does not hit original sheet cap
		self::assertCount(3, $clonedFfvMap);

		try {
			(new CSSFontFeatureValuesMap())->set('many', array_fill(0, CSSStyleValue::MAX_LIST_ITEMS + 1, 1));
			self::fail('Expected CSSFontFeatureValuesMap::set() MAX_LIST_ITEMS overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		try {
			$limitFfv = CSSStyleSheet::parse('@font-feature-values Big { @styleset { a: 1; b: 2; } }', new CSSParserOptions(maxDeclarations: 1));
			self::assertNotEmpty($limitFfv->getParseErrors());
			CSSStyleSheet::parse('', new CSSParserOptions(maxDeclarations: 1))->insertRule('@font-feature-values Big { @styleset { a: 1; b: 2; } }');
			self::fail('Expected @font-feature-values parse-time declaration limit to throw on insertRule().');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 5. CSSUnparsedValue / CSSVariableReferenceValue depth +1, fallback clamp, clone, and v\61r(
		try {
			new CSSVariableReferenceValue('--a', 'var(--b)', new CSSParserOptions(maxDepth: 1));
			self::fail('Expected leaf var() depth + 1 to exceed maxDepth=1.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$withinMaxDepth = new CSSVariableReferenceValue('--a', '1px', new CSSParserOptions(maxDepth: 1));
		self::assertSame('var(--a, 1px)', (string) $withinMaxDepth);

		$tightVar = new CSSVariableReferenceValue('--a', '1px', new CSSParserOptions(maxBytes: 16, maxDepth: 2));
		try {
			$tightVar->setFallback(str_repeat('x', 20));
			self::fail('Expected CSSVariableReferenceValue::setFallback() to enforce constructed maxBytes.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$escapedVar = CSSUnparsedValue::fromCssText('v\\61r(--x, 10px)');
		self::assertInstanceOf(CSSVariableReferenceValue::class, $escapedVar[0]);
		self::assertSame('--x', $escapedVar[0]->variable);

		// Deep var() chain at depth 256 clones cleanly without stack blowup
		$deepVar = new CSSVariableReferenceValue('--d255', '1px', CSSParserOptions::unlimited());
		for ($v = 254; $v >= 0; $v--) {
			$deepVar = new CSSVariableReferenceValue('--d' . $v, new CSSUnparsedValue([$deepVar]), CSSParserOptions::unlimited());
		}
		$deepVarClone = clone $deepVar;
		self::assertSame((string) $deepVar, (string) $deepVarClone);

		// 6. CSSFunctionRule $maxBytes & parameter/returnType validation
		try {
			CSSFunctionRule::fromPrelude('--fn(--x <length>: ' . str_repeat('1', 40) . 'px) returns <length>', 32);
			self::fail('Expected CSSFunctionRule::fromPrelude() to enforce maxBytes.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		foreach ([
			static fn () => new CSSFunctionRule('--fn', '', [['name' => "--x\x00", 'type' => '*', 'defaultValue' => null]], '*'),
			static fn () => new CSSFunctionRule('--fn', '', [], "<color>\x00"),
			static fn () => new CSSFunctionRule('--fn', '', [['name' => '--x', 'type' => '*', 'defaultValue' => '1; color: red']], '*'),
		] as $badFn) {
			try {
				$badFn();
				self::fail('Expected invalid CSSFunctionRule parameter/returnType to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 7. Nested CSSStyleRule grouping insertRule context, sheet-configured limits, unlimited(), & default maxDepth 256
		$nestedSheet = CSSStyleSheet::parse('a { @media screen { color: red; } }');
		$outerStyle = $nestedSheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $outerStyle);
		$innerMedia = $outerStyle->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $innerMedia);
		$innerMedia->insertRule('@media print { .nested { color: blue; } }', 1);
		$insertedMedia = $innerMedia->cssRules[1];
		self::assertInstanceOf(CSSMediaRule::class, $insertedMedia);
		self::assertInstanceOf(CSSStyleRule::class, $insertedMedia->cssRules[0]);

		$limitedSheet = CSSStyleSheet::parse('', new CSSParserOptions(maxRules: 1, maxDeclarations: 1, maxBytes: 64, maxDepth: 2, maxMathDepth: 2));
		$limitedSheet->insertRule('a { color: red; }');
		try {
			$limitedSheet->insertRule('b { color: blue; }');
			self::fail('Expected sheet maxRules to apply to insertRule() without explicit options.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$limitedStyle = $limitedSheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $limitedStyle);
		try {
			$limitedStyle->style->setCssText('color: red; background: blue;');
			self::fail('Expected sheet maxDeclarations to apply to setCssText().');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$defaultSheet = new CSSStyleSheet();
		$deepValidCss = str_repeat('@media screen { ', 255) . 'a { color: red; }' . str_repeat(' }', 255);
		$defaultSheet->insertRule($deepValidCss);
		self::assertSame(256, $defaultSheet->getTotalRuleCount());
		$tooDeepCss = str_repeat('@media screen { ', 256) . 'a { color: red; }' . str_repeat(' }', 256);
		try {
			$defaultSheet->insertRule($tooDeepCss);
			self::fail('Expected default CSSStyleSheet maxDepth=256 to reject depth 257.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$unlimitedSheet = CSSStyleSheet::parse('', CSSParserOptions::unlimited());
		$unlimitedSheet->insertRule($tooDeepCss);
		self::assertCount(1, $unlimitedSheet->cssRules);

		// 8. CSSCalcParser unary comments & escaped math functions; CSSCounterStyleRule dashed/escaped names
		self::assertSame('-10px', (string) CSSCalcParser::parse('calc(/*c*/-10px)'));
		self::assertSame('calc(10px + 5em)', (string) CSSCalcParser::parse('c\\61lc(10px + 5em)'));
		self::assertSame('min(10px, 20px)', (string) CSSNumericValue::parse('m\\69n(10px, 20px)'));
		try {
			CSSCalcParser::parse('calc(-/*c*/10px)');
			self::fail('Expected unary operator separated from number by comment to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$csDash = new CSSCounterStyleRule('--\\61', new CSSStyleDeclaration());
		self::assertSame('--\\61', $csDash->name);
		$csSingleDash = new CSSCounterStyleRule('-\\61', new CSSStyleDeclaration());
		self::assertSame('-\\61', $csSingleDash->name);

		// 9. CSS escapes across CSSImportRule, CSSScopeRule, CSSNamespaceRule, CSSTransformValue, CSSStyleDeclaration
		$escImport = new CSSImportRule('\\75\\72\\6c("t.css") l\\61yer(base) s\\75pports(display: grid) screen');
		self::assertSame('t.css', $escImport->href);
		self::assertSame('base', $escImport->layerName);
		self::assertSame('display: grid', $escImport->supportsText);

		$escScope = new CSSScopeRule('(.card) t\\6f  (.footer)');
		self::assertSame('.card', $escScope->start);
		self::assertSame('.footer', $escScope->end);

		$escNs = new CSSNamespaceRule('svg \\75\\72\\6c("urn:svg")');
		self::assertSame('urn:svg', $escNs->namespaceURI);

		$escTr = CSSTransformValue::fromCssText('tr\\61nslateX(10px)');
		self::assertSame('translatex(10px)', (string) $escTr);

		self::assertSame('color', CSSStyleDeclaration::normalizePropertyName('C\\6f lor'));
		try {
			CSSStyleDeclaration::normalizePropertyName('co\\000001lor');
			self::fail('Expected decoded control byte in property name to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 10. CSSGroupingRule::cloneRulesIntoList() and CSSRenderer::render() callback cycle guards
		$cycOuter = new CSSMediaRule('screen');
		$cycInner = new CSSMediaRule('print');
		$cycOuter->getCssRules()->appendDetached($cycInner);
		$cycProp = new \ReflectionProperty($cycInner->cssRules, 'rules');
		$cycProp->setValue($cycInner->cssRules, [$cycOuter]);
		try {
			CSSGroupingRule::cloneRulesIntoList([$cycOuter], new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSRuleList(null, null), null, null);
			self::fail('Expected cycle in CSSGroupingRule::cloneRulesIntoList() to throw LogicException.');
		} catch (\LogicException $e) {
			self::assertStringContainsString('cyclic', strtolower($e->getMessage()));
		}
		try {
			CSSRenderer::render($cycOuter, new CSSRenderOptions(
				CSSRenderMode::Minified,
				ruleCallback: static fn (CSSRule $r, string $s): string => $s,
			));
			self::fail('Expected cycle in CSSRenderer callback path to throw LogicException.');
		} catch (\LogicException $e) {
			self::assertStringContainsString('cyclic', strtolower($e->getMessage()));
		}

		// 11. Live transform getFunctionName() / CSSTranslate 3D bool / CSSRotate -0.0 / CSSTransformValue caps
		$liveTr = new CSSTranslate('translateX', [CSS::px(10)], false);
		self::assertSame('translate3d', $liveTr->getFunctionName());
		$liveTr2 = new CSSTranslate(CSS::px(5));
		self::assertSame('translate', $liveTr2->getFunctionName());
		$liveTr2->z = CSS::px(2);
		self::assertSame('translate3d', $liveTr2->getFunctionName());

		$liveScale = new CSSScale(2);
		$liveScale->z = 3;
		self::assertSame('scale3d', $liveScale->getFunctionName());

		$liveRot = new CSSRotate(CSS::deg(0));
		$rotMat = $liveRot->toMatrix();
		self::assertSame('0', sprintf('%.0F', $rotMat->toFloat64Array()[1]));
		$parsedNegZeroRot = CSSTransformValue::fromCssText('rotate(-0deg)')[0];
		self::assertInstanceOf(CSSRotate::class, $parsedNegZeroRot);
		self::assertSame('0', sprintf('%.0F', $parsedNegZeroRot->toMatrix()->toFloat64Array()[1]));
		$liveRot->x = 1;
		self::assertSame('rotate3d', $liveRot->getFunctionName());

		$cappedTransforms = new CSSTransformValue(array_fill(0, CSSTransformValue::MAX_COMPONENTS, new CSSTranslate(1, 2)));
		try {
			$cappedTransforms->offsetSet(null, new CSSTranslate(3, 4));
			self::fail('Expected CSSTransformValue MAX_COMPONENTS overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 12. MediaList & StylePropertyMap MAX_LIST_ITEMS + CSSColor bare hex & setChannels cap + StyleDeclaration maxDeclarations
		$bigMedia = new MediaList();
		try {
			$bigMedia->setMediaText(implode(', ', array_map(static fn (int $i): string => "(min-width: {$i}px)", range(1, CSSStyleValue::MAX_LIST_ITEMS + 1))));
			self::fail('Expected MediaList MAX_LIST_ITEMS overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$appendListMap = new StylePropertyMap(new CSSStyleDeclaration());
		$appendListMap->append('transition-property', ...array_fill(0, CSSStyleValue::MAX_LIST_ITEMS, 'opacity'));
		try {
			$appendListMap->append('transition-property', 'transform');
			self::fail('Expected StylePropertyMap::append() MAX_LIST_ITEMS overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$bareHexColor = new CSSColor('--\\61', [0.1, 0.2, 0.3]);
		self::assertSame('color(--\\61  0.1 0.2 0.3)', (string) $bareHexColor);
		$parsedBareHexColor = CSSColorValue::parse('color(--\\61  0.1 0.2 0.3)');
		self::assertInstanceOf(CSSColor::class, $parsedBareHexColor);
		try {
			$color = new CSSColor('--test', [0.1, 0.2, 0.3]);
			$color->setChannels(array_fill(0, CSSColor::MAX_CUSTOM_CHANNELS + 1, 0.1));
			self::fail('Expected CSSColor::setChannels() overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$detachedDecl = new CSSStyleDeclaration();
		try {
			$detachedDecl->setCssText(
				implode('; ', array_map(static fn (int $i): string => "--d{$i}: {$i}px", range(1, 5))),
				new CSSParserOptions(maxDeclarations: 3),
			);
			self::fail('Expected strict CSSStyleDeclaration::setCssText() maxDeclarations to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		self::assertSame('', $detachedDecl->getCssText());

		$this->assertPlanV10HardeningRegressions();
	}

	private function assertPlanV10HardeningRegressions(): void
	{
		// 1. CSSSyntaxScanner: $maxParts, advanceIdentEscapeEnd EOF/newline, escape-aware trimCssWhitespace
		self::assertSame(['a', 'b'], CSSSyntaxScanner::splitTopLevel('a,b', ',', 0, null, 2));
		self::assertSame(['a', 'b'], CSSSyntaxScanner::splitTopLevelWhitespace('a b   ', 0, null, 2));
		foreach ([
			static fn () => CSSSyntaxScanner::splitTopLevel('a,b,c', ',', 0, null, 2),
			static fn () => CSSSyntaxScanner::splitTopLevelWhitespace('a b c', 0, null, 2),
		] as $limitSplit) {
			try {
				$limitSplit();
				self::fail('Expected CSSParserLimitException on $maxParts overflow.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
		try {
			CSSSyntaxScanner::splitTopLevel('a', ',', 0, null, 0);
			self::fail('Expected InvalidArgumentException when $maxParts < 1.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		self::assertSame('foo\\ ', CSSSyntaxScanner::trimCssWhitespace('foo\\  '));
		self::assertSame("foo\\\t", CSSSyntaxScanner::trimCssWhitespace("foo\\\t "));
		self::assertSame('foo\\', CSSSyntaxScanner::trimCssWhitespace("foo\\\n"));
		self::assertSame('foo\\', CSSSyntaxScanner::trimCssWhitespace('  foo\\'));
		self::assertSame('foo\\\\', CSSSyntaxScanner::trimCssWhitespace('foo\\\\ '));
		self::assertSame(['a\\'], CSSSyntaxScanner::splitTopLevel('a\\', ';'));
		self::assertSame(["a\\\n", 'b'], CSSSyntaxScanner::splitTopLevel("a\\\n;b", ';'));

		// 2. CSSCalcParser: buildMathIndex depth vs rootMode === 0 + MAX_MATH_NODES on comments
		self::assertSame('1px', (string) CSSNumericValue::parse('calc((1px))', null, new CSSParserOptions(maxMathDepth: 1)));
		self::assertSame('calc(1px + 2px)', (string) CSSNumericValue::parse('calc(1px + 2px)', null, new CSSParserOptions(maxMathDepth: 256), 256));
		try {
			CSSCalcParser::parse('calc(1px ' . str_repeat('/**/', 100_005) . ')');
			self::fail('Expected CSSParserLimitException for comment node explosion.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 3. CSSUnparsedValue / CSSVariableReferenceValue: pre-pass paren separation, MAX_LIST_ITEMS, Policy A3 parent pointers & rollbacks
		$smallDepthWithCalc = CSSUnparsedValue::fromCssText('var(--a, calc((1px + (2px))))', new CSSParserOptions(maxDepth: 2));
		self::assertInstanceOf(CSSVariableReferenceValue::class, $smallDepthWithCalc[0]);

		try {
			new CSSUnparsedValue(array_fill(0, CSSStyleValue::MAX_LIST_ITEMS + 1, 'a'));
			self::fail('Expected CSSParserLimitException on CSSUnparsedValue MAX_LIST_ITEMS overflow.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$rootUnparsed = new CSSUnparsedValue([], new CSSParserOptions(maxBytes: 32, maxDepth: 2));
		$midRef = new CSSVariableReferenceValue('--mid', '1px', CSSParserOptions::unlimited());
		$rootUnparsed[] = $midRef;
		$attachedMid = $rootUnparsed[0];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $attachedMid);
		$attachedFb = $attachedMid->fallback;
		self::assertNotNull($attachedFb);

		try {
			$attachedMid->setFallback('var(--b, var(--c))');
			self::fail('Expected A3 root maxDepth enforcement on setFallback().');
		} catch (CSSParserLimitException) {
			self::assertSame('1px', (string) $attachedMid->fallback);
		}
		$tooDeepVar = new CSSVariableReferenceValue('--d1', new CSSUnparsedValue([new CSSVariableReferenceValue('--d2', '1px')]));
		try {
			$attachedFb->offsetSet(0, $tooDeepVar);
			self::fail('Expected A3 root maxDepth enforcement on child CSSUnparsedValue::offsetSet().');
		} catch (CSSParserLimitException) {
			self::assertSame('1px', (string) $attachedFb);
		}
		try {
			$attachedFb->offsetSet(0, str_repeat('x', 40));
			self::fail('Expected A3 root maxBytes enforcement on child offsetSet().');
		} catch (CSSParserLimitException) {
			self::assertSame('var(--mid, 1px)', (string) $rootUnparsed);
		}
		try {
			$attachedMid->fallback = str_repeat('y', 40);
			self::fail('Expected A3 root maxBytes enforcement via $fallback hook.');
		} catch (CSSParserLimitException) {
			self::assertSame('1px', (string) $attachedMid->fallback);
		}
		try {
			$attachedMid->variable = '--' . str_repeat('z', 40);
			self::fail('Expected A3 root maxBytes enforcement via $variable hook.');
		} catch (CSSParserLimitException) {
			self::assertSame('--mid', $attachedMid->variable);
		}

		$nullFbTight = new CSSVariableReferenceValue('--a', null, new CSSParserOptions(maxBytes: 10));
		try {
			$nullFbTight->setVariable('--toolongname');
			self::fail('Expected combined var(<var>) byte budget enforcement on null fallback.');
		} catch (CSSParserLimitException) {
			self::assertSame('var(--a)', (string) $nullFbTight);
		}

		$clonedRoot = clone $rootUnparsed;
		$clonedRef = $clonedRoot[0];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $clonedRef);
		$clonedRef->setVariable('--ok');
		self::assertSame('var(--mid, 1px)', (string) $rootUnparsed);
		self::assertSame('var(--ok, 1px)', (string) $clonedRoot);

		// 4. Bounded arity splits preserve CSSStyleValue::parse() fallback while capping huge floods
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('background-position', 'left top extra third'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('transform', 'matrix3d(' . implode(',', array_fill(0, 17, '1')) . ')'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('transform', 'translate(' . implode(' ', array_fill(0, 20, '1px')) . ')'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'rgb(1,2,3,4,5)'));
		try {
			CSSStyleValue::parse('color', 'color(--x ' . implode(' ', array_fill(0, CSSColor::MAX_CUSTOM_CHANNELS + 10, '1')) . ')');
			self::fail('Expected CSSParserLimitException for excessive color() arguments.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 5. CSSStyleRule @-rule rejection vs escaped \@foo + comment-only rejection across mutators
		foreach ([
			static fn () => new CSSStyleRule('@font-face'),
			static fn () => new CSSStyleRule('/* c */ @media all'),
			static fn () => (new CSSStyleRule('a'))->setSelectorText('/* comment */'),
			static fn () => (new CSSStyleDeclaration())->setProperty('color', '/* c */'),
			static fn () => new FontFace('/* c */', 'url(x.woff2)'),
			static fn () => new FontFace('Inter', '/* c */'),
			static fn () => new CSSKeyframesRule('/* c */'),
			static fn () => new CSSLayerStatementRule('/* c */'),
			static fn () => new CSSLayerStatementRule('a, /* c */'),
			static fn () => new CSSFontFeatureValuesRule('/* c */'),
			static fn () => new CSSCharsetRule('/* c */'),
			static fn () => new CSSImportRule('/* c */'),
			static fn () => new CSSNamespaceRule('/* c */'),
			static fn () => (new MediaList())->appendMedium('/* c */'),
		] as $badMutator) {
			try {
				$badMutator();
				self::fail('Expected comment-only or @-hijacking input to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
		self::assertSame('\\@foo { color: red; }', (new CSSStyleRule('\\@foo', CSSStyleDeclaration::parse('color: red;')))->getCssText());
		$mediaCommentList = new MediaList('screen, /* c */');
		self::assertSame('screen', $mediaCommentList->getMediaText());

		// 6. Policies A1, A2, A4 & attached sheet tight + unlimited limit inheritance
		self::assertFalse(method_exists(CSSGroupingRule::class, 'initializeRules'));
		foreach (['none', 'initial', 'inherit', 'unset', 'revert', 'revert-layer', 'default', 'decimal', 'disc', 'square', 'circle', 'disclosure-open', 'disclosure-closed', 'inside', 'outside', '\\6e one'] as $badCounterName) {
			try {
				new CSSCounterStyleRule($badCounterName, new CSSStyleDeclaration());
				self::fail('Expected disallowed @counter-style name to throw: ' . $badCounterName);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$defaultSheet = new CSSStyleSheet();
		self::assertSame(CSSParserOptions::DEFAULT_MAX_ERRORS, $defaultSheet->resolveParserOptionsInternal(null)->maxErrors);

		$inheritedSheet = CSSStyleSheet::parse('a { color: red; }', new CSSParserOptions(maxBytes: 64, maxErrors: 1));
		$inheritedSheet->replaceSync('{} {}', null);
		self::assertSame(64, $inheritedSheet->getMaxBytesInternal());
		self::assertCount(2, $inheritedSheet->getParseErrors()); // 1 error + 1 truncation sentinel
		$inheritedSheet->replaceSync('a { color: blue; }', null);
		// Second replaceSync resets errorsTruncated so new errors can be recorded again
		$inheritedSheet->recordParseError('new err', 0);
		self::assertCount(1, $inheritedSheet->getParseErrors());

		$ruleOnTightSheet = $inheritedSheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $ruleOnTightSheet);
		foreach ([
			static fn () => $ruleOnTightSheet->setSelectorText(str_repeat('a', 70)),
			static fn () => $ruleOnTightSheet->style->setProperty('color', str_repeat('a', 70)),
			static fn () => $ruleOnTightSheet->styleMap->set(str_repeat('a', 70), 'red'),
			static fn () => $ruleOnTightSheet->styleMap->has(str_repeat('a', 70)),
			static fn () => $ruleOnTightSheet->styleMap->delete(str_repeat('a', 70)),
			static fn () => $inheritedSheet->media->setMediaText(str_repeat('a', 70)),
		] as $exceededTight) {
			try {
				$exceededTight();
				self::fail('Expected attached 64-byte sheet limit to throw CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Unlimited sheet null maxBytes must NOT collapse back to DEFAULT_MAX_BYTES
		$unlimSheet = CSSStyleSheet::parse('a { color: red; }', CSSParserOptions::unlimited());
		self::assertNull($unlimSheet->getMaxBytesInternal());

		// 7. Escaped control-bytes, escaped leading --, CSSContainerRule, Color, FunctionRule, Batch 3 & v8.1 boundaries
		self::assertSame('--foo\\:bar', CSSStyleDeclaration::normalizePropertyName('\\2d -foo\\:bar'));
		self::assertSame('--foo', CSSStyleDeclaration::normalizePropertyName('-\\2d foo'));
		try {
			CSSStyleDeclaration::normalizePropertyName('--a\\07 g');
			self::fail('Expected escaped control byte in custom property name to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			new CSSKeywordValue('\\2d -');
			self::fail('Expected decoded bare "--" keyword to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$escapedContainer = new CSSContainerRule('card\\(1 (width > 10px)');
		self::assertSame('card\\(1', $escapedContainer->containerName);

		self::assertSame('rgb(none 100% 0%)', (string) CSSColorValue::parse('rgb(\\6e one /*c*/ 100% /*c*/ 0%)'));
		self::assertSame('srgb', (new CSSColor('\\73 rgb', [0.1, 0.2, 0.3]))->colorSpace->value);

		$escFn = CSSFunctionRule::fromPrelude('--f(--a\\:b <length>: 1px) \\72 eturns <length>');
		self::assertSame('--a\\:b', $escFn->getParameters()[0]['name']);
		self::assertSame('<length>', $escFn->returnType);
		foreach (['--f(--a<length>)', '--f() returns<length>'] as $badFn) {
			try {
				CSSFunctionRule::fromPrelude($badFn);
				self::fail('Expected missing whitespace in @function prelude to throw: ' . $badFn);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$escNsPrefix = new CSSNamespaceRule('\\61  "urn:a"');
		self::assertSame('\\61', $escNsPrefix->prefix);

		self::assertFalse(CSS::hasVarReference('10var(--x)'));
		self::assertFalse(CSS::hasVarReference('#var(--x)'));
		self::assertFalse(CSS::hasVarReference('@var(--x)'));

		foreach ([
			static fn () => (new CSSRuleList(null))->__set('foo', 'bar'),
			static fn () => (new CSSFontFeatureValuesMap())->__set('foo', 'bar'),
			static fn () => (new StyleSheetList())->__set('foo', 'bar'),
		] as $roSet) {
			try {
				$roSet();
				self::fail('Expected read-only __set() to throw Error.');
			} catch (Error $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$emptyPageStyle = new CSSStyleDeclaration();
		$emptyPageStyle->setValidatedProperty('--empty', '');
		$pageRule = new CSSPageRule(':first', $emptyPageStyle);
		self::assertSame(['value' => '', 'priority' => ''], $pageRule->style->getDeclarationsRaw()['--empty']);

		try {
			(new CSSKeyframesRule('fade'))->deleteRule('from');
			self::fail('Expected detached CSSKeyframesRule::deleteRule() to throw LogicException.');
		} catch (\LogicException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$propRule = new CSSPropertyRule('--x', new CSSStyleDeclaration('syntax: "*"; inherits: false;'));
		$propRule->style->setCssText('syntax: "*"; inherits: false; initial-value: ;');
		self::assertSame('', $propRule->initialValue);

		$symHwb = new CSSHWB(CSSNumericValue::parse('calc(10deg + 20deg)'), 60, 60);
		try {
			$symHwb->toRGB();
			self::fail('Expected symbolic hue in CSSHWB with w+b>=1 to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$zeroRot = new CSSRotate(CSS::deg(45));
		$zAxis = $zeroRot->z;
		self::assertInstanceOf(CSSUnitValue::class, $zAxis);
		$zAxis->value = 0;
		try {
			$zeroRot->setIs2D(false);
			self::fail('Expected setIs2D(false) on zero axis to throw before mutating state.');
		} catch (InvalidArgumentException) {
			self::assertTrue($zeroRot->is2D);
		}

		try {
			new CSSImageValue('url(a.png)', new CSSParserOptions(maxBytes: 4));
			self::fail('Expected CSSImageValue to enforce $options->maxBytes.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$commaDecl = new CSSStyleDeclaration();
		$commaDecl->setProperty('background-image', ',');
		$commaMap = new \Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMapReadOnly($commaDecl);
		self::assertSame([], $commaMap->getAll('background-image'));
		self::assertSame([[]], $commaMap->values());
		self::assertSame([['background-image', []]], $commaMap->entries());

		// v8.1 uncovered boundaries: CSSRotate [4] = -sin, escapeHtmlStyleTag variants, maxDepth > 512
		$rot90Mat = (new CSSRotate(CSS::deg(90)))->toMatrix()->toFloat64Array();
		self::assertEqualsWithDelta(-1.0, $rot90Mat[4], 1e-12);
		$escapedRender = CSSRenderer::render(
			CSSStyleSheet::parse('a { content: "</style\t></style\n></style/></style"; }'),
			new CSSRenderOptions(
				CSSRenderMode::Minified,
				ruleCallback: static fn (CSSRule $r, string $s): string => $s . '</style>',
				escapeHtmlStyleTag: true,
			),
		);
		self::assertDoesNotMatchRegularExpression('/<\/style(?=[\t\n\f\r \/>]|$)/i', $escapedRender);

		$this->assertPlanV11RegressionHardening();
	}

	private function assertPlanV11RegressionHardening(): void
	{
		// 1. Direct CSSOpaqueRule invariants
		$opaque = new CSSOpaqueRule('@unknown foo { bar: 1; }');
		self::assertSame(CSSRule::UNKNOWN_RULE, $opaque->getType());
		foreach ([
			['@bad { (unclosed; }', InvalidArgumentException::class],
			["@bad \x07;", InvalidArgumentException::class],
			['@too-long { a: 1; }', CSSParserLimitException::class, 8],
		] as $case) {
			$threw = false;
			try {
				new CSSOpaqueRule($case[0], $case[2] ?? CSSParserOptions::DEFAULT_MAX_BYTES);
			} catch (InvalidArgumentException $e) {
				$threw = true;
				self::assertInstanceOf($case[1], $e);
			}
			self::assertTrue($threw, 'Expected CSSOpaqueRule to reject: ' . $case[0]);
		}

		// 2. Policy A3 / B1: per-ancestor byte & depth budgets, __toString() outer vs inner, GC & clone clamping
		$tightChild = new CSSVariableReferenceValue('--long-var-name', null, new CSSParserOptions(maxBytes: 24));
		$wideParent = new CSSUnparsedValue([str_repeat('a', 30) . ' ', $tightChild], new CSSParserOptions(maxBytes: 100));
		$attachedChild = $wideParent[1];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $attachedChild);
		$attachedChild->setVariable('--a');
		self::assertSame('--a', $attachedChild->variable);

		$shrinkFb = new CSSUnparsedValue('hello', new CSSParserOptions(maxBytes: 10));
		$widerRef = new CSSVariableReferenceValue('--longvarname', $shrinkFb, new CSSParserOptions(maxBytes: 100));
		self::assertSame('var(--longvarname, hello)', (string) $widerRef);
		$widerRef->getFallback()?->offsetUnset(0);
		self::assertSame('var(--longvarname, )', (string) $widerRef);

		$wrapperFits = new CSSVariableReferenceValue('--x', 'a', new CSSParserOptions(maxBytes: 11));
		self::assertSame('var(--x, a)', (string) $wrapperFits);
		$fbTwo = new CSSUnparsedValue('ab', new CSSParserOptions(maxBytes: 2));
		$outerNarrow = new CSSVariableReferenceValue('--x', $fbTwo, new CSSParserOptions(maxBytes: 12));
		self::assertSame('var(--x, ab)', (string) $outerNarrow);
		$fbFive = new CSSUnparsedValue('12345', new CSSParserOptions(maxBytes: 5));
		$outerBig = new CSSVariableReferenceValue('--longcustom', $fbFive, new CSSParserOptions(maxBytes: 40));
		self::assertSame('var(--longcustom, 12345)', (string) $outerBig);
		try {
			new CSSVariableReferenceValue('--very-long-variable-name', '1', new CSSParserOptions(maxBytes: 12));
			self::fail('Expected __construct wrapper overflow to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Multi-ancestor decreasing-budget staircase + GC & clone maxDepth clamping
		$stairLeafFb = new CSSUnparsedValue('12345', new CSSParserOptions(maxBytes: 10));
		$stairMidRef = new CSSVariableReferenceValue('--m', $stairLeafFb, new CSSParserOptions(maxBytes: 30));
		$stairTop = new CSSUnparsedValue([str_repeat('p', 40) . ' ', $stairMidRef], new CSSParserOptions(maxBytes: 80));
		$stairAttachedMid = $stairTop[1];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $stairAttachedMid);
		$stairAttachedFb = $stairAttachedMid->getFallback();
		self::assertNotNull($stairAttachedFb);
		$stairAttachedFb[0] = 'ok';
		self::assertSame(['ok'], $stairAttachedFb->getValues());
		try {
			$stairAttachedFb[0] = '12345678901'; // 11 bytes exceeds leaf 10B cap even though top is <80B
			self::fail('Expected intermediate ancestor 10B cap in multi-ancestor staircase to throw.');
		} catch (CSSParserLimitException) {
			self::assertSame('ok', (string) $stairAttachedFb);
		}
		try {
			$stairAttachedMid->setVariable('--medium-var-name-overflow'); // exceeds mid 30B cap
			self::fail('Expected mid ancestor 30B cap in multi-ancestor staircase to throw.');
		} catch (CSSParserLimitException) {
			self::assertSame('--m', $stairAttachedMid->variable);
		}

		// Depth-cap inversion check & maxDepth: 1 remaining===0 plain fallback & negative remaining depth
		$deepTightChild = new CSSVariableReferenceValue('--c', 'ok', new CSSParserOptions(maxDepth: 2));
		$midUnp = new CSSUnparsedValue([$deepTightChild], new CSSParserOptions(maxDepth: 10));
		$midRef = new CSSVariableReferenceValue('--b', $midUnp, new CSSParserOptions(maxDepth: 10));
		$outerUnp = new CSSUnparsedValue([$midRef], new CSSParserOptions(maxDepth: 10));
		$depthRoot = new CSSUnparsedValue([new CSSVariableReferenceValue('--a', $outerUnp, new CSSParserOptions(maxDepth: 10))], new CSSParserOptions(maxDepth: 10));
		$leafRef = $depthRoot[0]?->getFallback()[0]?->getFallback()[0];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $leafRef);
		$leafRef->setFallback('plain-string');
		self::assertSame('plain-string', (string) $leafRef->getFallback());
		$leafRef->setFallback(null);
		self::assertNull($leafRef->getFallback());

		$oneDeep = new CSSVariableReferenceValue('--a', '1px', new CSSParserOptions(maxDepth: 1));
		$oneDeep->setFallback('2px');
		self::assertSame(['2px'], $oneDeep->getFallback()?->getValues());
		try {
			$oneDeep->setFallback('var(--b, 2px)');
			self::fail('Expected nested var() to exceed maxDepth: 1.');
		} catch (CSSParserLimitException $e) {
			self::assertSame('2px', (string) $oneDeep->getFallback());
		}
		try {
			$oneDeep->setFallback('1px', new CSSParserOptions(maxDepth: 1), 1);
			self::fail('Expected negative remaining depth to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('2px', $oneDeep->getFallback()->offsetGet(0));
		}

		// Rollbacks when root is CSSVariableReferenceValue + pre-clone guard + GC depth persistence
		$refRoot = new CSSVariableReferenceValue('--r', new CSSUnparsedValue([new CSSVariableReferenceValue('--k', '1px')], new CSSParserOptions(maxBytes: 25, maxDepth: 2)), new CSSParserOptions(maxBytes: 25, maxDepth: 2));
		$refRootFb = $refRoot->getFallback();
		self::assertNotNull($refRootFb);
		try {
			$refRootFb->offsetSet(0, str_repeat('a', 30));
			self::fail('Expected CSSVariableReferenceValue-rooted offsetSet rollback.');
		} catch (CSSParserLimitException) {
			self::assertInstanceOf(CSSVariableReferenceValue::class, $refRootFb[0]);
		}
		try {
			$refRootFb->offsetSet(0, new CSSVariableReferenceValue('--k', str_repeat('a', 40), CSSParserOptions::unlimited()));
			self::fail('Expected pre-clone maxBytes guard in offsetSet.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('var(--k, 1px)', (string) $refRootFb);
		}

		$bigSingleRef = new CSSVariableReferenceValue('--x', str_repeat('x', 500_000));
		try {
			new CSSUnparsedValue(array_fill(0, 100, $bigSingleRef), new CSSParserOptions(maxBytes: 100));
			self::fail('Expected pre-clone byte check in CSSUnparsedValue::__construct(array).');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Multi-ancestor byte staircase + GC maxDepth clamp
		$gcRoot = new CSSUnparsedValue([], new CSSParserOptions(maxBytes: 200, maxDepth: 2));
		$gcRoot[] = new CSSVariableReferenceValue('--a', '1', CSSParserOptions::unlimited());
		$gcChild = $gcRoot[0];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $gcChild);
		$gcClone = $gcChild->clone();
		unset($gcRoot);
		gc_collect_cycles();
		foreach ([$gcChild, $gcClone] as $surviving) {
			try {
				$surviving->setFallback('var(--b, var(--c, 1))');
				self::fail('Expected clamped maxDepth=2 to survive root GC and cloning.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// 3. Policy B2: CSSImportRule media clause smuggling, token caps & prelude maxBytes overrun
		$importSheet = CSSStyleSheet::parse('@import "https://example.com/a.css" screen;', new CSSParserOptions(maxBytes: 64));
		$importRule = $importSheet->cssRules[0];
		self::assertInstanceOf(CSSImportRule::class, $importRule);
		foreach (['layer(evil)', 'supports(display: grid)', 'layer, screen', 'layer(foo), screen', 'layer(foo)screen', 'supports(display:grid)screen', '\\6c ayer(x)'] as $badMedia) {
			try {
				$importRule->media->setMediaText($badMedia);
				self::fail('Expected CSSImportRule::media mutation to reject: ' . $badMedia);
			} catch (InvalidArgumentException $e) {
				self::assertSame('screen', $importRule->mediaText);
			}
		}
		try {
			$importRule->media->setMediaText(str_repeat('b', 40));
			self::fail('Expected CSSImportRule prelude maxBytes overrun to throw CSSParserLimitException.');
		} catch (CSSParserLimitException) {
			self::assertSame('screen', $importRule->getMediaText());
		}

		// 4. Policy B3: Detached CSSRule & MediaList $maxBytes persistence + unlimited propagation + clone GC
		$detachedTightImport = new CSSImportRule('"a.css" screen', 20);
		try {
			$detachedTightImport->media->appendMedium('1234567890');
			self::fail('Expected detached CSSImportRule to retain 20-byte prelude cap.');
		} catch (CSSParserLimitException) {
			self::assertSame('screen', $detachedTightImport->mediaText);
		}
		$detachedMedia = new MediaList('screen', null, 12);
		try {
			$detachedMedia->appendMedium('projection');
			self::fail('Expected detached MediaList to retain 12-byte limit.');
		} catch (CSSParserLimitException) {
			self::assertSame('screen', $detachedMedia->mediaText);
		}
		$unlimSheet = CSSStyleSheet::parse('a { color: red; } @media screen {}', CSSParserOptions::unlimited());
		$unlimSheet->replaceSync($unlimSheet->cssText, null);
		self::assertNull($unlimSheet->getMaxBytesInternal());
		$clonedMediaRule = clone $unlimSheet->cssRules[1];
		self::assertInstanceOf(CSSMediaRule::class, $clonedMediaRule);
		self::assertNull($clonedMediaRule->resolveMaxBytesInternal());

		// replaceSync with explicit $options + $errorsTruncated preservation
		$reSyncSheet = new CSSStyleSheet();
		$reSyncSheet->replaceSync('{} {}', new CSSParserOptions(maxErrors: 1));
		self::assertCount(2, $reSyncSheet->getParseErrors());

		// 5. Parser/Scanner/CSS/Declaration/TypedOM/Color/Geometry v11 edge cases
		$bigFfv = '@font-feature-values F { @styleset { a: ' . str_repeat('1 ', CSSStyleValue::MAX_LIST_ITEMS + 5) . '1; } }';
		$ffvSheet = CSSStyleSheet::parse($bigFfv);
		self::assertNotSame([], $ffvSheet->getParseErrors());

		foreach ([
			'@container style(--x: 1) { a { color: red; } }',
			'@container scroll-state(stuck: top) { a { color: red; } }',
			'@container foo.bar (width > 1px) { a { color: red; } }',
		] as $unnamedContainer) {
			$cSheet = CSSStyleSheet::parse($unnamedContainer);
			$cRule = $cSheet->cssRules[0];
			self::assertInstanceOf(CSSContainerRule::class, $cRule);
			self::assertSame('', $cRule->containerName);
		}

		// Keyframe comma limit, ViewTransition types limit, CSS::registerProperty syntax limit, CSS::supports clause cap
		try {
			(new CSSKeyframeRule('from'))->setKeyText(str_repeat('0%,', CSSStyleValue::MAX_LIST_ITEMS + 2) . '100%');
			self::fail('Expected CSSKeyframeRule comma limit to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			CSS::registerProperty(['name' => '--too-many-alt', 'syntax' => str_repeat('a|', CSSStyleValue::MAX_LIST_ITEMS + 2) . 'a', 'inherits' => false, 'initialValue' => 'a']);
			self::fail('Expected registerProperty syntax pipe limit to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		CSS::clearRegisteredProperties();
		CSS::registerProperty(['name' => '\\2d\\2d esc-prop', 'syntax' => '*', 'inherits' => false]);
		self::assertNotNull(CSS::getRegisteredProperty('\\2d\\2d esc-prop'));
		self::assertNull(CSS::getRegisteredProperty('123invalid'));
		self::assertTrue(CSS::unregisterProperty('\\2d\\2d esc-prop'));
		self::assertFalse(CSS::unregisterProperty('123invalid'));
		self::assertFalse(CSS::supports(str_repeat('(--a:1) and ', CSSStyleValue::MAX_LIST_ITEMS + 10) . '(--a:1)'));

		// Trailing escaped whitespace round-trips & CSS::supports selector/!important escapes + scanner hex-space escape
		$escDecl = new CSSStyleDeclaration();
		$escDecl->setProperty('--x', 'foo\\ ');
		$reparsedDecl = CSSStyleDeclaration::parse($escDecl->getCssText());
		self::assertSame('foo\\ ', $reparsedDecl->getPropertyValue('--x'));
		self::assertTrue(CSS::supports('selector(.a\\ )'));
		self::assertTrue(CSS::supports('(--x: a!b \\ !important)'));
		CSSSyntaxScanner::assertValidFragment('foo\\3b bar', 'Test', [' ']);
		try {
			CSSStyleDeclaration::normalizePropertyName("--\xFF\\61");
			self::fail('Expected invalid UTF-8 with backslash in property name to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// StylePropertyMap comment-only rejection, empty custom-prop options, pre-trim maxBytes, CSSTransformValue, CSSCharsetRule, CSSPageRule, DOMMatrix, CSSHWB toOKLCH
		$tightMapSheet = CSSStyleSheet::parse('a { --empty: ; }', new CSSParserOptions(maxBytes: 32, maxDepth: 2));
		$tightRule = $tightMapSheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $tightRule);
		self::assertNull($tightRule->styleMap->get('--nonexistent'));
		$emptyVal = $tightRule->styleMap->get('--empty');
		self::assertInstanceOf(CSSUnparsedValue::class, $emptyVal);
		self::assertSame(32, $emptyVal->getMaxBytesInternal());
		self::assertSame(2, $emptyVal->getMaxDepthInternal());
		try {
			$tightRule->styleMap->set('color', '/* comment */');
			self::fail('Expected comment-only StylePropertyMap::set on non-custom property to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('transform', 'translatefoo(calc(calc(calc(1px))))', new CSSParserOptions(maxMathDepth: 2)));
		self::assertSame('UTF-8', (new CSSCharsetRule('/*c*/ "UTF-8"'))->getEncoding());
		self::assertTrue((new \Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix('/* c */ none'))->isIdentity());
		$detachedMargin = new CSSMarginRule('top-left', new CSSStyleDeclaration());
		/** @var list<CSSMarginRule> $badMarginList */
		$badMarginList = [$detachedMargin, new CSSStyleRule('body')];
		try {
			new CSSPageRule('', null, $badMarginList);
			self::fail('Expected non-CSSMarginRule in CSSPageRule::$marginRules to throw.');
		} catch (InvalidArgumentException) {
			self::assertNull($detachedMargin->getParentRule());
		}
		try {
			(new CSSHWB(CSSNumericValue::parse('calc(10deg + 20deg)'), 60, 60))->toOKLCH();
			self::fail('Expected symbolic hue in CSSHWB::toOKLCH() with w+b>=1 to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$this->assertPlanV12RegressionHardening();
	}

	private function assertPlanV12RegressionHardening(): void
	{
		// 1. WS1: CSSImportRule unquoted url(), adjacent tokens, anonymous layer, control-escape linear scan & token cap
		foreach ([
			'"a.css" url(a[b), layer(evil), supports(display: grid)',
			'"a.css" url(a[b) layer(evil)',
			'"a.css" (min-width: 600px)layer(foo)',
			'"a.css" (min-width: 600px)supports(display: grid)',
			'"a.css" (min-width: 600px)layer',
			'"a.css" screen/layer(foo)',
			'"a.css" ""layer(evil)',
			'"a.css" [a]supports(display: grid)',
			'"a.css" 1e+layer(foo)',
		] as $badImportPrelude) {
			try {
				new CSSImportRule($badImportPrelude);
				self::fail('Expected CSSImportRule constructor to reject: ' . $badImportPrelude);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		foreach ([
			'url(a[b), layer(evil)',
			'url(a[b) layer(evil)',
			'(min-width: 600px)layer(foo)',
			'(color)supports(display: grid)',
			'screen/layer(foo)',
			'""layer(evil)',
			'1e+layer(foo)',
			'layer"x", screen',
			'layer[a], screen',
		] as $badMediaText) {
			$importBase = new CSSImportRule('"a.css" screen');
			try {
				$importBase->media->setMediaText($badMediaText);
				self::fail('Expected MediaList::setMediaText() to reject: ' . $badMediaText);
			} catch (InvalidArgumentException) {
				self::assertSame('screen', $importBase->getMediaText());
			}
			try {
				$importBase->media->appendMedium($badMediaText);
				self::fail('Expected MediaList::appendMedium() to reject: ' . $badMediaText);
			} catch (InvalidArgumentException) {
				self::assertSame('@import "a.css" screen;', $importBase->getCssText());
			}
		}

		$anonLayerQuote = new CSSImportRule('"a.css" layer"x", screen');
		self::assertSame('', $anonLayerQuote->layerName);
		self::assertSame('"x", screen', $anonLayerQuote->mediaText);
		$anonLayerBracket = new CSSImportRule('"a.css" layer[a], screen');
		self::assertSame('', $anonLayerBracket->layerName);
		self::assertSame('[a], screen', $anonLayerBracket->mediaText);

		// Valid escaped control-byte function & long ident remain linear-time (no O(N^2) rescan)
		$escCtlMedia = new CSSImportRule('"a.css" a\\7 layer(ok)');
		self::assertSame('a\\7 layer(ok)', $escCtlMedia->mediaText);
		$longCtlIdent = new CSSImportRule('"a.css" ' . str_repeat('a', 10_000) . '\\7 ');
		self::assertGreaterThan(10_000, strlen($longCtlIdent->mediaText));

		// Token caps via url(a[b) bypass attempt and direct constructor
		try {
			$importBase->media->setMediaText('url(a[b) ' . str_repeat('x ', CSSStyleValue::MAX_LIST_ITEMS + 5));
			self::fail('Expected CSSImportRule token cap to reject flood after unquoted url().');
		} catch (CSSParserLimitException) {
			self::assertSame('screen', $importBase->mediaText);
		}
		try {
			new CSSImportRule('"a.css" ' . str_repeat('screen ', CSSStyleValue::MAX_LIST_ITEMS + 5));
			self::fail('Expected CSSImportRule constructor token cap to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 2. WS2: Policy B3 detached/cloned/GC $maxBytes completeness
		$detachedStyleRule = new CSSStyleRule('a', null, 32);
		$detachedStyleRule->style->setProperty('color', 'red');
		$detachedStyleRule->styleMap->set('margin', '10px');
		foreach ([
			static fn () => $detachedStyleRule->style->setProperty('color', str_repeat('a', 40)),
			static fn () => $detachedStyleRule->style->setCssText('color: ' . str_repeat('b', 40) . ';'),
			static fn () => $detachedStyleRule->setSelectorText(str_repeat('a', 40)),
			static fn () => $detachedStyleRule->styleMap->set('color', str_repeat('c', 40)),
			static fn () => $detachedStyleRule->styleMap->append('color', str_repeat('d', 40)),
		] as $op) {
			try {
				$op();
				self::fail('Expected detached CSSStyleRule 32-byte cap to enforce CSSParserLimitException.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$tightSheetForMedia = CSSStyleSheet::parse('a {}', new CSSParserOptions(maxBytes: 12));
		$survivingMedia = $tightSheetForMedia->media;
		$clonedMedia = clone $tightSheetForMedia->media;
		unset($tightSheetForMedia);
		gc_collect_cycles();
		foreach ([$survivingMedia, $clonedMedia] as $ml) {
			try {
				$ml->appendMedium('very-long-medium');
				self::fail('Expected MediaList limit (12B) to survive sheet GC and clone.');
			} catch (CSSParserLimitException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$preBuiltMediaSheet = new CSSStyleSheet(media: new MediaList('screen', null, 12));
		$alignedMedia = $preBuiltMediaSheet->media;
		unset($preBuiltMediaSheet);
		gc_collect_cycles();
		$alignedMedia->appendMedium('projection-wide-name');
		self::assertSame('screen, projection-wide-name', $alignedMedia->mediaText);

		$tightSheetForClone = CSSStyleSheet::parse('a { color: red; } @media screen {}', new CSSParserOptions(maxBytes: 40));
		$clonedRuleFromTight = clone $tightSheetForClone->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $clonedRuleFromTight);
		$tightMediaRuleForClone = $tightSheetForClone->cssRules[1];
		self::assertInstanceOf(CSSMediaRule::class, $tightMediaRuleForClone);
		$clonedMediaFromTight = clone $tightMediaRuleForClone->media;
		try {
			$clonedRuleFromTight->setSelectorText(str_repeat('x', 50));
			self::fail('Expected CSSRule::__clone() to snapshot 40-byte limit.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		try {
			$clonedMediaFromTight->appendMedium(str_repeat('m', 50));
			self::fail('Expected MediaList clone from tight rule to snapshot 40-byte limit.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$unlimSheetForClone = CSSStyleSheet::parse('@media screen {}', CSSParserOptions::unlimited());
		$unlimMediaRuleForClone = $unlimSheetForClone->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $unlimMediaRuleForClone);
		$clonedUnlimMedia = clone $unlimMediaRuleForClone->media;
		$clonedUnlimMedia->appendMedium(str_repeat('m', 256));
		self::assertStringContainsString(str_repeat('m', 256), $clonedUnlimMedia->mediaText);

		$detachedFfv = new CSSFontFeatureValuesRule('MyFont', 20);
		try {
			$detachedFfv->stylistic->set(str_repeat('a', 30), 1);
			self::fail('Expected detached CSSFontFeatureValuesRule 20-byte cap via map::set().');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$tightFontFace = CSSParser::parseSingleRule('@font-face { font-family: A; }', new CSSParserOptions(maxBytes: 40));
		self::assertInstanceOf(CSSFontFaceRule::class, $tightFontFace);
		try {
			$tightFontFace->style->setProperty('font-family', str_repeat('a', 60));
			self::fail('Expected parsed CSSFontFaceRule to retain 40-byte cap.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$tightVt = CSSParser::parseSingleRule('@view-transition { navigation: auto; }', new CSSParserOptions(maxBytes: 50));
		self::assertInstanceOf(CSSViewTransitionRule::class, $tightVt);
		try {
			$tightVt->style->setProperty('types', str_repeat('a', 60));
			self::fail('Expected parsed CSSViewTransitionRule to retain 50-byte cap.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$nestedSr = CSSParser::parseSingleRule('a { & b {} color: red; }', new CSSParserOptions(maxBytes: 40));
		self::assertInstanceOf(CSSStyleRule::class, $nestedSr);
		$nestedDecls = $nestedSr->cssRules[1];
		self::assertInstanceOf(CSSNestedDeclarations::class, $nestedDecls);
		try {
			$nestedDecls->style->setProperty('color', str_repeat('a', 60));
			self::fail('Expected CSSNestedDeclarations to retain 40-byte cap.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$fnRuleParsed = CSSParser::parseSingleRule('@function --f() { result: 1px; }', new CSSParserOptions(maxBytes: 40));
		self::assertInstanceOf(CSSFunctionRule::class, $fnRuleParsed);
		$fnDecls = $fnRuleParsed->cssRules[0];
		self::assertInstanceOf(CSSFunctionDeclarations::class, $fnDecls);
		try {
			$fnDecls->style->setProperty('result', str_repeat('a', 60));
			self::fail('Expected CSSFunctionDeclarations to retain 40-byte cap.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$unlimFontDecl = new CSSStyleDeclaration();
		$unlimFontDecl->setValidatedProperty('font-family', str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES + 1), '');
		$unlimFontFace = new CSSFontFaceRule($unlimFontDecl, null);
		self::assertNull($unlimFontFace->resolveMaxBytesInternal());
		self::assertSame(CSSParserOptions::DEFAULT_MAX_BYTES + 1, strlen($unlimFontFace->style->getPropertyValue('font-family')));
		unset($unlimFontDecl, $unlimFontFace);

		$unlimKf = new CSSKeyframesRule('spin', 'keyframes', null);
		$unlimKf->setNameText(str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES + 1));
		self::assertSame(CSSParserOptions::DEFAULT_MAX_BYTES + 1, strlen($unlimKf->name));
		unset($unlimKf);

		// 3. WS3: Invalid UTF-8 var(), post-parse expansion, setFallback maxDepth clamp, offsetUnset, array error msg, stringify prefix, negative counters, StylePropertyMap precedence
		try {
			new CSSUnparsedValue(str_repeat('var(--a, ', 10) . '1' . str_repeat(')', 10) . " \xFF", new CSSParserOptions(maxDepth: 2));
			self::fail('Expected invalid UTF-8 not to blind var() maxDepth check.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		$utf8VarParsed = CSSStyleValue::parse('--x', "var(--secret) \xFF");
		self::assertInstanceOf(CSSUnparsedValue::class, $utf8VarParsed);
		self::assertInstanceOf(CSSVariableReferenceValue::class, $utf8VarParsed[0]);

		try {
			new CSSUnparsedValue('var(--a,x)', new CSSParserOptions(maxBytes: 10));
			self::fail('Expected post-parse var(--a, x) 11-byte expansion to exceed 10-byte cap.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('10 bytes', $e->getMessage());
		}

		$depthChainRoot = new CSSUnparsedValue('var(--a, var(--b, var(--c, var(--d))))', new CSSParserOptions(maxDepth: 4));
		$refD = $depthChainRoot[0]->fallback[0]->fallback[0]->fallback[0];
		self::assertInstanceOf(CSSVariableReferenceValue::class, $refD);
		$refD->setFallback('x', new CSSParserOptions(maxDepth: 2));
		$clonedRefDFb = $refD->fallback?->clone();
		self::assertNotNull($clonedRefDFb);
		self::assertSame(2, $clonedRefDFb->getMaxDepthInternal());
		try {
			$clonedRefDFb[] = new CSSVariableReferenceValue('--e', 'var(--f, var(--g, var(--h)))');
			self::fail('Expected cloned fallback to keep stricter caller maxDepth=2.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$unsetRoot = new CSSVariableReferenceValue('--r', new CSSUnparsedValue(['/*', 'x', '*/']));
		try {
			$unsetRoot->getFallback()?->offsetUnset(2);
			self::fail('Expected invalid fragment rollback on offsetUnset() with CSSVariableReferenceValue root.');
		} catch (InvalidArgumentException) {
			self::assertSame('var(--r, /*x*/)', (string) $unsetRoot);
		}

		try {
			new CSSUnparsedValue(['1234567890', new CSSVariableReferenceValue('--abcdefghij')], new CSSParserOptions(maxBytes: 20));
			self::fail('Expected array constructor byte cap error to report configured maxBytes=20.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('20 bytes', $e->getMessage());
		}

		try {
			CSSUnparsedValue::stringifyIteratively([], 5, '1234567890', '');
			self::fail('Expected stringifyIteratively() to enforce maxBytes on prefix.');
		} catch (CSSParserLimitException $e) {
			self::assertStringContainsString('5 bytes', $e->getMessage());
		}

		foreach ([
			static fn () => CSSParser::parseSingleRule('a {}', null, -1),
			static fn () => CSSParser::parseSingleRule('a {}', null, 0, -1),
			static fn () => CSSParser::parseSingleRule('a {}', null, 0, 0, -1),
			static fn () => CSSParser::parseSingleNestedRule('color: red;', null, -1),
			static fn () => CSSParser::parseSingleKeyframeRule('from {}', null, 0, -1),
			static fn () => CSSParser::parseSingleMarginRule('@top-left {}', null, 0, 0, -1),
			static fn () => new CSSUnparsedValue('1px', null, -1),
			static fn () => CSSUnparsedValue::fromCssText('1px', null, -1),
			static fn () => new CSSVariableReferenceValue('--a', null, null, -1),
			static fn () => (new CSSVariableReferenceValue('--a'))->setFallback(null, null, -1),
			static fn () => CSSCalcParser::parse('calc(1px)', null, -1),
			static fn () => CSSCalcParser::parseExpression('1px', null, -1),
			static fn () => CSSNumericValue::parse('1px', null, null, -1),
			static fn () => CSSNumericValue::parse('width', '1px', null, -1),
			static fn () => CSSNumericValue::tryParse('1px', null, -1),
			static fn () => CSSNumericValue::tryParseValidated('1px', null, -1),
		] as $negCall) {
			try {
				$negCall();
				self::fail('Expected negative depth/counter argument to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString('negative', strtolower($e->getMessage()));
			}
		}

		$mapSheet = CSSStyleSheet::parse('a {}', new CSSParserOptions(maxBytes: 20));
		$mapStyleRule = $mapSheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $mapStyleRule);
		$map = $mapStyleRule->styleMap;
		try {
			$map->set('color', '/* unclosed');
			self::fail('Expected unclosed comment to trigger validatePropertyValue error first.');
		} catch (InvalidArgumentException $e) {
			self::assertStringContainsString('comment', strtolower($e->getMessage()));
		}
		try {
			$map->set('margin', '10px', '/* 1234567890123 */');
			self::fail('Expected oversized comment-only trailing argument to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 4. WS4: StylePropertyMapReadOnly get vs getAll, CSSPageRule duplicate atomicity, ViewTransition/PropertyRule comments, 65536 parens, Color pre-scan, comment-only rejections
		$roDecl = new CSSStyleDeclaration();
		$roDecl->setProperty('background-image', '/* c */, url(a.png)');
		$roMap = new StylePropertyMapReadOnly($roDecl);
		$roFirst = $roMap->get('background-image');
		$roAll = $roMap->getAll('background-image');
		self::assertInstanceOf(CSSImageValue::class, $roFirst);
		self::assertCount(1, $roAll);
		self::assertEquals($roFirst, $roAll[0]);
		$roDecl->setProperty('background-image', '/* a */, /* b */');
		self::assertNull($roMap->get('background-image'));
		self::assertSame([], $roMap->getAll('background-image'));

		$dupMargin = new CSSMarginRule('top-left', new CSSStyleDeclaration('content: "hi";'));
		$pageStyle = new CSSPageDescriptors('margin: 1in;');
		try {
			new CSSPageRule('', $pageStyle, [$dupMargin, $dupMargin]);
			self::fail('Expected duplicate CSSMarginRule in CSSPageRule constructor to throw.');
		} catch (InvalidArgumentException) {
			self::assertNull($dupMargin->getParentRule());
			self::assertNull($pageStyle->getParentRule());
		}

		$vtDecl = new CSSStyleDeclaration();
		$vtDecl->setProperty('types', '/* comment */ none');
		self::assertSame([], (new CSSViewTransitionRule($vtDecl))->getTypes());
		$vtDecl->setProperty('types', str_repeat('a ', CSSStyleValue::MAX_LIST_ITEMS + 2) . 'b');
		try {
			(new CSSViewTransitionRule($vtDecl))->getTypes();
			self::fail('Expected CSSViewTransitionRule::getTypes() token cap to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$propDecl = new CSSStyleDeclaration();
		$propDecl->setProperty('syntax', '/* c */ "\'<length>\'"');
		$propDecl->setProperty('inherits', '/* c */ true');
		$propRule = new CSSPropertyRule('--x-commented', $propDecl);
		self::assertSame('\'<length>\'', $propRule->getSyntax());
		self::assertTrue($propRule->getInherits());
		$propRule->style->setProperty('syntax', '/* c */ "<length> | auto"');
		$propRule->style->setProperty('initial-value', '0px');
		self::assertSame('<length> | auto', $propRule->getSyntax());
		CSS::clearRegisteredProperties();
		CSS::registerProperty($propRule);
		self::assertTrue(CSS::getRegisteredProperty('--x-commented')?->getInherits());
		CSS::clearRegisteredProperties();

		self::assertTrue(CSS::supports('(--a: ' . str_repeat('()', 65_535) . ')'));
		self::assertFalse(CSS::supports('(--a: ' . str_repeat('()', 65_536) . ')'));

		$colorVal = new CSSColor('srgb', [0.5, 0.5, 0.5]);
		try {
			$colorVal->setColorSpace(str_repeat('a', CSSParserOptions::DEFAULT_MAX_BYTES + 1));
			self::fail('Expected CSSColor::setColorSpace() byte limit to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('--custom', '/* ok */'));
		foreach ([
			static fn () => CSSStyleValue::parse('color', '/* c */'),
			static fn () => CSSStyleValue::parse('/* c */'),
			static fn () => CSSStyleValue::parseAll('color', '/* a */, /* b */'),
			static fn () => new CSSSupportsRule(''),
			static fn () => new CSSSupportsRule('/* c */'),
			static fn () => new CSSContainerRule(''),
			static fn () => new CSSContainerRule('/* c */'),
			static fn () => new CSSScopeRule('()'),
			static fn () => new CSSScopeRule('(/* a */) to (/* b */)'),
			static fn () => new CSSScopeRule('(.a) to (/* b */)'),
			static fn () => CSSFunctionRule::fromPrelude('--f(--x: /* c */)'),
			static fn () => new CSSFunctionRule('--f', '', [], '/* c */'),
			static fn () => new CSSFunctionRule('--f', '', [['name' => '--x', 'type' => '/* c */', 'defaultValue' => null]], '*'),
			static fn () => new CSSFunctionRule('--f', '', [['name' => '--x', 'type' => '*', 'defaultValue' => '']], '*'),
			static fn () => new CSSFunctionRule('--f', '', [['name' => '--x', 'type' => '*', 'defaultValue' => '   ']], '*'),
			static fn () => new CSSFunctionRule('--f', '', [['name' => '--x', 'type' => '*', 'defaultValue' => '/* c */']], '*'),
		] as $badEmptyCall) {
			try {
				$badEmptyCall();
				self::fail('Expected empty/comment-only call to throw InvalidArgumentException.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$bareCommentScope = new CSSScopeRule('/* c */');
		self::assertNull($bareCommentScope->start);
		self::assertNull($bareCommentScope->end);

		$condSup = new CSSSupportsRule('(color: red)');
		$condCon = new CSSContainerRule('(width > 1px)');
		foreach ([$condSup, $condCon] as $condRule) {
			$prev = $condRule->conditionText;
			try {
				$condRule->setConditionText('/* c */');
				self::fail('Expected transactional setConditionText() to reject comment-only input.');
			} catch (InvalidArgumentException) {
				self::assertSame($prev, $condRule->conditionText);
			}
		}
	}
}
