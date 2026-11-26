<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;

/**
 * Authoritative registry of CSSOM §6.7 shorthand-to-longhand mappings,
 * initial values, logical/physical sibling groups, and reverse indices.
 *
 * @internal
 */
final class CSSShorthandRegistry
{
	/**
	 * Ordered constituent longhands for every shorthand except `all` (76 shorthands).
	 *
	 * @var array<string, list<string>>
	 */
	private const array SHORTHAND_LONGHANDS = [
		// 4-side boxes
		'margin' => ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
		'padding' => ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
		'inset' => ['top', 'right', 'bottom', 'left'],
		'border-width' => ['border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width'],
		'border-style' => ['border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style'],
		'border-color' => ['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color'],
		'scroll-margin' => ['scroll-margin-top', 'scroll-margin-right', 'scroll-margin-bottom', 'scroll-margin-left'],
		'scroll-padding' => ['scroll-padding-top', 'scroll-padding-right', 'scroll-padding-bottom', 'scroll-padding-left'],

		// 2-side / pair shorthands
		'margin-block' => ['margin-block-start', 'margin-block-end'],
		'margin-inline' => ['margin-inline-start', 'margin-inline-end'],
		'padding-block' => ['padding-block-start', 'padding-block-end'],
		'padding-inline' => ['padding-inline-start', 'padding-inline-end'],
		'inset-block' => ['inset-block-start', 'inset-block-end'],
		'inset-inline' => ['inset-inline-start', 'inset-inline-end'],
		'border-block-width' => ['border-block-start-width', 'border-block-end-width'],
		'border-block-style' => ['border-block-start-style', 'border-block-end-style'],
		'border-block-color' => ['border-block-start-color', 'border-block-end-color'],
		'border-inline-width' => ['border-inline-start-width', 'border-inline-end-width'],
		'border-inline-style' => ['border-inline-start-style', 'border-inline-end-style'],
		'border-inline-color' => ['border-inline-start-color', 'border-inline-end-color'],
		'scroll-margin-block' => ['scroll-margin-block-start', 'scroll-margin-block-end'],
		'scroll-margin-inline' => ['scroll-margin-inline-start', 'scroll-margin-inline-end'],
		'scroll-padding-block' => ['scroll-padding-block-start', 'scroll-padding-block-end'],
		'scroll-padding-inline' => ['scroll-padding-inline-start', 'scroll-padding-inline-end'],
		'gap' => ['row-gap', 'column-gap'],
		'overflow' => ['overflow-x', 'overflow-y'],
		'overscroll-behavior' => ['overscroll-behavior-x', 'overscroll-behavior-y'],
		'contain-intrinsic-size' => ['contain-intrinsic-width', 'contain-intrinsic-height'],
		'place-content' => ['align-content', 'justify-content'],
		'place-items' => ['align-items', 'justify-items'],
		'place-self' => ['align-self', 'justify-self'],

		// Border line & side shorthands
		'border-top' => ['border-top-width', 'border-top-style', 'border-top-color'],
		'border-right' => ['border-right-width', 'border-right-style', 'border-right-color'],
		'border-bottom' => ['border-bottom-width', 'border-bottom-style', 'border-bottom-color'],
		'border-left' => ['border-left-width', 'border-left-style', 'border-left-color'],
		'border-block-start' => ['border-block-start-width', 'border-block-start-style', 'border-block-start-color'],
		'border-block-end' => ['border-block-end-width', 'border-block-end-style', 'border-block-end-color'],
		'border-inline-start' => ['border-inline-start-width', 'border-inline-start-style', 'border-inline-start-color'],
		'border-inline-end' => ['border-inline-end-width', 'border-inline-end-style', 'border-inline-end-color'],
		'border-block' => [
			'border-block-start-width',
			'border-block-start-style',
			'border-block-start-color',
			'border-block-end-width',
			'border-block-end-style',
			'border-block-end-color',
		],
		'border-inline' => [
			'border-inline-start-width',
			'border-inline-start-style',
			'border-inline-start-color',
			'border-inline-end-width',
			'border-inline-end-style',
			'border-inline-end-color',
		],
		'border' => [
			'border-top-width',
			'border-right-width',
			'border-bottom-width',
			'border-left-width',
			'border-top-style',
			'border-right-style',
			'border-bottom-style',
			'border-left-style',
			'border-top-color',
			'border-right-color',
			'border-bottom-color',
			'border-left-color',
			'border-image-source',
			'border-image-slice',
			'border-image-width',
			'border-image-outset',
			'border-image-repeat',
		],
		'outline' => ['outline-color', 'outline-style', 'outline-width'],
		'column-rule' => ['column-rule-width', 'column-rule-style', 'column-rule-color'],

		// Border corners & image
		'border-radius' => [
			'border-top-left-radius',
			'border-top-right-radius',
			'border-bottom-right-radius',
			'border-bottom-left-radius',
		],
		'border-image' => [
			'border-image-source',
			'border-image-slice',
			'border-image-width',
			'border-image-outset',
			'border-image-repeat',
		],
		'mask-border' => [
			'mask-border-source',
			'mask-border-mode',
			'mask-border-slice',
			'mask-border-width',
			'mask-border-outset',
			'mask-border-repeat',
		],

		// Background & mask
		'background-position' => ['background-position-x', 'background-position-y'],
		'background' => [
			'background-image',
			'background-position-x',
			'background-position-y',
			'background-size',
			'background-repeat',
			'background-attachment',
			'background-origin',
			'background-clip',
			'background-color',
		],
		'mask' => [
			'mask-image',
			'mask-position',
			'mask-size',
			'mask-repeat',
			'mask-origin',
			'mask-clip',
			'mask-composite',
			'mask-mode',
		],

		// Flexbox
		'flex' => ['flex-grow', 'flex-shrink', 'flex-basis'],
		'flex-flow' => ['flex-direction', 'flex-wrap'],

		// Fonts
		'font-synthesis' => [
			'font-synthesis-weight',
			'font-synthesis-style',
			'font-synthesis-small-caps',
			'font-synthesis-position',
		],
		'font-variant' => [
			'font-variant-ligatures',
			'font-variant-caps',
			'font-variant-alternates',
			'font-variant-numeric',
			'font-variant-east-asian',
			'font-variant-position',
			'font-variant-emoji',
		],
		'font' => [
			'font-style',
			'font-variant-caps',
			'font-weight',
			'font-stretch',
			'font-size',
			'line-height',
			'font-family',
			'font-variant-ligatures',
			'font-variant-alternates',
			'font-variant-numeric',
			'font-variant-east-asian',
			'font-variant-position',
			'font-variant-emoji',
			'font-optical-sizing',
			'font-size-adjust',
			'font-kerning',
			'font-feature-settings',
			'font-variation-settings',
			'font-language-override',
		],

		// Grid
		'grid-row' => ['grid-row-start', 'grid-row-end'],
		'grid-column' => ['grid-column-start', 'grid-column-end'],
		'grid-area' => ['grid-row-start', 'grid-column-start', 'grid-row-end', 'grid-column-end'],
		'grid-template' => ['grid-template-rows', 'grid-template-columns', 'grid-template-areas'],
		'grid' => [
			'grid-template-rows',
			'grid-template-columns',
			'grid-template-areas',
			'grid-auto-flow',
			'grid-auto-rows',
			'grid-auto-columns',
		],

		// Timing & timelines
		'transition' => [
			'transition-property',
			'transition-duration',
			'transition-timing-function',
			'transition-delay',
			'transition-behavior',
		],
		'animation' => [
			'animation-duration',
			'animation-timing-function',
			'animation-delay',
			'animation-iteration-count',
			'animation-direction',
			'animation-fill-mode',
			'animation-play-state',
			'animation-name',
			'animation-timeline',
			'animation-range-start',
			'animation-range-end',
		],
		'animation-range' => ['animation-range-start', 'animation-range-end'],
		'scroll-timeline' => ['scroll-timeline-name', 'scroll-timeline-axis'],
		'view-timeline' => ['view-timeline-name', 'view-timeline-axis', 'view-timeline-inset'],

		// Typography, layout & misc
		'columns' => ['column-width', 'column-count'],
		'container' => ['container-name', 'container-type'],
		'list-style' => ['list-style-position', 'list-style-image', 'list-style-type'],
		'marker' => ['marker-start', 'marker-mid', 'marker-end'],
		'offset' => ['offset-position', 'offset-path', 'offset-distance', 'offset-rotate', 'offset-anchor'],
		'position-try' => ['position-try-order', 'position-try-fallbacks'],
		'text-box' => ['text-box-trim', 'text-box-edge'],
		'text-decoration' => [
			'text-decoration-line',
			'text-decoration-thickness',
			'text-decoration-style',
			'text-decoration-color',
		],
		'text-emphasis' => ['text-emphasis-style', 'text-emphasis-color'],
		'text-wrap' => ['text-wrap-mode', 'text-wrap-style'],
		'white-space' => ['white-space-collapse', 'text-wrap-mode'],
	];

