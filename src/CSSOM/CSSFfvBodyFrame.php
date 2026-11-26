<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * @internal Iterative parser frame for `@font-feature-values` rule bodies.
 */
final class CSSFfvBodyFrame
{
	public function __construct(
		public CSSFontFeatureValuesRule $rule,
		public int $depth,
		public int $openBraceOffset,
		public int $ruleStartOffset,
		public bool $allowEofClose = false,
		public ?CSSRule $pendingRule = null,
		public ?CSSRuleList $parentRules = null,
		public bool $parentStopOnBrace = false,
	) {
	}
}
