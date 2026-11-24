<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Security;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesMap;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
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

		$cappedTransforms = new CSSTransformValue([new CSSTranslate(1, 2)]);
		try {
			$cappedTransforms->offsetSet(1, new CSSTranslate(str_repeat('1', CSSParserOptions::DEFAULT_MAX_BYTES + 1) . 'px'));
			self::fail('Expected oversized CSSTransformValue entry to throw.');
		} catch (CSSParserLimitException|InvalidArgumentException $e) {
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
			CSSStyleDeclaration::parse(
				implode('; ', array_map(static fn (int $i): string => "--d{$i}: {$i}px", range(1, 5))),
				true,
				$dDiags,
				new CSSParserOptions(maxDeclarations: 3),
			);
			self::fail('Expected strict CSSStyleDeclaration::parse() maxDeclarations to throw.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
		self::assertSame('', $detachedDecl->getCssText());
	}
}
