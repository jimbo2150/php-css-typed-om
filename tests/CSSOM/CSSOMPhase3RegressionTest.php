<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandExpander;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandSerializer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use PHPUnit\Framework\TestCase;

final class CSSOMPhase3RegressionTest extends TestCase
{
	public function testStrictPreludeCountSynchronizedAcrossCloneInsertDeleteAndReplace(): void
	{
		$sheet = CSSStyleSheet::parse('@charset "UTF-8"; @import url("a.css"); @namespace svg "http://www.w3.org/2000/svg"; .a { color: red; }');
		$cloned = clone $sheet;

		// Cloned sheet must retain strict prelude tracking and reject rule before @charset
		try {
			$cloned->insertRule('.early { color: blue; }', 0);
			self::fail('Expected InvalidArgumentException when inserting before @charset in cloned sheet.');
		} catch (InvalidArgumentException) {
		}

		// Delete regular rule, then remove all 3 strict prelude rules from clone
		$cloned->deleteRule(3); // .a
		$cloned->deleteRule(2); // @namespace
		$cloned->deleteRule(1); // @import
		$cloned->getCssRules()->remove(0); // @charset

		// Now strictPreludeCount is 0 on clone: inserting a regular rule at 0 succeeds cleanly
		self::assertSame(0, $cloned->insertRule('.fresh { color: green; }', 0));

		// replaceAll transfers strict prelude rules out of $sourceSheet into $cloned
		$sourceSheet = CSSStyleSheet::parse('@import url("b.css"); .x { margin: 1px; }');
		$cloned->getCssRules()->replaceAll($sourceSheet->getCssRules());

		try {
			$cloned->insertRule('.before-import { color: red; }', 0);
			self::fail('Expected InvalidArgumentException after replaceAll transferred @import rule.');
		} catch (InvalidArgumentException) {
		}

		// Emptied source sheet has strictPreludeCount reset to 0 and accepts a regular rule
		self::assertCount(0, $sourceSheet->getCssRules());
		self::assertSame(0, $sourceSheet->insertRule('.after-empty { color: black; }', 0));
	}

	public function testMonotoneLogicalMasksPreserveCascadeOrderAfterRemoveProperty(): void
	{
		$style = CSSStyleDeclaration::parse('margin-top: 1px; margin-inline-start: 2px; margin-right: 3px; margin-bottom: 4px; margin-left: 5px;');

		// Opposite logical property sits in the middle, so getCssText() must not collapse `margin`
		self::assertStringNotContainsString('margin:', $style->getCssText());

		// Remove the logical property, then re-insert it and overwrite margin-top:
		// margin-top must be re-appended after margin-inline-start.
		self::assertSame('2px', $style->removeProperty('margin-inline-start'));
		$style->setProperty('margin-top', '10px');
		$style->setProperty('margin-inline-start', '6px');
		$style->setProperty('margin-top', '20px');

		$keys = [];
		foreach ($style as $prop => $_entry) {
			$keys[] = $prop;
		}
		self::assertGreaterThan(
			array_search('margin-inline-start', $keys, true),
			array_search('margin-top', $keys, true),
		);
	}

	public function testSerializeMaskForwardsCallerOptionsLimits(): void
	{
		$decl = new CSSStyleDeclaration('', new CSSParserOptions(maxDepth: 256));
		$decl->setProperty('mask-image', 'linear-gradient(rgb(1, 2, 3), transparent)');
		$decl->setProperty('mask-position', '0% 0%');
		$decl->setProperty('mask-size', 'auto');
		$decl->setProperty('mask-repeat', 'repeat');
		$decl->setProperty('mask-origin', 'border-box');
		$decl->setProperty('mask-clip', 'border-box');
		$decl->setProperty('mask-composite', 'add');
		$decl->setProperty('mask-mode', 'match-source');

		// Under loose depth=256, mask serializes cleanly
		self::assertSame(
			'linear-gradient(rgb(1, 2, 3), transparent)',
			CSSShorthandSerializer::serializeShorthand('mask', $decl->getDeclarationsRaw(), [], CSSParserOptions::default()),
		);

		// Forwarded strict maxDepth=1 must enforce depth limit on nested rgb() inside linear-gradient()
		$this->expectException(CSSParserLimitException::class);
		CSSShorthandSerializer::serializeShorthand('mask', $decl->getDeclarationsRaw(), [], new CSSParserOptions(maxDepth: 1));
	}

