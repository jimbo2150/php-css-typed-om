<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

/**
 * Immutable AST node for a compiled CSS Value Definition Syntax grammar.
 *
 * @internal
 */
final readonly class CSSGrammarNode
{
	/**
	 * @param list<self> $children
	 * @param array<string, true> $excludedKeywords
	 * @param array<string, true> $keywordMap
	 * @param array<string, list<self>> $functionMap
	 * @param list<self> $fallbackChildren
	 */
	public function __construct(
		public int $id,
		public CSSGrammarNodeType $type,
		public ?string $value = null,
		public array $children = [],
		public int $min = 1,
		public ?int $max = 1,
		public bool $commaSeparated = false,
		public ?float $rangeMin = null,
		public ?float $rangeMax = null,
		public array $excludedKeywords = [],
		public bool $caseSensitive = false,
		public array $keywordMap = [],
		public bool $keywordMapCaseSensitive = false,
		public array $functionMap = [],
		public array $fallbackChildren = [],
	) {
	}
}
