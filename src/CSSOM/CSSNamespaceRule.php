<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

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
	/** @var ?array{0: string, 1: string} */
	private ?array $parsedPrelude = null;

	public function __construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		self::validateNonEmptyFragmentInternal(
			$prelude,
			$maxBytes,
			'CSS @namespace rule',
			'@namespace requires a namespace URI.',
			'@namespace prelude',
		);
		$this->initAtRuleInternal('namespace', $prelude, null, false, $maxBytes);
	}

	public function getPrefix(): string
	{
		[$prefix] = $this->splitNamespacePrelude();
		return $prefix;
	}

	public function getNamespaceURI(): string
	{
		[, $uri] = $this->splitNamespacePrelude();
		$uriLen = strlen($uri);
		if ($uriLen > 0) {
			$ident = CSSSyntaxScanner::decodeIdentAt($uri, 0, $uriLen);
			if ($ident !== null
				&& strtolower($ident['ident']) === 'url'
				&& CSSSyntaxScanner::findMatchingUrlEnd($uri, $ident['end'], $uriLen) === $uriLen - 1
			) {
				$uri = CSSSyntaxScanner::trimCssWhitespace(substr($uri, $ident['end'] + 1, -1));
			}
		}
		return CSSSyntaxScanner::unwrapQuotedString($uri);
	}

	/** @return array{0: string, 1: string} */
	private function splitNamespacePrelude(): array
	{
		if ($this->parsedPrelude !== null) {
			return $this->parsedPrelude;
		}

		$trimmed = CSSSyntaxScanner::trimCssWhitespace($this->prelude);
		if ($trimmed !== '' && str_contains($trimmed, '/*')) {
			$trimmed = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed));
		}
		$length = strlen($trimmed);
		if ($length === 0) {
			return $this->parsedPrelude = ['', ''];
		}

		// No prefix when prelude starts directly with quoted string or url(...)
		$first = $trimmed[0];
		if ($first === '"' || $first === "'") {
			return $this->parsedPrelude = ['', $trimmed];
		}
		$leadingIdent = CSSSyntaxScanner::decodeIdentAt($trimmed, 0, $length);
		if ($leadingIdent !== null && strtolower($leadingIdent['ident']) === 'url' && $leadingIdent['end'] < $length && $trimmed[$leadingIdent['end']] === '(') {
			return $this->parsedPrelude = ['', $trimmed];
		}

		// Scan optional leading identifier prefix up to whitespace or quoted string
		$pos = 0;
		while ($pos < $length) {
			$ch = $trimmed[$pos];
			if ($ch === '"' || $ch === "'" || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $ch)) {
				break;
			}
			if ($ch === '\\') {
				$pos = CSSSyntaxScanner::advanceIdentEscapeEnd($trimmed, $pos, $length);
				continue;
			}
			$pos++;
		}

		$prefix = CSSSyntaxScanner::trimCssWhitespace(substr($trimmed, 0, $pos));
		$rest = CSSSyntaxScanner::trimCssWhitespace(substr($trimmed, $pos));
		if ($rest === '') {
			return $this->parsedPrelude = ['', $prefix];
		}

		return $this->parsedPrelude = [$prefix, $rest];
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