	/**
	 * Authoritative W3C initial values for every shorthand-participating longhand.
	 *
	 * @var array<string, string>
	 */
	private const array INITIAL_VALUES = [
		// Margins, paddings, insets, scroll offsets
		'margin-top' => '0px',
		'margin-right' => '0px',
		'margin-bottom' => '0px',
		'margin-left' => '0px',
		'margin-block-start' => '0px',
		'margin-block-end' => '0px',
		'margin-inline-start' => '0px',
		'margin-inline-end' => '0px',
		'padding-top' => '0px',
		'padding-right' => '0px',
		'padding-bottom' => '0px',
		'padding-left' => '0px',
		'padding-block-start' => '0px',
		'padding-block-end' => '0px',
		'padding-inline-start' => '0px',
		'padding-inline-end' => '0px',
		'top' => 'auto',
		'right' => 'auto',
		'bottom' => 'auto',
		'left' => 'auto',
		'inset-block-start' => 'auto',
		'inset-block-end' => 'auto',
		'inset-inline-start' => 'auto',
		'inset-inline-end' => 'auto',
		'scroll-margin-top' => '0px',
		'scroll-margin-right' => '0px',
		'scroll-margin-bottom' => '0px',
		'scroll-margin-left' => '0px',
		'scroll-margin-block-start' => '0px',
		'scroll-margin-block-end' => '0px',
		'scroll-margin-inline-start' => '0px',
		'scroll-margin-inline-end' => '0px',
		'scroll-padding-top' => 'auto',
		'scroll-padding-right' => 'auto',
		'scroll-padding-bottom' => 'auto',
		'scroll-padding-left' => 'auto',
		'scroll-padding-block-start' => 'auto',
		'scroll-padding-block-end' => 'auto',
		'scroll-padding-inline-start' => 'auto',
		'scroll-padding-inline-end' => 'auto',

		// Borders & outlines
		'border-top-width' => 'medium',
		'border-right-width' => 'medium',
		'border-bottom-width' => 'medium',
		'border-left-width' => 'medium',
		'border-block-start-width' => 'medium',
		'border-block-end-width' => 'medium',
		'border-inline-start-width' => 'medium',
		'border-inline-end-width' => 'medium',
		'border-top-style' => 'none',
		'border-right-style' => 'none',
		'border-bottom-style' => 'none',
		'border-left-style' => 'none',
		'border-block-start-style' => 'none',
		'border-block-end-style' => 'none',
		'border-inline-start-style' => 'none',
		'border-inline-end-style' => 'none',
		'border-top-color' => 'currentcolor',
		'border-right-color' => 'currentcolor',
		'border-bottom-color' => 'currentcolor',
		'border-left-color' => 'currentcolor',
		'border-block-start-color' => 'currentcolor',
		'border-block-end-color' => 'currentcolor',
		'border-inline-start-color' => 'currentcolor',
		'border-inline-end-color' => 'currentcolor',
		'border-top-left-radius' => '0px',
		'border-top-right-radius' => '0px',
		'border-bottom-right-radius' => '0px',
		'border-bottom-left-radius' => '0px',
		'border-image-source' => 'none',
		'border-image-slice' => '100%',
		'border-image-width' => '1',
		'border-image-outset' => '0',
		'border-image-repeat' => 'stretch',
		'mask-border-source' => 'none',
		'mask-border-mode' => 'alpha',
		'mask-border-slice' => '0',
		'mask-border-width' => 'auto',
		'mask-border-outset' => '0',
		'mask-border-repeat' => 'stretch',
		'outline-color' => 'auto',
		'outline-style' => 'none',
		'outline-width' => 'medium',
		'column-rule-width' => 'medium',
		'column-rule-style' => 'none',
		'column-rule-color' => 'currentcolor',

		// Background & mask
		'background-image' => 'none',
		'background-position-x' => '0%',
		'background-position-y' => '0%',
		'background-size' => 'auto',
		'background-repeat' => 'repeat',
		'background-attachment' => 'scroll',
		'background-origin' => 'padding-box',
		'background-clip' => 'border-box',
		'background-color' => 'transparent',
		'mask-image' => 'none',
		'mask-position' => '0% 0%',
		'mask-size' => 'auto',
		'mask-repeat' => 'repeat',
		'mask-origin' => 'border-box',
		'mask-clip' => 'border-box',
		'mask-composite' => 'add',
		'mask-mode' => 'match-source',

		// Pairs & alignment
		'row-gap' => 'normal',
		'column-gap' => 'normal',
		'overflow-x' => 'visible',
		'overflow-y' => 'visible',
		'overscroll-behavior-x' => 'auto',
		'overscroll-behavior-y' => 'auto',
		'contain-intrinsic-width' => 'none',
		'contain-intrinsic-height' => 'none',
		'align-content' => 'normal',
		'justify-content' => 'normal',
		'align-items' => 'normal',
		'justify-items' => 'normal',
		'align-self' => 'auto',
		'justify-self' => 'auto',

		// Flexbox
		'flex-grow' => '0',
		'flex-shrink' => '1',
		'flex-basis' => 'auto',
		'flex-direction' => 'row',
		'flex-wrap' => 'nowrap',

		// Fonts
		'font-style' => 'normal',
		'font-variant-ligatures' => 'normal',
		'font-variant-caps' => 'normal',
		'font-variant-alternates' => 'normal',
		'font-variant-numeric' => 'normal',
		'font-variant-east-asian' => 'normal',
		'font-variant-position' => 'normal',
		'font-variant-emoji' => 'normal',
		'font-weight' => 'normal',
		'font-stretch' => 'normal',
		'font-size' => 'medium',
		'line-height' => 'normal',
		'font-family' => 'sans-serif',
		'font-optical-sizing' => 'auto',
		'font-size-adjust' => 'none',
		'font-kerning' => 'auto',
		'font-feature-settings' => 'normal',
		'font-variation-settings' => 'normal',
		'font-language-override' => 'normal',
		'font-synthesis-weight' => 'auto',
		'font-synthesis-style' => 'auto',
		'font-synthesis-small-caps' => 'auto',
		'font-synthesis-position' => 'auto',

		// Grid
		'grid-row-start' => 'auto',
		'grid-row-end' => 'auto',
		'grid-column-start' => 'auto',
		'grid-column-end' => 'auto',
		'grid-template-rows' => 'none',
		'grid-template-columns' => 'none',
		'grid-template-areas' => 'none',
		'grid-auto-flow' => 'row',
		'grid-auto-rows' => 'auto',
		'grid-auto-columns' => 'auto',

		// Timing & timelines
		'transition-property' => 'all',
		'transition-duration' => '0s',
		'transition-timing-function' => 'ease',
		'transition-delay' => '0s',
		'transition-behavior' => 'normal',
		'animation-duration' => 'auto',
		'animation-timing-function' => 'ease',
		'animation-delay' => '0s',
		'animation-iteration-count' => '1',
		'animation-direction' => 'normal',
		'animation-fill-mode' => 'none',
		'animation-play-state' => 'running',
		'animation-name' => 'none',
		'animation-timeline' => 'auto',
		'animation-range-start' => 'normal',
		'animation-range-end' => 'normal',
		'scroll-timeline-name' => 'none',
		'scroll-timeline-axis' => 'block',
		'view-timeline-name' => 'none',
		'view-timeline-axis' => 'block',
		'view-timeline-inset' => 'auto',

		// Misc
		'column-width' => 'auto',
		'column-count' => 'auto',
		'container-name' => 'none',
		'container-type' => 'normal',
		'list-style-position' => 'outside',
		'list-style-image' => 'none',
		'list-style-type' => 'disc',
		'marker-start' => 'none',
		'marker-mid' => 'none',
		'marker-end' => 'none',
		'offset-position' => 'normal',
		'offset-path' => 'none',
		'offset-distance' => '0px',
		'offset-rotate' => 'auto',
		'offset-anchor' => 'auto',
		'position-try-order' => 'normal',
		'position-try-fallbacks' => 'none',
		'text-box-trim' => 'none',
		'text-box-edge' => 'auto',
		'text-decoration-line' => 'none',
		'text-decoration-thickness' => 'auto',
		'text-decoration-style' => 'solid',
		'text-decoration-color' => 'currentcolor',
		'text-emphasis-style' => 'none',
		'text-emphasis-color' => 'currentcolor',
		'text-wrap-mode' => 'wrap',
		'text-wrap-style' => 'auto',
		'white-space-collapse' => 'collapse',
	];