	public function testGuardB2FlatAsciiExpandParityForOutlineBorderBlockInlineAndFlex(): void
	{
		// outline preserves ['outline-color', 'outline-style', 'outline-width'], default 'auto', and 'invert'
		$outlineSolid = CSSShorthandExpander::expand('outline', '2px solid red');
		self::assertNotNull($outlineSolid);
		self::assertSame('expanded', $outlineSolid['kind']);
		self::assertSame([
			'outline-color' => 'red',
			'outline-style' => 'solid',
			'outline-width' => '2px',
		], $outlineSolid['longhands']);

		$outlineDefaultColor = CSSShorthandExpander::expand('outline', 'solid 3px');
		self::assertNotNull($outlineDefaultColor);
		self::assertSame([
			'outline-color' => 'auto',
			'outline-style' => 'solid',
			'outline-width' => '3px',
		], $outlineDefaultColor['longhands']);

		$outlineInvert = CSSShorthandExpander::expand('outline', 'invert dashed 1px');
		self::assertNotNull($outlineInvert);
		self::assertSame([
			'outline-color' => 'invert',
			'outline-style' => 'dashed',
			'outline-width' => '1px',
		], $outlineInvert['longhands']);

		// border-block & border-inline expand cleanly to all 6 longhands; single side/column-rule to 3
		$borderBlock = CSSShorthandExpander::expand('border-block', '2px dashed navy');
		self::assertNotNull($borderBlock);
		self::assertSame([
			'border-block-start-width' => '2px',
			'border-block-start-style' => 'dashed',
			'border-block-start-color' => 'navy',
			'border-block-end-width' => '2px',
			'border-block-end-style' => 'dashed',
			'border-block-end-color' => 'navy',
		], $borderBlock['longhands']);

		$borderInline = CSSShorthandExpander::expand('border-inline', 'thin solid #123456');
		self::assertNotNull($borderInline);
		self::assertSame([
			'border-inline-start-width' => 'thin',
			'border-inline-start-style' => 'solid',
			'border-inline-start-color' => '#123456',
			'border-inline-end-width' => 'thin',
			'border-inline-end-style' => 'solid',
			'border-inline-end-color' => '#123456',
		], $borderInline['longhands']);

		$colRule = CSSShorthandExpander::expand('column-rule', '1px dotted green');
		self::assertNotNull($colRule);
		self::assertSame([
			'column-rule-width' => '1px',
			'column-rule-style' => 'dotted',
			'column-rule-color' => 'green',
		], $colRule['longhands']);

		// flex Flexbox §7.1.1 flat ASCII cases
		$flexCases = [
			'none' => ['flex-grow' => '0', 'flex-shrink' => '0', 'flex-basis' => 'auto'],
			'auto' => ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => 'auto'],
			'0' => ['flex-grow' => '0', 'flex-shrink' => '1', 'flex-basis' => '0%'],
			'1' => ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => '0%'],
			'0px' => ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => '0px'],
			'20px' => ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => '20px'],
			'10px 1' => ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => '10px'],
			'1 2 10px' => ['flex-grow' => '1', 'flex-shrink' => '2', 'flex-basis' => '10px'],
			'10px 1 2' => ['flex-grow' => '1', 'flex-shrink' => '2', 'flex-basis' => '10px'],
		];
		foreach ($flexCases as $input => $expected) {
			$inputStr = (string) $input;
			$res = CSSShorthandExpander::expand('flex', $inputStr);
			self::assertNotNull($res, "flex: {$inputStr}");
			self::assertSame($expected, $res['longhands'], "flex: {$inputStr}");

			$decl = CSSStyleDeclaration::parse("flex: {$inputStr}");
			foreach ($expected as $lh => $val) {
				self::assertSame($val, $decl->getPropertyValue($lh), "decl flex: {$inputStr} -> {$lh}");
			}
		}
	}

	public function testCommaLayersWithSubstitutionsDoNotCollapseAndFlexBasisEscapesStaySafe(): void
	{
		$transitionDecl = CSSStyleDeclaration::parse(
			'transition-property: opacity; transition-duration: var(--d); transition-timing-function: ease; transition-delay: 0s;',
		);
		self::assertSame('', $transitionDecl->getPropertyValue('transition'));
		self::assertStringContainsString('transition-duration: var(--d)', $transitionDecl->getCssText());

		$animationDecl = CSSStyleDeclaration::parse(
			'animation-name: fade; animation-duration: env(--custom, 1s); animation-timing-function: ease; animation-delay: 0s; animation-iteration-count: 1; animation-direction: normal; animation-fill-mode: none; animation-play-state: running;',
		);
		self::assertSame('', $animationDecl->getPropertyValue('animation'));

		$serializedFlex = CSSShorthandSerializer::serializeDeclarationBlock([
			'flex-grow' => ['value' => '2', 'priority' => ''],
			'flex-shrink' => ['value' => '1', 'priority' => ''],
			'flex-basis' => ['value' => '/*c*/ 0', 'priority' => ''],
		], [], true, CSSParserOptions::default());
		self::assertSame('2 1 /*c*/ 0', $serializedFlex[0]['value'] ?? '');

		self::assertNull(CSSSyntaxScanner::skipWhitespaceAndComments('/* a */', 0, 6));
		self::assertSame(7, CSSSyntaxScanner::skipWhitespaceAndComments('/* a */', 0, 7));

		// Paren-only fast path and upfront control-byte precedence in assertValidFragment()
		CSSSyntaxScanner::assertValidFragment('1px solid rgba(10, 20, 30, 0.5)', 'val');
		CSSSyntaxScanner::assertValidFragment('url(foo)', 'val');
		foreach ([
			["a;b\x00", 'disallowed control characters'],
			['rgba(10, 20', 'unbalanced syntax'],
			['rgba(10))', 'unbalanced syntax'],
			['url(foo bar)', 'unbalanced syntax'],
		] as [$fragment, $expectedMessage]) {
			try {
				CSSSyntaxScanner::assertValidFragment($fragment, 'val');
				self::fail('Expected InvalidArgumentException for fragment.');
			} catch (InvalidArgumentException $e) {
				self::assertStringContainsString($expectedMessage, $e->getMessage());
			}
		}
	}
}
