<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

final class CSSLayerStatementRule extends CSSAtRule
{
	/**
	 * @throws InvalidArgumentException When $layerNames is empty or contains empty segments
	 */
	public function __construct(string $layerNames)
	{
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($layerNames);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@layer statement requires at least one layer name.');
		}
		$parts = CSSSyntaxScanner::splitTopLevel($trimmed, ',');
		foreach ($parts as $part) {
			if (CSSSyntaxScanner::trimCssWhitespace($part) === '') {
				throw new InvalidArgumentException('@layer statement cannot contain empty layer names.');
			}
		}
		parent::__construct('layer', $trimmed);
	}

	public function getType(): int
	{
		return self::LAYER_STATEMENT_RULE;
	}

	public function getLayerNames(): array
	{
		return array_values(array_filter(
			array_map(
				static fn (string $name): string => CSSSyntaxScanner::trimCssWhitespace($name),
				CSSSyntaxScanner::splitTopLevel($this->prelude, ','),
			),
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