	/**
	 * Physical vs logical sibling property groups for CSSOM §6.7.2 cascade barrier checks.
	 *
	 * @var array<string, array{group: int, logical: bool}>
	 */
	private const array LOGICAL_GROUPS = [
		// 1: margin
		'margin-top' => ['group' => 1, 'logical' => false],
		'margin-right' => ['group' => 1, 'logical' => false],
		'margin-bottom' => ['group' => 1, 'logical' => false],
		'margin-left' => ['group' => 1, 'logical' => false],
		'margin-block-start' => ['group' => 1, 'logical' => true],
		'margin-block-end' => ['group' => 1, 'logical' => true],
		'margin-inline-start' => ['group' => 1, 'logical' => true],
		'margin-inline-end' => ['group' => 1, 'logical' => true],

		// 2: padding
		'padding-top' => ['group' => 2, 'logical' => false],
		'padding-right' => ['group' => 2, 'logical' => false],
		'padding-bottom' => ['group' => 2, 'logical' => false],
		'padding-left' => ['group' => 2, 'logical' => false],
		'padding-block-start' => ['group' => 2, 'logical' => true],
		'padding-block-end' => ['group' => 2, 'logical' => true],
		'padding-inline-start' => ['group' => 2, 'logical' => true],
		'padding-inline-end' => ['group' => 2, 'logical' => true],

		// 3: inset
		'top' => ['group' => 3, 'logical' => false],
		'right' => ['group' => 3, 'logical' => false],
		'bottom' => ['group' => 3, 'logical' => false],
		'left' => ['group' => 3, 'logical' => false],
		'inset-block-start' => ['group' => 3, 'logical' => true],
		'inset-block-end' => ['group' => 3, 'logical' => true],
		'inset-inline-start' => ['group' => 3, 'logical' => true],
		'inset-inline-end' => ['group' => 3, 'logical' => true],

		// 4: border-width
		'border-top-width' => ['group' => 4, 'logical' => false],
		'border-right-width' => ['group' => 4, 'logical' => false],
		'border-bottom-width' => ['group' => 4, 'logical' => false],
		'border-left-width' => ['group' => 4, 'logical' => false],
		'border-block-start-width' => ['group' => 4, 'logical' => true],
		'border-block-end-width' => ['group' => 4, 'logical' => true],
		'border-inline-start-width' => ['group' => 4, 'logical' => true],
		'border-inline-end-width' => ['group' => 4, 'logical' => true],

		// 5: border-style
		'border-top-style' => ['group' => 5, 'logical' => false],
		'border-right-style' => ['group' => 5, 'logical' => false],
		'border-bottom-style' => ['group' => 5, 'logical' => false],
		'border-left-style' => ['group' => 5, 'logical' => false],
		'border-block-start-style' => ['group' => 5, 'logical' => true],
		'border-block-end-style' => ['group' => 5, 'logical' => true],
		'border-inline-start-style' => ['group' => 5, 'logical' => true],
		'border-inline-end-style' => ['group' => 5, 'logical' => true],

		// 6: border-color
		'border-top-color' => ['group' => 6, 'logical' => false],
		'border-right-color' => ['group' => 6, 'logical' => false],
		'border-bottom-color' => ['group' => 6, 'logical' => false],
		'border-left-color' => ['group' => 6, 'logical' => false],
		'border-block-start-color' => ['group' => 6, 'logical' => true],
		'border-block-end-color' => ['group' => 6, 'logical' => true],
		'border-inline-start-color' => ['group' => 6, 'logical' => true],
		'border-inline-end-color' => ['group' => 6, 'logical' => true],

		// 7: border-radius
		'border-top-left-radius' => ['group' => 7, 'logical' => false],
		'border-top-right-radius' => ['group' => 7, 'logical' => false],
		'border-bottom-right-radius' => ['group' => 7, 'logical' => false],
		'border-bottom-left-radius' => ['group' => 7, 'logical' => false],
		'border-start-start-radius' => ['group' => 7, 'logical' => true],
		'border-start-end-radius' => ['group' => 7, 'logical' => true],
		'border-end-start-radius' => ['group' => 7, 'logical' => true],
		'border-end-end-radius' => ['group' => 7, 'logical' => true],

		// 8: contain-intrinsic-size
		'contain-intrinsic-width' => ['group' => 8, 'logical' => false],
		'contain-intrinsic-height' => ['group' => 8, 'logical' => false],
		'contain-intrinsic-block-size' => ['group' => 8, 'logical' => true],
		'contain-intrinsic-inline-size' => ['group' => 8, 'logical' => true],

		// 9: scroll-margin
		'scroll-margin-top' => ['group' => 9, 'logical' => false],
		'scroll-margin-right' => ['group' => 9, 'logical' => false],
		'scroll-margin-bottom' => ['group' => 9, 'logical' => false],
		'scroll-margin-left' => ['group' => 9, 'logical' => false],
		'scroll-margin-block-start' => ['group' => 9, 'logical' => true],
		'scroll-margin-block-end' => ['group' => 9, 'logical' => true],
		'scroll-margin-inline-start' => ['group' => 9, 'logical' => true],
		'scroll-margin-inline-end' => ['group' => 9, 'logical' => true],

		// 10: scroll-padding
		'scroll-padding-top' => ['group' => 10, 'logical' => false],
		'scroll-padding-right' => ['group' => 10, 'logical' => false],
		'scroll-padding-bottom' => ['group' => 10, 'logical' => false],
		'scroll-padding-left' => ['group' => 10, 'logical' => false],
		'scroll-padding-block-start' => ['group' => 10, 'logical' => true],
		'scroll-padding-block-end' => ['group' => 10, 'logical' => true],
		'scroll-padding-inline-start' => ['group' => 10, 'logical' => true],
		'scroll-padding-inline-end' => ['group' => 10, 'logical' => true],

		// 11: overflow
		'overflow-x' => ['group' => 11, 'logical' => false],
		'overflow-y' => ['group' => 11, 'logical' => false],
		'overflow-block' => ['group' => 11, 'logical' => true],
		'overflow-inline' => ['group' => 11, 'logical' => true],

		// 12: overscroll-behavior
		'overscroll-behavior-x' => ['group' => 12, 'logical' => false],
		'overscroll-behavior-y' => ['group' => 12, 'logical' => false],
		'overscroll-behavior-block' => ['group' => 12, 'logical' => true],
		'overscroll-behavior-inline' => ['group' => 12, 'logical' => true],
	];

