<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Closure;

/**
 * Formatting and rule callback options for CSSRenderer.
 */
final readonly class CSSRenderOptions
{
	/**
	 * @param ?Closure(CSSRule, string): string $ruleCallback       Replaces rendered rule text after its children are rendered
	 * @param bool                              $keepComments       Whether to retain comments in `CSSOpaqueRule` output (structured declarations strip comments at parse time)
	 * @param bool                              $escapeHtmlStyleTag Escapes `</style` sequences as `<\/style` for safer embedding inside HTML `<style>` elements
	 */
	public function __construct(
		public CSSRenderMode $mode = CSSRenderMode::Pretty,
		public ?Closure $ruleCallback = null,
		public bool $keepComments = true,
		public bool $escapeHtmlStyleTag = false,
	) {
	}
}
