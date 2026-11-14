<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Formatting and rule callback options for CSSRenderer.
 *
 * @param ?\Closure(CSSRule, string): string $ruleCallback Replaces rendered rule text after its children are rendered
 */
final readonly class CSSRenderOptions
{
	/**
	 * @param ?\Closure(CSSRule, string): string $ruleCallback Replaces rendered rule text after its children are rendered
	 */
	public function __construct(
		public CSSRenderMode $mode = CSSRenderMode::Pretty,
		public ?\Closure $ruleCallback = null,
	) {
	}
}
