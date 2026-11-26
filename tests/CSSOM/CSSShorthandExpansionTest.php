<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderer;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleProperties;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand\CSSShorthandRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CSSShorthandExpansionTest extends TestCase
{
	/**
	 * @return array<string, array{string, string, string, array<string, string>}>
	 */
	public static function provideShorthandRoundTripCases(): array
	{
		return [
			'margin 1-val' => ['margin', '10px', '10px', ['margin-top' => '10px', 'margin-right' => '10px', 'margin-bottom' => '10px', 'margin-left' => '10px']],
			'margin 2-val' => ['margin', '10px 20px', '10px 20px', ['margin-top' => '10px', 'margin-right' => '20px', 'margin-bottom' => '10px', 'margin-left' => '20px']],
			'margin 3-val' => ['margin', '10px 20px 30px', '10px 20px 30px', ['margin-top' => '10px', 'margin-right' => '20px', 'margin-bottom' => '30px', 'margin-left' => '20px']],
			'margin 4-val collapsible' => ['margin', '10px 20px 10px 20px', '10px 20px', ['margin-top' => '10px', 'margin-right' => '20px', 'margin-bottom' => '10px', 'margin-left' => '20px']],
			'margin 4-val distinct' => ['margin', '1px 2px 3px 4px', '1px 2px 3px 4px', ['margin-top' => '1px', 'margin-right' => '2px', 'margin-bottom' => '3px', 'margin-left' => '4px']],
			'padding' => ['padding', '8px 16px', '8px 16px', ['padding-top' => '8px', 'padding-right' => '16px', 'padding-bottom' => '8px', 'padding-left' => '16px']],
			'inset' => ['inset', '0 auto 10px', '0 auto 10px', ['top' => '0', 'right' => 'auto', 'bottom' => '10px', 'left' => 'auto']],
			'border-width' => ['border-width', 'thin 2px', 'thin 2px', ['border-top-width' => 'thin', 'border-right-width' => '2px', 'border-bottom-width' => 'thin', 'border-left-width' => '2px']],
			'border-style' => ['border-style', 'solid dashed dotted', 'solid dashed dotted', ['border-top-style' => 'solid', 'border-right-style' => 'dashed', 'border-bottom-style' => 'dotted', 'border-left-style' => 'dashed']],
			'border-color' => ['border-color', 'red blue green yellow', 'red blue green yellow', ['border-top-color' => 'red', 'border-right-color' => 'blue', 'border-bottom-color' => 'green', 'border-left-color' => 'yellow']],
			'scroll-margin' => ['scroll-margin', '4px 8px', '4px 8px', ['scroll-margin-top' => '4px', 'scroll-margin-right' => '8px', 'scroll-margin-bottom' => '4px', 'scroll-margin-left' => '8px']],
			'scroll-padding' => ['scroll-padding', 'auto 12px', 'auto 12px', ['scroll-padding-top' => 'auto', 'scroll-padding-right' => '12px', 'scroll-padding-bottom' => 'auto', 'scroll-padding-left' => '12px']],

			'margin-block' => ['margin-block', '1rem 2rem', '1rem 2rem', ['margin-block-start' => '1rem', 'margin-block-end' => '2rem']],
			'margin-inline' => ['margin-inline', 'auto', 'auto', ['margin-inline-start' => 'auto', 'margin-inline-end' => 'auto']],
			'padding-block' => ['padding-block', '4px 4px', '4px', ['padding-block-start' => '4px', 'padding-block-end' => '4px']],
			'padding-inline' => ['padding-inline', '10px 20px', '10px 20px', ['padding-inline-start' => '10px', 'padding-inline-end' => '20px']],
			'inset-block' => ['inset-block', '0 10%', '0 10%', ['inset-block-start' => '0', 'inset-block-end' => '10%']],
			'inset-inline' => ['inset-inline', '5px', '5px', ['inset-inline-start' => '5px', 'inset-inline-end' => '5px']],
			'border-block-width' => ['border-block-width', '1px 2px', '1px 2px', ['border-block-start-width' => '1px', 'border-block-end-width' => '2px']],
			'border-block-style' => ['border-block-style', 'solid', 'solid', ['border-block-start-style' => 'solid', 'border-block-end-style' => 'solid']],
			'border-block-color' => ['border-block-color', 'red blue', 'red blue', ['border-block-start-color' => 'red', 'border-block-end-color' => 'blue']],
			'border-inline-width' => ['border-inline-width', 'thick', 'thick', ['border-inline-start-width' => 'thick', 'border-inline-end-width' => 'thick']],
			'border-inline-style' => ['border-inline-style', 'dotted solid', 'dotted solid', ['border-inline-start-style' => 'dotted', 'border-inline-end-style' => 'solid']],
			'border-inline-color' => ['border-inline-color', 'navy', 'navy', ['border-inline-start-color' => 'navy', 'border-inline-end-color' => 'navy']],
			'scroll-margin-block' => ['scroll-margin-block', '10px', '10px', ['scroll-margin-block-start' => '10px', 'scroll-margin-block-end' => '10px']],
			'scroll-margin-inline' => ['scroll-margin-inline', '10px 20px', '10px 20px', ['scroll-margin-inline-start' => '10px', 'scroll-margin-inline-end' => '20px']],
			'scroll-padding-block' => ['scroll-padding-block', 'auto', 'auto', ['scroll-padding-block-start' => 'auto', 'scroll-padding-block-end' => 'auto']],
			'scroll-padding-inline' => ['scroll-padding-inline', '0 8px', '0 8px', ['scroll-padding-inline-start' => '0', 'scroll-padding-inline-end' => '8px']],
			'gap' => ['gap', '1rem 2rem', '1rem 2rem', ['row-gap' => '1rem', 'column-gap' => '2rem']],
			'gap single' => ['gap', '12px', '12px', ['row-gap' => '12px', 'column-gap' => '12px']],
			'overflow' => ['overflow', 'hidden auto', 'hidden auto', ['overflow-x' => 'hidden', 'overflow-y' => 'auto']],
			'overscroll-behavior' => ['overscroll-behavior', 'contain', 'contain', ['overscroll-behavior-x' => 'contain', 'overscroll-behavior-y' => 'contain']],
			'contain-intrinsic-size' => ['contain-intrinsic-size', 'auto 300px 200px', 'auto 300px 200px', ['contain-intrinsic-width' => 'auto 300px', 'contain-intrinsic-height' => '200px']],
			'place-content' => ['place-content', 'center space-between', 'center space-between', ['align-content' => 'center', 'justify-content' => 'space-between']],
			'place-content baseline' => ['place-content', 'first baseline', 'first baseline', ['align-content' => 'first baseline', 'justify-content' => 'start']],
			'place-items' => ['place-items', 'safe center', 'safe center', ['align-items' => 'safe center', 'justify-items' => 'safe center']],
			'place-self' => ['place-self', 'start end', 'start end', ['align-self' => 'start', 'justify-self' => 'end']],

			'border' => ['border', '1px solid #cbd5e1', '1px solid #cbd5e1', ['border-top-width' => '1px', 'border-top-style' => 'solid', 'border-top-color' => '#cbd5e1', 'border-image-source' => 'none', 'border-image-slice' => '100%', 'border-image-width' => '1', 'border-image-outset' => '0', 'border-image-repeat' => 'stretch']],
			'border none' => ['border', 'none', 'none', ['border-top-width' => 'medium', 'border-top-style' => 'none', 'border-top-color' => 'currentcolor', 'border-image-source' => 'none']],
			'border-top' => ['border-top', '2px dashed red', '2px dashed red', ['border-top-width' => '2px', 'border-top-style' => 'dashed', 'border-top-color' => 'red']],
			'border-right' => ['border-right', 'solid', 'solid', ['border-right-width' => 'medium', 'border-right-style' => 'solid', 'border-right-color' => 'currentcolor']],
			'border-bottom' => ['border-bottom', 'thick', 'thick', ['border-bottom-width' => 'thick', 'border-bottom-style' => 'none', 'border-bottom-color' => 'currentcolor']],
			'border-left' => ['border-left', 'rebeccapurple', 'rebeccapurple', ['border-left-width' => 'medium', 'border-left-style' => 'none', 'border-left-color' => 'rebeccapurple']],
			'border-block-start' => ['border-block-start', '1px solid blue', '1px solid blue', ['border-block-start-width' => '1px', 'border-block-start-style' => 'solid', 'border-block-start-color' => 'blue']],
			'border-block-end' => ['border-block-end', '2px dotted', '2px dotted', ['border-block-end-width' => '2px', 'border-block-end-style' => 'dotted', 'border-block-end-color' => 'currentcolor']],
			'border-inline-start' => ['border-inline-start', 'solid green', 'solid green', ['border-inline-start-width' => 'medium', 'border-inline-start-style' => 'solid', 'border-inline-start-color' => 'green']],
			'border-inline-end' => ['border-inline-end', 'none', 'none', ['border-inline-end-width' => 'medium', 'border-inline-end-style' => 'none', 'border-inline-end-color' => 'currentcolor']],
			'border-block' => ['border-block', '2px solid navy', '2px solid navy', ['border-block-start-width' => '2px', 'border-block-end-width' => '2px', 'border-block-start-style' => 'solid', 'border-block-end-color' => 'navy']],
			'border-inline' => ['border-inline', '1px dashed teal', '1px dashed teal', ['border-inline-start-width' => '1px', 'border-inline-end-style' => 'dashed', 'border-inline-end-color' => 'teal']],
			'outline' => ['outline', '2px solid invert', '2px solid invert', ['outline-width' => '2px', 'outline-style' => 'solid', 'outline-color' => 'invert']],
			'column-rule' => ['column-rule', '1px dotted #333', '1px dotted #333', ['column-rule-width' => '1px', 'column-rule-style' => 'dotted', 'column-rule-color' => '#333']],

			'border-radius' => ['border-radius', '10px 20px / 5px', '10px 20px / 5px', ['border-top-left-radius' => '10px 5px', 'border-top-right-radius' => '20px 5px', 'border-bottom-right-radius' => '10px 5px', 'border-bottom-left-radius' => '20px 5px']],
			'border-image' => ['border-image', 'url("b.png") 30 fill / 10px / 5px round', 'url("b.png") 30 fill / 10px / 5px round', ['border-image-source' => 'url("b.png")', 'border-image-slice' => '30 fill', 'border-image-width' => '10px', 'border-image-outset' => '5px', 'border-image-repeat' => 'round']],
			'mask-border' => ['mask-border', 'url("m.png") 20 / 8px / 2px space luminance', 'url("m.png") 20 / 8px / 2px space luminance', ['mask-border-source' => 'url("m.png")', 'mask-border-mode' => 'luminance', 'mask-border-slice' => '20', 'mask-border-width' => '8px', 'mask-border-outset' => '2px', 'mask-border-repeat' => 'space']],
			'mask-border none' => ['mask-border', 'none', 'none', ['mask-border-source' => 'none', 'mask-border-mode' => 'alpha', 'mask-border-slice' => '0', 'mask-border-width' => 'auto', 'mask-border-outset' => '0', 'mask-border-repeat' => 'stretch']],

			'background-position' => ['background-position', 'right 10px bottom 20px, top', 'right 10px bottom 20px, top', ['background-position-x' => 'right 10px, center', 'background-position-y' => 'bottom 20px, top']],
			'background' => ['background', 'url("a.png") no-repeat center / cover, linear-gradient(red, blue) #fff', 'url("a.png") center / cover no-repeat, linear-gradient(red, blue) #fff', ['background-image' => 'url("a.png"), linear-gradient(red, blue)', 'background-position-x' => 'center, 0%', 'background-position-y' => 'center, 0%', 'background-size' => 'cover, auto', 'background-repeat' => 'no-repeat, repeat', 'background-attachment' => 'scroll, scroll', 'background-origin' => 'padding-box, padding-box', 'background-clip' => 'border-box, border-box', 'background-color' => '#fff']],
			'background size zero pos' => ['background', '0% 0% / contain', '0% 0% / contain', ['background-size' => 'contain', 'background-position-x' => '0%', 'background-position-y' => '0%']],
			'mask' => ['mask', 'url("m.svg") 50% 50% / contain no-repeat padding-box no-clip intersect alpha', 'url("m.svg") 50% 50% / contain no-repeat padding-box no-clip intersect alpha', ['mask-image' => 'url("m.svg")', 'mask-position' => '50% 50%', 'mask-size' => 'contain', 'mask-repeat' => 'no-repeat', 'mask-origin' => 'padding-box', 'mask-clip' => 'no-clip', 'mask-composite' => 'intersect', 'mask-mode' => 'alpha']],

			'flex none' => ['flex', 'none', 'none', ['flex-grow' => '0', 'flex-shrink' => '0', 'flex-basis' => 'auto']],
			'flex auto' => ['flex', 'auto', 'auto', ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => 'auto']],
			'flex grow' => ['flex', '2', '2', ['flex-grow' => '2', 'flex-shrink' => '1', 'flex-basis' => '0%']],
			'flex initial' => ['flex', '0 1 auto', '0 1 auto', ['flex-grow' => '0', 'flex-shrink' => '1', 'flex-basis' => 'auto']],
			'flex-flow' => ['flex-flow', 'column wrap', 'column wrap', ['flex-direction' => 'column', 'flex-wrap' => 'wrap']],

			'font-synthesis' => ['font-synthesis', 'weight style', 'weight style', ['font-synthesis-weight' => 'auto', 'font-synthesis-style' => 'auto', 'font-synthesis-small-caps' => 'none', 'font-synthesis-position' => 'none']],
			'font-variant' => ['font-variant', 'small-caps tabular-nums', 'small-caps tabular-nums', ['font-variant-caps' => 'small-caps', 'font-variant-numeric' => 'tabular-nums', 'font-variant-ligatures' => 'normal']],
			'font' => ['font', 'italic bold 16px/1.5 "Inter", sans-serif', 'italic bold 16px/1.5 "Inter", sans-serif', ['font-style' => 'italic', 'font-weight' => 'bold', 'font-size' => '16px', 'line-height' => '1.5', 'font-family' => '"Inter", sans-serif', 'font-optical-sizing' => 'auto', 'font-size-adjust' => 'none', 'font-kerning' => 'auto', 'font-feature-settings' => 'normal', 'font-variation-settings' => 'normal', 'font-language-override' => 'normal']],

			'grid-row' => ['grid-row', 'header / span 2', 'header / span 2', ['grid-row-start' => 'header', 'grid-row-end' => 'span 2']],
			'grid-row bare ident' => ['grid-row', 'header', 'header', ['grid-row-start' => 'header', 'grid-row-end' => 'header']],
			'grid-column' => ['grid-column', '2 / -1', '2 / -1', ['grid-column-start' => '2', 'grid-column-end' => '-1']],
			'grid-area' => ['grid-area', 'main', 'main', ['grid-row-start' => 'main', 'grid-column-start' => 'main', 'grid-row-end' => 'main', 'grid-column-end' => 'main']],
			'grid-template areas' => ['grid-template', '[r1] "a b" 100px [r2] [r3] "c d" [r4] / 1fr 2fr', '[r1] "a b" 100px [r2 r3] "c d" [r4] / 1fr 2fr', ['grid-template-rows' => '[r1] 100px [r2 r3] auto [r4]', 'grid-template-columns' => '1fr 2fr', 'grid-template-areas' => '"a b" "c d"']],
			'grid auto-flow' => ['grid', '100px / auto-flow dense 200px', '100px / auto-flow dense 200px', ['grid-template-rows' => '100px', 'grid-template-columns' => 'none', 'grid-auto-flow' => 'column dense', 'grid-auto-rows' => 'auto', 'grid-auto-columns' => '200px']],

			'transition' => ['transition', 'opacity 200ms ease-in 50ms allow-discrete', 'opacity 200ms ease-in 50ms allow-discrete', ['transition-property' => 'opacity', 'transition-duration' => '200ms', 'transition-timing-function' => 'ease-in', 'transition-delay' => '50ms', 'transition-behavior' => 'allow-discrete']],
			'transition zero dur delay' => ['transition', 'opacity 0s 50ms', 'opacity 0s 50ms', ['transition-property' => 'opacity', 'transition-duration' => '0s', 'transition-delay' => '50ms']],
			'animation' => ['animation', '1s cubic-bezier(0.2, 0, 0, 1) 0.5s infinite alternate forwards paused fadeIn', '1s cubic-bezier(0.2, 0, 0, 1) 0.5s infinite alternate forwards paused fadeIn', ['animation-duration' => '1s', 'animation-delay' => '0.5s', 'animation-iteration-count' => 'infinite', 'animation-direction' => 'alternate', 'animation-fill-mode' => 'forwards', 'animation-play-state' => 'paused', 'animation-name' => 'fadeIn', 'animation-range-start' => 'normal', 'animation-range-end' => 'normal']],
			'animation name collision' => ['animation', 'normal reverse', 'normal reverse', ['animation-direction' => 'normal', 'animation-name' => 'reverse']],
			'animation-range' => ['animation-range', 'entry 10% exit 90%', 'entry 10% exit 90%', ['animation-range-start' => 'entry 10%', 'animation-range-end' => 'exit 90%']],
			'scroll-timeline' => ['scroll-timeline', '--scroller inline', '--scroller inline', ['scroll-timeline-name' => '--scroller', 'scroll-timeline-axis' => 'inline']],
			'view-timeline' => ['view-timeline', '--hero x 10px 20px', '--hero x 10px 20px', ['view-timeline-name' => '--hero', 'view-timeline-axis' => 'x', 'view-timeline-inset' => '10px 20px']],

			'columns' => ['columns', '12rem 3', '12rem 3', ['column-width' => '12rem', 'column-count' => '3']],
			'container' => ['container', 'sidebar / inline-size', 'sidebar / inline-size', ['container-name' => 'sidebar', 'container-type' => 'inline-size']],
			'list-style' => ['list-style', 'square inside url("dot.png")', 'square inside url("dot.png")', ['list-style-type' => 'square', 'list-style-position' => 'inside', 'list-style-image' => 'url("dot.png")']],
			'marker' => ['marker', 'url(#arrow)', 'url(#arrow)', ['marker-start' => 'url(#arrow)', 'marker-mid' => 'url(#arrow)', 'marker-end' => 'url(#arrow)']],
			'offset' => ['offset', 'top left path("M 0 0") 10px 45deg / center', 'top left path("M 0 0") 10px 45deg / center', ['offset-position' => 'top left', 'offset-path' => 'path("M 0 0")', 'offset-distance' => '10px', 'offset-rotate' => '45deg', 'offset-anchor' => 'center']],
			'position-try' => ['position-try', 'most-width --flip flip-block', 'most-width --flip flip-block', ['position-try-order' => 'most-width', 'position-try-fallbacks' => '--flip flip-block']],
			'text-box' => ['text-box', 'cap alphabetic', 'cap alphabetic', ['text-box-trim' => 'trim-both', 'text-box-edge' => 'cap alphabetic']],
			'text-decoration' => ['text-decoration', 'underline 2px wavy red', 'underline 2px wavy red', ['text-decoration-line' => 'underline', 'text-decoration-thickness' => '2px', 'text-decoration-style' => 'wavy', 'text-decoration-color' => 'red']],
			'text-emphasis' => ['text-emphasis', 'filled dot blue', 'filled dot blue', ['text-emphasis-style' => 'filled dot', 'text-emphasis-color' => 'blue']],
			'text-wrap' => ['text-wrap', 'nowrap balance', 'nowrap balance', ['text-wrap-mode' => 'nowrap', 'text-wrap-style' => 'balance']],
			'white-space' => ['white-space', 'pre-wrap', 'pre-wrap', ['white-space-collapse' => 'preserve', 'text-wrap-mode' => 'wrap']],
			'all' => ['all', 'inherit', 'inherit', ['color' => 'inherit', 'margin-top' => 'inherit', 'display' => 'inherit']],
		];
	}

	/**
	 * @param array<string, string> $expectedLonghands
	 */
	#[DataProvider('provideShorthandRoundTripCases')]
	public function testShorthandExpansionAndUniversalRoundTrip(
		string $shorthand,
		string $inputValue,
		string $expectedSerialized,
		array $expectedLonghands,
	): void {
		$decl = new CSSStyleDeclaration();
		$decl->setProperty($shorthand, $inputValue);

		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		self::assertNotNull($longhands);
		self::assertSame(count($longhands), $decl->getLength(), "Expanded length mismatch for {$shorthand}");

		foreach ($expectedLonghands as $lh => $val) {
			self::assertSame($val, $decl->getPropertyValue($lh), "Longhand {$lh} mismatch for {$shorthand}");
		}

		$serialized = $decl->getPropertyValue($shorthand);
		self::assertSame($expectedSerialized, $serialized, "Canonical serialization mismatch for {$shorthand}");
		self::assertTrue(
			CSSValueValidator::validateStandardProperty($shorthand, $serialized),
			"Serialized {$shorthand}: {$serialized} failed validation.",
		);

		$reparsed = new CSSStyleDeclaration();
		$reparsed->setProperty($shorthand, $serialized);
		self::assertSame($decl->getDeclarationsRaw(), $reparsed->getDeclarationsRaw(), "Round-trip mismatch for {$shorthand}");
	}

	public function testImportantConflictAndPartialOverride(): void
	{
		$decl = CSSStyleDeclaration::parse('margin-top: 10px !important; margin-right: 10px; margin-bottom: 10px; margin-left: 10px;');
		self::assertSame('', $decl->getPropertyValue('margin'));
		self::assertSame('', $decl->getPropertyPriority('margin'));
		self::assertSame(
			'margin-top: 10px !important; margin-right: 10px; margin-bottom: 10px; margin-left: 10px;',
			$decl->getCssText(),
		);

		$imp = CSSStyleDeclaration::parse('margin: 10px !important;');
		self::assertSame('important', $imp->getPropertyPriority('margin'));
		self::assertSame('margin: 10px !important;', $imp->getCssText());

		// CSS cascade: non-important override after !important shorthand does not clobber !important longhands
		$cascade = CSSStyleDeclaration::parse('margin: 10px !important; margin-top: 20px;');
		self::assertSame('10px', $cascade->getPropertyValue('margin-top'));
		self::assertSame('10px', $cascade->getPropertyValue('margin'));
		self::assertSame('important', $cascade->getPropertyPriority('margin'));
	}

	public function testCssWideKeywordMixingAndAllShorthandBoundaries(): void
	{
		$mixed = CSSStyleDeclaration::parse('margin-top: inherit; margin-right: 10px; margin-bottom: 10px; margin-left: 10px;');
		self::assertSame('', $mixed->getPropertyValue('margin'));

		$uniform = CSSStyleDeclaration::parse('margin-top: revert; margin-right: revert; margin-bottom: revert; margin-left: revert;');
		self::assertSame('revert', $uniform->getPropertyValue('margin'));
		self::assertSame('margin: revert;', $uniform->getCssText());

		$allDecl = CSSStyleDeclaration::parse('direction: rtl; unicode-bidi: bidi-override; all: unset; --custom: 1;');
		self::assertSame('unset', $allDecl->getPropertyValue('all'));
		self::assertSame('rtl', $allDecl->getPropertyValue('direction'));
		self::assertSame('bidi-override', $allDecl->getPropertyValue('unicode-bidi'));
		self::assertSame('1', $allDecl->getPropertyValue('--custom'));
		self::assertSame('direction: rtl; unicode-bidi: bidi-override; all: unset; --custom: 1;', $allDecl->getCssText());
	}

	public function testPendingSubstitutionAndSystemFontSemantics(): void
	{
		$decl = CSSStyleDeclaration::parse('margin: var(--gap);');
		self::assertSame(4, $decl->getLength());
		self::assertSame('var(--gap)', $decl->getPropertyValue('margin'));
		self::assertSame('', $decl->getPropertyValue('margin-top'));
		self::assertSame('margin: var(--gap);', $decl->getCssText());

		$map = new StylePropertyMap($decl);
		self::assertSame('var(--gap)', (string) $map->get('margin'));
		self::assertNull($map->get('margin-top'));
		self::assertSame([], $map->getAll('margin-top'));

		// Overwriting one longhand orphans the pending shorthand
		$decl->setProperty('margin-top', '10px');
		self::assertSame('', $decl->getPropertyValue('margin'));
		self::assertSame('', $decl->getPropertyValue('margin-right'));
		self::assertSame('margin-top: 10px;', $decl->getCssText());

		// Removing the last non-pending longhand leaves only orphaned pending longhands -> renders {}
		$sheet = CSSStyleSheet::parse('a { margin: var(--gap); margin-top: 10px; }');
		$rule = $sheet->cssRules[0];
		self::assertInstanceOf(CSSStyleRule::class, $rule);
		$rule->style->removeProperty('margin-top');
		self::assertSame('a {}', CSSRenderer::render($sheet));

		// System font pending tracking & copy across StyleProperties / CSSPageRule
		$sysFont = CSSStyleDeclaration::parse('font: menu;');
		self::assertSame('menu', $sysFont->getPropertyValue('font'));
		self::assertSame('', $sysFont->getPropertyValue('font-family'));
		self::assertSame('font: menu;', $sysFont->getCssText());

		$copied = CSSStyleProperties::fromDeclaration($sysFont);
		self::assertSame('menu', $copied->getPropertyValue('font'));
		$pageRule = new CSSPageRule(':first', $sysFont);
		self::assertSame('menu', $pageRule->style->getPropertyValue('font'));

		// `all: var(--x)` pending substitution
		$allVar = CSSStyleDeclaration::parse('all: var(--theme);');
		self::assertSame('var(--theme)', $allVar->getPropertyValue('all'));
		self::assertSame('all: var(--theme);', $allVar->getCssText());
	}

	public function testLogicalPhysicalCascadeBarriers(): void
	{
		$marginBarrier = CSSStyleDeclaration::parse('margin-top: 10px; margin-inline-start: 20px; margin-right: 10px; margin-bottom: 10px; margin-left: 10px;');
		self::assertSame('10px', $marginBarrier->getPropertyValue('margin'));
		self::assertSame(
			'margin-top: 10px; margin-inline-start: 20px; margin-right: 10px; margin-bottom: 10px; margin-left: 10px;',
			$marginBarrier->getCssText(),
		);

		$radiusBarrier = CSSStyleDeclaration::parse('border-top-left-radius: 4px; border-start-end-radius: 8px; border-top-right-radius: 4px; border-bottom-right-radius: 4px; border-bottom-left-radius: 4px;');
		self::assertSame('4px', $radiusBarrier->getPropertyValue('border-radius'));
		self::assertStringContainsString('border-start-end-radius: 8px;', $radiusBarrier->getCssText());
		self::assertStringContainsString('border-top-left-radius: 4px;', $radiusBarrier->getCssText());

		$intrinsicBarrier = CSSStyleDeclaration::parse('contain-intrinsic-width: 100px; contain-intrinsic-block-size: 50px; contain-intrinsic-height: 100px;');
		self::assertSame('100px', $intrinsicBarrier->getPropertyValue('contain-intrinsic-size'));
		self::assertSame(
			'contain-intrinsic-width: 100px; contain-intrinsic-block-size: 50px; contain-intrinsic-height: 100px;',
			$intrinsicBarrier->getCssText(),
		);

		// Programmatic setProperty moves logical-group-blocked longhand to end
		$prog = CSSStyleDeclaration::parse('margin-top: 10px; margin-inline-start: 20px;');
		$prog->setProperty('margin-top', '30px');
		self::assertSame('margin-inline-start: 20px; margin-top: 30px;', $prog->getCssText());
	}

	public function testRemovePropertyTypedOmInteropRendererAndExemptBlocks(): void
	{
		$decl = CSSStyleDeclaration::parse('padding: 4px 8px; color: red;');
		self::assertSame('4px 8px', $decl->removeProperty('padding'));
		self::assertSame(1, $decl->getLength());
		self::assertSame('color: red;', $decl->getCssText());

		$decl->setProperty('margin', '5px');
		$decl->setProperty('margin', '');
		self::assertSame(1, $decl->getLength());

		// StylePropertyMap shorthand get, getAll, has, append, delete & invalid background: text padding-box
		$map = new StylePropertyMap($decl);
		$map->set('background', 'url("a.png")');
		self::assertTrue($map->has('background'));
		$map->append('background', 'url("b.png") red');
		self::assertCount(2, $map->getAll('background'));
		self::assertSame('url("a.png")', (string) $map->get('background'));
		$map->delete('background');
		self::assertFalse($map->has('background'));

		try {
			$map->set('background', 'text padding-box');
			self::fail('Expected background: text padding-box to throw InvalidArgumentException.');
		} catch (InvalidArgumentException) {
			self::assertFalse($map->has('background'));
		}

		// CSSRenderer pretty & minified round-trip
		$sheet = CSSStyleSheet::parse('a { margin: 10px 20px; border: 1px solid red; }');
		$mini = CSSRenderer::render($sheet, new CSSRenderOptions(CSSRenderMode::Minified));
		self::assertSame('a{margin:10px 20px;border:1px solid red;}', $mini);
		self::assertSame($sheet->getCssText(), CSSStyleSheet::parse($mini)->getCssText());

		// Grammar-exempt blocks do not expand shorthands
		$fnDesc = new CSSFunctionDescriptors('margin: 10px;');
		self::assertSame(1, $fnDesc->getLength());
		self::assertSame('10px', $fnDesc->getPropertyValue('margin'));
		self::assertSame('', $fnDesc->getPropertyValue('margin-top'));

		$ffSheet = CSSStyleSheet::parse('@font-face { font-family: "Demo"; src: url("a.woff2"); font-weight: 100 900; }');
		$ffRule = $ffSheet->cssRules[0];
		self::assertInstanceOf(CSSFontFaceRule::class, $ffRule);
		self::assertSame('100 900', $ffRule->style->getPropertyValue('font-weight'));
	}
}
