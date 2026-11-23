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
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
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
		} catch (CSSParserLimitException) {
			self::assertSame($before, $base->cssText);
		}

		try {
			$base->replaceSync('b { color: blue }', new CSSParserOptions(maxBytes: 5));
			self::fail('Expected strict replaceSync to reject over-limit stylesheet.');
		} catch (CSSParserLimitException) {
			self::assertSame($before, $base->cssText);
		}

		try {
			CSSNumericValue::parse('min(1px, min(2px, min(3px, 4px)))', null, new CSSParserOptions(maxMathDepth: 2));
			self::fail('Expected excessive math depth to throw.');
		} catch (CSSParserLimitException) {
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

	public function testPreventsUnquotedUrlBreakoutHtmlStyleTagEscapeAndCycles(): void
	{
		// SEC-H1: escaped \75 rl(...) is recognized as unquoted URL and <bad-url-token> rejects quotes
		self::assertFalse(CSSSyntaxScanner::isBalanced('\75 rl(https://example.test/a?q="quoted")'));
		self::assertTrue(CSSSyntaxScanner::isBalanced('\75 rl(https://example.test/a?b=1)'));
		self::assertFalse(CSSSyntaxScanner::isBalanced('url(a)b)'));

		// SEC-M1: over-depth known at-rule is dropped cleanly, never retained as opaque rule
		$deepKnown = CSSStyleSheet::parse('@media a { @media b { @media c { p { color: red } } } }', new CSSParserOptions(maxDepth: 2));
		self::assertNotEmpty($deepKnown->getParseErrors());
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
			@ini_set('xdebug.max_nesting_level', '4096');
		}
		$sheet = new CSSStyleSheet();
		$sheet->getCssRules()->append($root);
		try {
			CSSRenderer::render($root);
			self::fail('Expected CSSParserLimitException for deep render.');
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		} finally {
			if ($prevNesting !== false) {
				@ini_set('xdebug.max_nesting_level', $prevNesting);
			}
		}

		// StyleSheet metadata control bytes (tested via CSSStyleSheet)
		try {
			new CSSStyleSheet(href: "https://example.test/\x00a.css", title: 'title');
			self::fail('Expected control character rejection in StyleSheet.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		// DOMMatrixReadOnly transformPoint finite output check
		$big = new DOMMatrixReadOnly([1e308, 0, 0, 1e308, 0, 0]);
		try {
			$big->transformPoint(['x' => 1e10]);
			self::fail('Expected non-finite transformed point to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
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
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}
		try {
			$map->set('color', 'red; background: blue');
			self::fail('Expected InvalidArgumentException on injected declaration value.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		// Nested calc(min(...)) respects maxMathDepth
		try {
			CSSNumericValue::parse('calc(min(1px, min(2px, min(3px, min(4px, 5px)))))', null, $tight);
			self::fail('Expected CSSParserLimitException on deep nested math functions.');
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
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
			} catch (CSSParserLimitException) {
				self::assertTrue(true);
			}
		}

		// CSSUnparsedValue per-fragment control chars & circular fallback cycle
		try {
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue(["\x00"]);
			self::fail('Expected control char rejection in CSSUnparsedValue.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		$ref = new CSSVariableReferenceValue('--self', 'a');
		$fallback = $ref->fallback;
		try {
			$fallback?->offsetSet(0, $ref);
			self::fail('Expected cycle rejection in CSSUnparsedValue.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
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
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}

		// Skew setIs2D(false) & Perspective setIs2D(true) & non-finite matrix rejections
		try {
			(new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX(CSS::deg(45)))->setIs2D(false);
			self::fail('Expected CSSSkewX setIs2D(false) to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}
		try {
			(new CSSPerspective(200))->setIs2D(true);
			self::fail('Expected CSSPerspective setIs2D(true) to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}
		try {
			(new CSSPerspective(1e-310))->toMatrix();
			self::fail('Expected subnormal perspective overflow to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}
		$skew90Matrix = (new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX(CSS::deg(90)))->toMatrix();
		self::assertTrue(is_finite($skew90Matrix[4])); // tan(deg2rad(90)) is large finite float (~1.63e16)
		try {
			(new CSSRotate(CSS::turn(1e308)))->toMatrix();
			self::fail('Expected angle multiplication overflow in scalar() to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
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
		} catch (CSSParserLimitException) {
			self::assertSame($atomicBefore, $atomicTarget->cssText);
		}

		// 2. Escaped URL differential (\ url(...) and 40-byte hex-escaped ident) rejected cleanly
		foreach ([
			'\ url(/*) "*/); } .injected { color: red } a { x: ("',
			"\\000061\\r\\n\\000075\\r\\n\\000072\\r\\n\\00006c\\r\\n(/*) \"*/); } .injected { color: red } a { x: (\"",
		] as $payload) {
			try {
				CSSSyntaxScanner::assertValidFragment($payload, 'declaration value', [';', '{', '}']);
				self::fail('Expected structural injection payload to be rejected.');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
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
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
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
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
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
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}
		try {
			new CSSVariableReferenceValue('--a', 'var(--b, var(--c, 1px))', new CSSParserOptions(maxDepth: 1));
			self::fail('Expected CSSParserLimitException for CSSVariableReferenceValue depth.');
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}

		// 5. Iterative deep attach / reparent (>512 deep without recursion overflow) and getCssText() depth limit
		$deepRoot = new CSSMediaRule('all');
		$cursor = $deepRoot;
		for ($i = 0; $i < 600; $i++) {
			$child = new CSSMediaRule('all');
			$cursor->getCssRules()->appendDetached($child);
			$cursor = $child;
		}
		$deepSheet = new CSSStyleSheet();
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
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}
		try {
			\Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer::render($deepSheet);
			self::fail('Expected CSSParserLimitException on deep CSSRenderer::render().');
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
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
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
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
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		foreach ([
			static fn () => \Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser::parse('calc((((10px))))', new CSSParserOptions(maxMathDepth: 2)),
			static fn () => \Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser::parse('calc(-----10px)', new CSSParserOptions(maxMathDepth: 3)),
			static fn () => CSSNumericValue::parse('min(' . implode(',', array_fill(0, 1025, '1px')) . ')', null, new CSSParserOptions(maxMathDepth: 4)),
		] as $mathLimitCall) {
			try {
				$mathLimitCall();
				self::fail('Expected math limit CSSParserLimitException.');
			} catch (CSSParserLimitException) {
				self::assertTrue(true);
			}
		}

		// StylePropertyMap append maxBytes
		$appendMap = new StylePropertyMap(new CSSStyleDeclaration());
		$appendMap->set('--a', str_repeat('x', 9_000_000));
		try {
			$appendMap->append('--a', str_repeat('y', 9_000_000));
			self::fail('Expected CSSParserLimitException on StylePropertyMap::append exceeding 16 MiB.');
		} catch (CSSParserLimitException) {
			self::assertTrue(true);
		}

		// CSSContext extreme fontSize overflow & underflow guards
		foreach ([
			[PHP_FLOAT_MAX, 'lh'],
			[1e-320, 'ex'],
		] as [$extremeFont, $unit]) {
			try {
				(new CSSContext($extremeFont))->getToPxFactor($unit);
				self::fail('Expected extreme fontSize overflow/underflow in CSSContext::getToPxFactor().');
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
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
			} catch (CSSParserLimitException) {
				self::assertTrue(true);
			}
		}

		// CSSUnparsedValue handles var(/* c */ --foo /* c2 */, fallback) and large O(N) nested parens
		$unparsedCommentVar = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText('var(/* c */ --foo /* c2 */, red)');
		self::assertCount(1, $unparsedCommentVar);
		self::assertSame('--foo', $unparsedCommentVar[0]->variable);
		$deepParens = str_repeat('(', 5000) . '1' . str_repeat(')', 5000);
		$unparsedLinear = \Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue::fromCssText($deepParens, CSSParserOptions::unlimited());
		self::assertSame($deepParens, (string) $unparsedLinear);
	}
}
