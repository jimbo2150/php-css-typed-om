<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Formatting and rule callback options for CSSRenderer.
 *
 * @param ?\Closure(CSSRule, string): string $ruleCallback       Replaces rendered rule text after its children are rendered
 * @param bool                               $keepComments       Whether to retain comments in `CSSOpaqueRule` output. Comments inside structured
 *                                                               declarations are stripped during parsing and cannot be restored by the renderer.
 * @param bool                               $escapeHtmlStyleTag Escapes `</style` sequences as `<\/style` for safer embedding inside HTML `<style>` elements
 */
final readonly class CSSRenderOptions
{
	/**
	 * @param ?\Closure(CSSRule, string): string $ruleCallback       Replaces rendered rule text after its children are rendered
	 * @param bool                               $keepComments       Whether to retain comments in `CSSOpaqueRule` output
	 * @param bool                               $escapeHtmlStyleTag Escapes `</style` sequences as `<\/style` for safer embedding inside HTML `<style>` elements
	 */
	public function __construct(
		public CSSRenderMode $mode = CSSRenderMode::Pretty,
		public ?\Closure $ruleCallback = null,
		public bool $keepComments = true,
		public bool $escapeHtmlStyleTag = false,
	) {
	}
}
