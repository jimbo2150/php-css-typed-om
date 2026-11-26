<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * @property-read string $name
 */
final class CSSLayerBlockRule extends CSSGroupingRule
{
	public function __construct(string $layerName = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($layerName) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS grouping rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmedPrelude = CSSSyntaxScanner::trimCssWhitespace($layerName);
		if ($trimmedPrelude !== '') {
			CSSSyntaxScanner::assertValidFragment($trimmedPrelude, 'CSS rule prelude', [';', '{', '}']);
		}
		$this->initGroupingRuleInternal('layer', $trimmedPrelude, $maxBytes);
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
