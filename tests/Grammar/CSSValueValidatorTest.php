<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStandardProperties;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarNodeType;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use PHPUnit\Framework\TestCase;

final class CSSValueValidatorTest extends TestCase
{
	public function testFullRegistryCompilationAndNonTerminalIntegrity(): void
	{
		$properties = CSSPropertyGrammarRegistry::allProperties();
		self::assertGreaterThanOrEqual(460, count($properties));

		$queue = [];
		foreach ($properties as $prop) {
			self::assertTrue(CSSStandardProperties::isKnown($prop));
			$ast = CSSPropertyGrammarRegistry::getPropertyGrammar($prop);
			self::assertNotNull($ast, "Failed to compile grammar for {$prop}");
			$queue[] = $ast;
		}
		foreach (CSSPropertyGrammarRegistry::allNonTerminals() as $nt) {
			$ast = CSSPropertyGrammarRegistry::resolveNonTerminal($nt);
			self::assertNotNull($ast, "Failed to compile non-terminal <{$nt}>");
			$queue[] = $ast;
		}

		while ($queue !== []) {
			$node = array_pop($queue);
			if ($node->type === CSSGrammarNodeType::NonTerminal) {
				self::assertNotNull(
					CSSPropertyGrammarRegistry::resolveNonTerminal((string) $node->value),
					sprintf('Unresolved non-terminal reference "%s".', (string) $node->value),
				);
			}
			foreach ($node->children as $child) {
				$queue[] = $child;
			}
		}
	}

	public function testStandardPropertiesPositiveAndNegativeAcrossModules(): void
	{
		$validCases = [
			['width', '10px'],
			['width', 'calc(10% + 5px)'],
			['width', 'fit-content(20rem)'],
			['border-width', 'thin 2px 0'],
			['border-radius', '10px 20px / 5px'],
			['color', 'rebeccapurple'],
			['color', 'CanvasText'],
			['color', 'oklch(0.6 0.15 30 / 50%)'],
			['background', 'url("a.png") no-repeat center / cover, linear-gradient(45deg, red 10%, blue 90%) #fff'],
			['display', 'inline flex'],
			['display', 'flow-root list-item'],
			['font', 'italic bold 16px/1.5 "Inter", sans-serif'],
			['font-style', 'oblique 0.1turn'],
			['grid-template-columns', '[header-start] 1fr minmax(100px, auto) repeat(2, [col] 1fr) [end]'],
			['grid-template-rows', 'repeat(auto-fill, minmax(10px, 1fr))'],
			['box-shadow', '0 4px 6px rgba(0, 0, 0, 0.1), inset 0 1px 0 #fff'],
			['transform', 'translateX(10px) rotate(45deg) scale(1.2)'],
			['transition', 'opacity 200ms ease-in 50ms allow-discrete'],
			['animation', '1s cubic-bezier(0.2, 0, 0, 1) 0s infinite alternate forwards running fadeIn'],
			['filter', 'blur(4px) drop-shadow(2px 4px 6px black)'],
			['clip-path', 'circle(50% at 25% 25%)'],
			['aspect-ratio', '16 / 9'],
			['z-index', 'calc(1 + 2)'],
			['content', 'url(a.png) / "alt"'],
			['speak-as', 'bullets'],
			['speak-as', 'spell-out digits literal-punctuation'],
			['anchor-name', '--trigger, --menu'],
			['position-try-fallbacks', '--custom-pos flip-block'],
			['scroll-timeline', '--scroller block'],
			['view-transition-name', 'hero-card'],
			['fill', 'url(#grad) red'],
			['cursor', 'url(a.cur), pointer'],
		];

		foreach ($validCases as [$prop, $val]) {
			self::assertTrue(CSSValueValidator::validateStandardProperty($prop, $val), "Expected {$prop}: {$val} to be valid.");
			self::assertTrue(CSS::supports($prop, $val), "Expected CSS::supports('{$prop}', '{$val}') to be true.");
		}

		$invalidCases = [
			['width', 'purple'],
			['width', '-10px'],
			['width', 'calc(0)'],
			['width', '10deg'],
			['display', '12px'],
			['display', 'banana'],
			['border-width', 'calc(10% + 5px)'],
			['font-style', 'oblique 0.5turn'],
			['font-style', 'oblique 120deg'],
			['font-weight', '0'],
			['font-weight', '1001'],
			['color', '10px'],
			['color', 'red, blue'],
			['all', 'red'],
			['aspect-ratio', '-1 / 2'],
			['opacity', '10px'],
			['z-index', '1.5'],
			['z-index', '10px'],
			['background-image', 'linear-gradient(red)'],
			['background-image', 'linear-gradient(to right, red)'],
			['grid-template-columns', '[10px] 1fr'],
			['view-transition-name', 'auto'],
			['animation-duration', '-1s'],
		];

		foreach ($invalidCases as [$prop, $val]) {
			self::assertFalse(CSSValueValidator::validateStandardProperty($prop, $val), "Expected {$prop}: {$val} to be invalid.");
			self::assertFalse(CSS::supports($prop, $val), "Expected CSS::supports('{$prop}', '{$val}') to be false.");
		}
	}

