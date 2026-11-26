<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * @internal Iterative parser frame for rule-list containers (stylesheet, @media, @supports, @keyframes, etc.).
 */
final class CSSRuleListFrame
{
	public function __construct(
		public CSSRuleList $rules,
		public bool $keyframes,
		public int $depth,
		public bool $stopOnClosingBrace,
		public ?int $openBraceOffset,
		public bool $allowEofClose,
		public bool $allowCdoCdc,
		public bool $allowRelativeSelectors = false,
		public bool $limitReached = false,
		public ?CSSRule $pendingRule = null,
		public ?CSSRuleList $parentRules = null,
		public bool $parentStopOnBrace = false,
		public int $ruleStartOffset = 0,
		public bool $enforceRuleOrder = false,
		public bool $seenAnyRule = false,
		public bool $seenNonImportPrelude = false,
		public bool $seenRegularRule = false,
	) {
	}
}
