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
	/** @var ?list<string> */
	private ?array $cachedLayerNames = null;

	/**
	 * @throws InvalidArgumentException When $layerNames is empty, invalid, or contains empty/comment-only segments
	 */
	public function __construct(string $layerNames, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		$trimmed = self::validateNonEmptyFragmentInternal(
			$layerNames,
			$maxBytes,
			'CSS @layer statement',
			'@layer statement requires at least one layer name.',
			'@layer statement',
		);
		$parts = CSSSyntaxScanner::splitTopLevel($trimmed, ',', 0, null, CSSStyleValue::MAX_LIST_ITEMS);
		$names = [];
		foreach ($parts as $part) {
			$cleanPart = CSSSyntaxScanner::trimCssWhitespace($part);
			if ($cleanPart === '' || (str_contains($cleanPart, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($cleanPart)) === '')) {
				throw new InvalidArgumentException('@layer statement cannot contain empty layer names.');
			}
			$names[] = $cleanPart;
		}
		$this->cachedLayerNames = $names;
		$this->initAtRuleInternal('layer', $trimmed, null, false, $maxBytes);
	}

	public function getType(): int
	{
		return self::LAYER_STATEMENT_RULE;
	}

	/** @return list<string> */
	public function getLayerNames(): array
	{
		if ($this->cachedLayerNames !== null) {
			return $this->cachedLayerNames;
		}

		$names = [];
		foreach (CSSSyntaxScanner::splitTopLevel($this->prelude, ',') as $name) {
			$trimmed = CSSSyntaxScanner::trimCssWhitespace($name);
			if ($trimmed !== '') {
				$names[] = $trimmed;
			}
		}

		return $this->cachedLayerNames = $names;
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
