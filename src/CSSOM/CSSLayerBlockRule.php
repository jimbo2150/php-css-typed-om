<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * @property-read string $name
 */
final class CSSLayerBlockRule extends CSSGroupingRule
{
	public function __construct(string $layerName = '')
	{
		parent::__construct('layer', $layerName);
	}

	public function getType(): int
	{
		return self::LAYER_BLOCK_RULE;
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function __get(string $name): mixed
	{
		return $name === 'name' ? $this->getNameText() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'name' || parent::__isset($name);
	}
}
