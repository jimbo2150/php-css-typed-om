<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@namespace` rule.
 *
 * @property-read string $prefix
 * @property-read string $namespaceURI
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNamespaceRule
 */
final class CSSNamespaceRule extends CSSAtRule
{
	public function __construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		if ($maxBytes !== null && strlen($prelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @namespace rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@namespace requires a namespace URI.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, '@namespace prelude', [';', '{', '}']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('@namespace requires a namespace URI.');
		}
		parent::__construct('namespace', $prelude, null, false, $maxBytes);
	}

	public function getPrefix(): string
	{
		[$prefix] = self::splitNamespacePrelude($this->prelude);
		return $prefix;
	}

	public function getNamespaceURI(): string
	{
		[, $uri] = self::splitNamespacePrelude($this->prelude);
		$uriLen = strlen($uri);
		if ($uriLen > 0) {
			$ident = CSSSyntaxScanner::decodeIdentAt($uri, 0, $uriLen);
			if ($ident !== null && strtolower($ident['ident']) === 'url' && $ident['end'] < $uriLen && $uri[$ident['end']] === '(' && str_ends_with($uri, ')')) {
				$uri = CSSSyntaxScanner::trimCssWhitespace(substr($uri, $ident['end'] + 1, -1));
			}
		}
		if (strlen($uri) >= 2 && (($uri[0] === '"' && $uri[-1] === '"') || ($uri[0] === "'" && $uri[-1] === "'"))) {
			return substr($uri, 1, -1);
		}

		return $uri;
	}

	/** @return array{0: string, 1: string} */
	private static function splitNamespacePrelude(string $prelude): array
	{
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed !== '' && str_contains($trimmed, '/*')) {
			$trimmed = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed));
		}
		$length = strlen($trimmed);
		if ($length === 0) {
			return ['', ''];
		}

		// No prefix when prelude starts directly with quoted string or url(...)
		$first = $trimmed[0];
		if ($first === '"' || $first === "'") {
			return ['', $trimmed];
		}
		$leadingIdent = CSSSyntaxScanner::decodeIdentAt($trimmed, 0, $length);
		if ($leadingIdent !== null && strtolower($leadingIdent['ident']) === 'url' && $leadingIdent['end'] < $length && $trimmed[$leadingIdent['end']] === '(') {
			return ['', $trimmed];
		}

		// Scan optional leading identifier prefix up to whitespace or quoted string
		$pos = 0;
		while ($pos < $length) {
			$ch = $trimmed[$pos];
			if ($ch === '"' || $ch === "'" || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $ch)) {
				break;
			}
			if ($ch === '\\') {
				$pos++;
				if ($pos < $length) {
					$hexLen = strspn($trimmed, '0123456789abcdefABCDEF', $pos, min(6, $length - $pos));
					if ($hexLen > 0) {
						$pos += $hexLen;
						if ($pos < $length && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $trimmed[$pos])) {
							if ($trimmed[$pos] === "\r" && $pos + 1 < $length && $trimmed[$pos + 1] === "\n") {
								$pos += 2;
							} else {
								$pos++;
							}
						}
					} else {
						$pos++;
					}
				}
				continue;
			}
			$pos++;
		}

		$prefix = CSSSyntaxScanner::trimCssWhitespace(substr($trimmed, 0, $pos));
		$rest = CSSSyntaxScanner::trimCssWhitespace(substr($trimmed, $pos));
		if ($rest === '') {
			return ['', $prefix];
		}

		return [$prefix, $rest];
	}

	public function getType(): int
	{
		return self::NAMESPACE_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'prefix' => $this->getPrefix(),
			'namespaceURI' => $this->getNamespaceURI(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'prefix' || $name === 'namespaceURI' || parent::__isset($name);
	}
}
