<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

abstract class CSSParserObserver
{
	abstract public function __construct();

	abstract public function startRuleHeader(StyleRuleType $type, int $offset): void;

	abstract public function endRuleHeader(int $offset): void;

	abstract public function observeSelector(int $startOffset, int $endOffset): void;

	abstract public function startRuleBody(int $offset): void;

	abstract public function endRuleBody(int $offset): void;

	abstract public function markRuleBodyContainsImplicitlyNestedProperties(): void;

	abstract public function observeProperty(
		int $startOffset,
		int $endOffset,
		bool $isImportant,
		bool $isParsed,
	): void;

	abstract public function observeComment(int $startOffset, int $endOffset): void;
}