	/** @var ?list<string> */
	private static ?array $allLonghands = null;

	/** @var ?array<string, list<string>> */
	private static ?array $reverseIndex = null;

	/** @var ?array<string, list<array{group: int, logical: bool}>> */
	private static ?array $shorthandLogicalGroups = null;

	public static function isShorthand(string $normalizedLowerProperty): bool
	{
		return $normalizedLowerProperty === 'all' || isset(self::SHORTHAND_LONGHANDS[$normalizedLowerProperty]);
	}

	/**
	 * @return list<string>
	 */
	public static function getAllShorthands(): array
	{
		$keys = array_keys(self::SHORTHAND_LONGHANDS);
		$keys[] = 'all';

		return $keys;
	}

	/**
	 * @return ?list<string>
	 */
	public static function getLonghands(string $shorthand): ?array
	{
		if ($shorthand === 'all') {
			return self::getAllLonghands();
		}

		return self::SHORTHAND_LONGHANDS[$shorthand] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public static function getAllLonghands(): array
	{
		if (self::$allLonghands !== null) {
			return self::$allLonghands;
		}

		$list = [];
		foreach (CSSPropertyGrammarRegistry::allProperties() as $prop) {
			if ($prop === 'all' || isset(self::SHORTHAND_LONGHANDS[$prop])) {
				continue;
			}
			if ($prop === 'direction' || $prop === 'unicode-bidi') {
				continue;
			}
			$list[] = $prop;
		}

		return self::$allLonghands = $list;
	}

	public static function getAllLonghandsCount(): int
	{
		return count(self::getAllLonghands());
	}

	public static function isShorthandLonghand(string $longhand): bool
	{
		return isset(self::INITIAL_VALUES[$longhand]);
	}

	/**
	 * Returns candidate shorthands (excluding `all`) containing `$longhand`,
	 * pre-sorted by descending longhand count.
	 *
	 * @return list<string>
	 */
	public static function getShorthandsForLonghand(string $longhand): array
	{
		if (self::$reverseIndex === null) {
			self::buildReverseIndex();
		}

		return self::$reverseIndex[$longhand] ?? [];
	}

	public static function getInitialValue(string $longhand): string
	{
		return self::INITIAL_VALUES[$longhand] ?? 'initial';
	}

	/**
	 * @return ?array{group: int, logical: bool}
	 */
	public static function getLogicalGroup(string $longhand): ?array
	{
		return self::LOGICAL_GROUPS[$longhand] ?? null;
	}

	/**
	 * Returns distinct logical groups represented among the shorthand's longhands.
	 *
	 * @return list<array{group: int, logical: bool}>
	 */
	public static function getLogicalGroupsForShorthand(string $shorthand): array
	{
		if ($shorthand === 'all') {
			return [];
		}

		if (self::$shorthandLogicalGroups === null) {
			self::buildShorthandLogicalGroups();
		}

		return self::$shorthandLogicalGroups[$shorthand] ?? [];
	}

	private static function buildReverseIndex(): void
	{
		$index = [];
		foreach (self::SHORTHAND_LONGHANDS as $shorthand => $longhands) {
			foreach ($longhands as $lh) {
				$index[$lh][] = $shorthand;
			}
		}

		// Sort each longhand's shorthands by descending longhand count, then canonical tie-break order.
		$priorityBias = [
			'border' => 100,
			'border-block' => 90,
			'border-inline' => 90,
			'border-image' => 80,
			'border-width' => 70,
			'border-style' => 70,
			'border-color' => 70,
			'grid' => 100,
			'grid-template' => 85,
			'grid-area' => 80,
			'font' => 100,
			'font-variant' => 80,
			'animation' => 100,
			'background' => 100,
			'text-wrap' => 20,
			'white-space' => 10,
		];

		foreach ($index as $lh => $shorthands) {
			usort(
				$shorthands,
				static function (string $a, string $b) use ($priorityBias): int {
					$countDiff = count(self::SHORTHAND_LONGHANDS[$b]) <=> count(self::SHORTHAND_LONGHANDS[$a]);
					if ($countDiff !== 0) {
						return $countDiff;
					}
					$biasDiff = ($priorityBias[$b] ?? 0) <=> ($priorityBias[$a] ?? 0);
					if ($biasDiff !== 0) {
						return $biasDiff;
					}

					return strcmp($a, $b);
				},
			);
			$index[$lh] = $shorthands;
		}

		self::$reverseIndex = $index;
	}

	private static function buildShorthandLogicalGroups(): void
	{
		$map = [];
		foreach (self::SHORTHAND_LONGHANDS as $shorthand => $longhands) {
			$seen = [];
			$groups = [];
			foreach ($longhands as $lh) {
				$info = self::LOGICAL_GROUPS[$lh] ?? null;
				if ($info === null) {
					continue;
				}
				$key = $info['group'] . ':' . ($info['logical'] ? '1' : '0');
				if (!isset($seen[$key])) {
					$seen[$key] = true;
					$groups[] = $info;
				}
			}
			if ($groups !== []) {
				$map[$shorthand] = $groups;
			}
		}

		self::$shorthandLogicalGroups = $map;
	}
}
