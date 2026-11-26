<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * @internal Iterative parser frame for style, page, margin, function, and declaration at-rule blocks.
 */
final class CSSStyleBlockFrame
{
	/** @var list<array{message: string, offset: int}> */
	public array $diagnostics = [];

	public function __construct(
		public CSSStyleDeclaration $currentStyle,
		public ?CSSGroupingRule $ownerRule,
		public int $depth,
		public int $openBraceOffset,
		public int $ruleStartOffset,
		public bool $allowNestedRules,
		public bool $pageMode = false,
		public bool $functionMode = false,
		public bool $validatePropertyGrammar = true,
		public bool $hasNestedRule = false,
		public bool $isGroupingWithDecls = false,
		public bool $allowEmptyInitialValue = false,
		public bool $sawEmptyInitialValue = false,
		public bool $allowEofClose = false,
		public ?CSSRule $pendingRule = null,
		public ?string $declAtRuleName = null,
		public ?string $declAtRuleCondition = null,
		public ?CSSRuleList $parentRules = null,
		public bool $parentStopOnBrace = false,
	) {
	}
}
