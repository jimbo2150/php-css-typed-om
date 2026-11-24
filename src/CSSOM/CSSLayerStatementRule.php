<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * @property-read list<string> $nameList
 */
final class CSSLayerStatementRule extends CSSAtRule
{
	/**
	 * @throws InvalidArgumentException When $layerNames is empty, invalid, or contains empty/comment-only segments
	 */
	public function __construct(string $layerNames, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		if ($maxBytes !== null && strlen($layerNames) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @layer statement exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($layerNames);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@layer statement requires at least one layer name.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, '@layer statement', [';', '{', '}']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('@layer statement requires at least one layer name.');
		}
		$parts = CSSSyntaxScanner::splitTopLevel($trimmed, ',', 0, null, CSSStyleValue::MAX_LIST_ITEMS);
		foreach ($parts as $part) {
			$cleanPart = CSSSyntaxScanner::trimCssWhitespace($part);
			if ($cleanPart === '' || CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($cleanPart)) === '') {
				throw new InvalidArgumentException('@layer statement cannot contain empty layer names.');
			}
		}
		parent::__construct('layer', $trimmed, null, false, $maxBytes);
	}

	public function getType(): int
	{
		return self::LAYER_STATEMENT_RULE;
	}

	/** @return list<string> */
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
