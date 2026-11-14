<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

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
		return array_values(array_filter(array_map('trim', explode(',', $this->prelude)), static fn (string $name): bool => $name !== ''));
	}

	public function __get(string $name): mixed
	{
		return $name === 'nameList' ? $this->getLayerNames() : parent::__get($name);
	}
}