	public function testCssWideKeywordsSubstitutionsAndEmptyVarFallback(): void
	{
		foreach (['initial', 'inherit', 'unset', 'revert', 'revert-layer', 'INHERIT'] as $kw) {
			self::assertTrue(CSS::supports('width', $kw));
			self::assertTrue(CSS::supports('all', $kw));
		}

		foreach ([
			'var(--x)',
			'var(--x, )',
			'var(--x, red)',
			'var(--x, var(--y, 10px))',
			'env(safe-area-inset-top, 0px)',
			'calc(var(--gap) + 10px)',
			'attr(data-size px, 10px)',
			'all: var(--a)',
		] as $entry) {
			if (str_starts_with($entry, 'all: ')) {
				self::assertTrue(CSS::supports('all', 'var(--a)'));
				continue;
			}
			self::assertTrue(CSS::supports('width', $entry), "Expected width: {$entry} to pass.");
		}

		foreach ([
			'var()',
			'var(--)',
			'var(foo)',
			'var(--a, var())',
			'var(--a, var(bar))',
			'env()',
		] as $badSub) {
			self::assertFalse(CSS::supports('width', $badSub), "Expected width: {$badSub} to be rejected.");
		}
	}

	public function testTypedOmAndCssomEntryPointIntegrationAndDescriptorExemptions(): void
	{
		// Multi-arg StylePropertyMap::set() atomic shorthand & operator validation
		$decl = new CSSStyleDeclaration();
		$map = new StylePropertyMap($decl);
		$map->set('font', 'italic', '16px', 'sans-serif');
		self::assertSame('italic 16px sans-serif', $decl->getPropertyValue('font'));
		$map->set('border-radius', '10px', '/', '20px');
		self::assertSame('10px / 20px', $decl->getPropertyValue('border-radius'));

		// CSSStyleValue::parseAll() with cursor fallback list
		$cursorItems = CSSStyleValue::parseAll('cursor', 'url(a.cur), pointer');
		self::assertCount(2, $cursorItems);

		// Lenient CSSStyleSheet drops invalid standard declarations as parse errors, keeps valid + custom + vendor
		$sheet = CSSStyleSheet::parse('.box { width: purple; -webkit-box-flex: 1; --custom: anything; color: rebeccapurple; }');
		self::assertCount(1, $sheet->cssRules);
		$box = $sheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $box);
		self::assertSame('', $box->style->getPropertyValue('width'));
		self::assertSame('1', $box->style->getPropertyValue('-webkit-box-flex'));
		self::assertSame('anything', $box->style->getPropertyValue('--custom'));
		self::assertSame('rebeccapurple', $box->style->getPropertyValue('color'));
		self::assertNotEmpty($sheet->getParseErrors());

		// Unknown property rejected in CSS::supports()
		self::assertFalse(CSS::supports('-webkit-unknown-prop', '1'));
		self::assertTrue(CSS::supports('--anything', '123 purple'));

		// Standalone speak-as accepts @counter-style values before attachment to CSSCounterStyleRule
		$csDecl = new CSSStyleDeclaration('speak-as: bullets;');
		$csRule = new CSSCounterStyleRule('thumbs', $csDecl);
		self::assertSame('bullets', $csRule->style->getPropertyValue('speak-as'));

		// Descriptor setCssText() preserves exemption on @font-face and @property
		$ffSheet = CSSStyleSheet::parse('@font-face { font-family: "Demo"; src: url(a.woff2); font-weight: 100 900; }');
		self::assertSame([], $ffSheet->getParseErrors());
		$ffRule = $ffSheet->cssRules[0];
		self::assertInstanceOf(CSSFontFaceRule::class, $ffRule);
		$ffRule->style->setCssText('font-family: "Demo"; src: url(b.woff2); font-weight: 200 700;');
		self::assertSame('200 700', $ffRule->style->getPropertyValue('font-weight'));

		$propSheet = CSSStyleSheet::parse('@property --accent { syntax: "<color>"; inherits: false; initial-value: red; }');
		$propRule = $propSheet->cssRules[0];
		self::assertInstanceOf(CSSPropertyRule::class, $propRule);
		$propRule->style->setCssText('syntax: "<length>"; inherits: true; initial-value: 8px;');
		self::assertSame('8px', $propRule->initialValue);
	}

	public function testRegisterPropertyInitialValueComputationalIndependence(): void
	{
		CSS::clearRegisteredProperties();
		try {
			CSS::registerProperty([
				'name' => '--len',
				'syntax' => '<length>',
				'inherits' => false,
				'initialValue' => 'calc(10px + 20px)',
			]);
			CSS::registerProperty([
				'name' => '--kw',
				'syntax' => 'auto | <length>',
				'inherits' => false,
				'initialValue' => 'auto',
			]);

			self::assertInstanceOf(CSSStyleValue::class, CSSStyleValue::parse('--len', '10em'));
			try {
				CSSStyleValue::parse('--len', 'red');
				self::fail('Expected invalid value for registered <length> property to throw.');
			} catch (InvalidArgumentException) {
			}

			foreach ([
				['--bad-em', '<length>', '1em'],
				['--bad-vw', '<length>', 'calc(10px + 2vw)'],
				['--bad-inherit', '<color>', 'inherit'],
				['--bad-type', '<color>', '10px'],
				['--bad-kw', 'auto | none', 'purple'],
			] as [$name, $syntax, $initial]) {
				try {
					CSS::registerProperty([
						'name' => $name,
						'syntax' => $syntax,
						'inherits' => false,
						'initialValue' => $initial,
					]);
					self::fail("Expected registerProperty({$name}, {$syntax}, {$initial}) to throw.");
				} catch (InvalidArgumentException) {
				}
			}
		} finally {
			CSS::clearRegisteredProperties();
		}
	}
}
