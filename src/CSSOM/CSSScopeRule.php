<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@scope` rule.
 *
 * @property-read ?string $start
 * @property-read ?string $end
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSScopeRule
 */
final class CSSScopeRule extends CSSGroupingRule
{
	private ?string $startSelector = null;
	private ?string $endSelector = null;

	public function __construct(string $prelude = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		parent::__construct('scope', $prelude, $maxBytes);
		[$this->startSelector, $this->endSelector] = self::parseBounds($this->prelude);
	}

	public function getType(): int
	{
		return self::SCOPE_RULE;
	}

	public function getStart(): ?string
	{
		return $this->startSelector;
	}

	public function getEnd(): ?string
	{
		return $this->endSelector;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'start' => $this->startSelector,
			'end' => $this->endSelector,
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'start' => $this->startSelector !== null,
			'end' => $this->endSelector !== null,
			default => parent::__isset($name),
		};
	}

	/** @return array{?string, ?string} */
	private static function parseBounds(string $prelude): array
	{
		$prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if (str_contains($prelude, '/*')) {
			$prelude = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($prelude));
		}
		if ($prelude === '') {
			return [null, null];
		}

		$offset = 0;
		$start = null;
		$end = null;

		if ($prelude[0] === '(') {
			$close = CSSSyntaxScanner::findMatchingDelimiter($prelude, 0);
			if ($close === null) {
				throw new InvalidArgumentException('Unclosed @scope start selector.');
			}
			$start = CSSSyntaxScanner::trimCssWhitespace(substr($prelude, 1, $close - 1));
			if ($start === '') {
				throw new InvalidArgumentException('Empty @scope start selector is not allowed.');
			}
			$offset = $close + 1;
		}

		$rest = CSSSyntaxScanner::trimCssWhitespace(substr($prelude, $offset));
		if ($rest !== '') {
			$restLen = strlen($rest);
			if (preg_match('/^to[ \t\n\r\f]+\(/i', $rest)) {
				$open = strpos($rest, '(');
			} else {
				$ident = CSSSyntaxScanner::decodeIdentAt($rest, 0, $restLen);
				if ($ident === null || strtolower($ident['ident']) !== 'to' || $ident['end'] >= $restLen || !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $rest[$ident['end']])) {
					throw new InvalidArgumentException('Malformed @scope prelude: ' . $prelude);
				}
				$afterWs = $ident['end'] + strspn($rest, CSSSyntaxScanner::CSS_WHITESPACE, $ident['end']);
				if ($afterWs >= $restLen || $rest[$afterWs] !== '(') {
					throw new InvalidArgumentException('Malformed @scope prelude: ' . $prelude);
				}
				$open = $afterWs;
			}
			$close = $open === false ? null : CSSSyntaxScanner::findMatchingDelimiter($rest, $open);
			if ($close !== $restLen - 1) {
				throw new InvalidArgumentException('Malformed @scope end selector.');
			}
			$end = CSSSyntaxScanner::trimCssWhitespace(substr($rest, $open + 1, $close - $open - 1));
			if ($end === '') {
				throw new InvalidArgumentException('Empty @scope end selector is not allowed.');
			}
		}

		return [$start, $end];
	}
}
