<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

final class CSSLayerStatementRule extends CSSAtRule
{
	public function __construct(string $layerNames)
	{
		parent::__construct('layer', $layerNames);
	}

	public function getType(): int
	{
		return self::LAYER_STATEMENT_RULE;
	}

	public function getLayerNames(): array
	{
		return array_values(array_filter(
			array_map(static fn (string $name): string => CSSSyntaxScanner::trimCssWhitespace($name), explode(',', $this->prelude)),
			static fn (string $name): bool => $name !== '',
		));
	}

	public function __get(string $name): mixed
	{
		return $name === 'nameList' ? $this->getLayerNames() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'nameList' || parent::__isset($name);
	}
}
