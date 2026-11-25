<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Represents a CSS `@import` rule. Import rules retain URL, layer, supports,
 * and media query metadata without fetching external stylesheets.
 *
 * @property-read string $href
 * @property-read MediaList $media
 * @property-read string $mediaText
 * @property-read ?CSSStyleSheet $styleSheet
 * @property-read ?string $layerName
 * @property-read ?string $supportsText
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImportRule
 */
final class CSSImportRule extends CSSAtRule
{
	private MediaList $mediaList;
	private string $urlToken;
	private string $hrefValue;
	private ?string $layerNameValue;
	private ?string $supportsTextValue;
	private bool $initializing = true;

	public function __construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		if ($maxBytes !== null && strlen($prelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @import rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@import requires a URL.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, '@import prelude', [';', '{', '}']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('@import requires a URL.');
		}
		parent::__construct('import', $trimmed, null, false, $maxBytes);

		$parts = self::parseTailParts($this->prelude);
		$this->urlToken = $parts['urlToken'];
		$this->hrefValue = $parts['href'];
		$this->layerNameValue = $parts['layerName'];
		$this->supportsTextValue = $parts['supportsText'];
		$this->mediaList = new MediaList($parts['media'], function (string $mediaText): void {
			if (!$this->initializing) {
				$this->rebuildPrelude($mediaText);
			}
		}, $maxBytes);
		$this->mediaList->setOwnerInternal($this);
		$this->initializing = false;
	}

	public function getHref(): string
	{
		return $this->hrefValue;
	}

	public function getMedia(): MediaList
	{
		return $this->mediaList;
	}

	public function getMediaText(): string
	{
		return $this->mediaList->getMediaText();
	}

	public function getLayerName(): ?string
	{
		return $this->layerNameValue;
	}

	public function getSupportsText(): ?string
	{
		return $this->supportsTextValue;
	}

	public function getType(): int
	{
		return self::IMPORT_RULE;
	}

	/** @phpstan-ignore return.unusedType */
	public function getStyleSheet(): ?CSSStyleSheet
	{
		return null;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'href' => $this->hrefValue,
			'media' => $this->mediaList,
			'mediaText' => $this->mediaList->getMediaText(),
			'styleSheet' => null,
			'layerName' => $this->layerNameValue,
			'supportsText' => $this->supportsTextValue,
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'href', 'media', 'mediaText' => true,
			'styleSheet' => false,
			'layerName' => $this->layerNameValue !== null,
			'supportsText' => $this->supportsTextValue !== null,
			default => parent::__isset($name),
		};
	}

	private function rebuildPrelude(string $mediaText): void
	{
		self::assertNoImportClausesInMedia($mediaText);

		$parts = [$this->urlToken];
		if ($this->layerNameValue !== null) {
			$parts[] = $this->layerNameValue === '' ? 'layer' : 'layer(' . $this->layerNameValue . ')';
		}
		if ($this->supportsTextValue !== null) {
			$parts[] = 'supports(' . $this->supportsTextValue . ')';
		}
		if ($mediaText !== '') {
			$parts[] = $mediaText;
		}
		$nextPrelude = implode(' ', $parts);
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($nextPrelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @import rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->prelude = $nextPrelude;
	}

	/** @return array{urlToken: string, href: string, layerName: ?string, supportsText: ?string, media: string} */
	private static function parseTailParts(string $prelude): array
	{
		$prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($prelude !== '' && str_contains($prelude, '/*')) {
			$prelude = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($prelude));
		}
		$length = strlen($prelude);
		if ($length === 0) {
			return ['urlToken' => '', 'href' => '', 'layerName' => null, 'supportsText' => null, 'media' => ''];
		}

		// Extract leading <string-token> or url(...) without requiring whitespace before subsequent tokens.
		$pos = 0;
		$ch = $prelude[0];
		if ($ch === '"' || $ch === "'") {
			$valid = false;
			$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($prelude, 0, $length, $valid);
			$pos = $valid ? $strEnd + 1 : $length;
		} else {
			$openParen = strpos($prelude, '(');
			if ($openParen !== false && CSSSyntaxScanner::decodeLeadingFunctionName($prelude, $openParen) === 'url') {
				$urlEnd = CSSSyntaxScanner::findMatchingUrlEnd($prelude, $openParen, $length);
				$pos = $urlEnd !== null ? $urlEnd + 1 : $length;
			}
		}

		if ($pos > 0) {
			$urlToken = substr($prelude, 0, $pos);
			$tail = CSSSyntaxScanner::trimCssWhitespace(substr($prelude, $pos));
		} else {
			$tokens = CSSSyntaxScanner::splitTopLevelWhitespace($prelude, 0, null, CSSStyleValue::MAX_LIST_ITEMS);
			$urlToken = array_shift($tokens) ?? '';
			$tail = implode(' ', $tokens);
		}

		$href = self::unwrapUrlToken($urlToken);
		$layerName = null;
		$supportsText = null;
		$mediaText = '';
		$p = 0;
		$tLen = strlen($tail);
		while ($p < $tLen) {
			$p += strspn($tail, CSSSyntaxScanner::CSS_WHITESPACE, $p, $tLen - $p);
			if ($p >= $tLen) {
				break;
			}

			$ident = CSSSyntaxScanner::decodeIdentAt($tail, $p, $tLen);
			$identLower = $ident !== null ? strtolower($ident['ident']) : null;
			$identEnd = $ident !== null ? $ident['end'] : -1;

			if ($identLower === 'layer' && $identEnd < $tLen && $tail[$identEnd] === '(') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($tail, $identEnd, $tLen);
				if ($close === null) {
					throw new InvalidArgumentException('Malformed CSS @import layer() clause.');
				}
				if ($supportsText !== null) {
					throw new InvalidArgumentException('CSS @import layer() must precede supports().');
				}
				if ($layerName !== null) {
					throw new InvalidArgumentException('Duplicate CSS @import layer() clause.');
				}
				$name = CSSSyntaxScanner::trimCssWhitespace(substr($tail, $identEnd + 1, $close - ($identEnd + 1)));
				if ($name === '') {
					throw new InvalidArgumentException('Empty CSS @import layer() clause is not allowed.');
				}
				$layerName = $name;
				$p = $close + 1;
				continue;
			}
			if ($identLower === 'layer'
				&& ($identEnd === $tLen || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $tail[$identEnd]))
			) {
				if ($supportsText !== null) {
					throw new InvalidArgumentException('CSS @import layer() must precede supports().');
				}
				if ($layerName !== null) {
					throw new InvalidArgumentException('Duplicate CSS @import layer clause.');
				}
				$layerName = '';
				$p = $identEnd;
				continue;
			}
			if ($identLower === 'supports' && $identEnd < $tLen && $tail[$identEnd] === '(') {
				$close = CSSSyntaxScanner::findMatchingDelimiter($tail, $identEnd, $tLen);
				if ($close === null) {
					throw new InvalidArgumentException('Malformed CSS @import supports() clause.');
				}
				if ($supportsText !== null) {
					throw new InvalidArgumentException('Duplicate CSS @import supports() clause.');
				}
				$cond = CSSSyntaxScanner::trimCssWhitespace(substr($tail, $identEnd + 1, $close - ($identEnd + 1)));
				if ($cond === '') {
					throw new InvalidArgumentException('Empty CSS @import supports() clause is not allowed.');
				}
				$supportsText = $cond;
				$p = $close + 1;
				continue;
			}

			$mediaText = CSSSyntaxScanner::trimCssWhitespace(substr($tail, $p));
			self::assertNoImportClausesInMedia($mediaText);
			break;
		}

		return [
			'urlToken' => $urlToken,
			'href' => $href,
			'layerName' => $layerName,
			'supportsText' => $supportsText,
			'media' => $mediaText,
		];
	}

	private static function assertNoImportClausesInMedia(string $mediaText): void
	{
		if ($mediaText === '') {
			return;
		}
		if (str_contains($mediaText, '/*')) {
			$mediaText = CSSSyntaxScanner::removeComments($mediaText);
		}
		$length = strlen($mediaText);
		$pos = 0;
		$tokenCount = 0;
		while ($pos < $length) {
			$ch = $mediaText[$pos];
			if ($ch === ',' || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $ch)) {
				$pos++;
				continue;
			}
			$tokenStart = $pos;
			$stack = [];
			while ($pos < $length) {
				$c = $mediaText[$pos];
				if ($stack === [] && ($c === ',' || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $c))) {
					break;
				}
				if ($c === '"' || $c === "'") {
					$valid = false;
					$endStr = CSSSyntaxScanner::scanQuotedStringEnd($mediaText, $pos, $length, $valid);
					$pos = $valid ? $endStr + 1 : $length;
					continue;
				}
				if ($c === '\\') {
					$pos = CSSSyntaxScanner::advanceIdentEscapeEnd($mediaText, $pos, $length);
					continue;
				}
				if ($c === '(' || $c === '[' || $c === '{') {
					$stack[] = $c === '(' ? ')' : ($c === '[' ? ']' : '}');
					$pos++;
					continue;
				}
				if ($c === ')' || $c === ']' || $c === '}') {
					if ($stack !== [] && $stack[count($stack) - 1] === $c) {
						array_pop($stack);
					}
					$pos++;
					continue;
				}
				$pos++;
			}

			$tokenCount++;
			if ($tokenCount > CSSStyleValue::MAX_LIST_ITEMS) {
				throw new CSSParserLimitException(sprintf('CSS @import media list exceeds maximum limit of %d tokens.', CSSStyleValue::MAX_LIST_ITEMS));
			}

			$tokLen = $pos - $tokenStart;
			$ident = CSSSyntaxScanner::decodeIdentAt($mediaText, $tokenStart, $pos);
			if ($ident !== null) {
				$lower = strtolower($ident['ident']);
				$identEnd = $ident['end'];
				if ($lower === 'layer' && ($identEnd === $pos || ($identEnd < $pos && $mediaText[$identEnd] === '('))) {
					throw new InvalidArgumentException('CSS @import layer() must precede supports() and media queries.');
				}
				if ($lower === 'supports' && $identEnd < $pos && $mediaText[$identEnd] === '(') {
					throw new InvalidArgumentException('CSS @import supports() must precede media queries.');
				}
			}
			unset($tokLen);
		}
	}

	private static function unwrapUrlToken(string $token): string
	{
		if (str_contains($token, '/*')) {
			$token = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($token));
		}
		$openParen = strpos($token, '(');
		if ($openParen !== false
			&& str_ends_with($token, ')')
			&& CSSSyntaxScanner::decodeLeadingFunctionName($token, $openParen) === 'url'
		) {
			$token = CSSSyntaxScanner::trimCssWhitespace(substr($token, $openParen + 1, -1));
		}

		if (strlen($token) >= 2 && (($token[0] === '"' && $token[-1] === '"') || ($token[0] === "'" && $token[-1] === "'"))) {
			return substr($token, 1, -1);
		}

		return $token;
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->mediaList = $this->mediaList->cloneWithCallback(function (string $mediaText): void {
			if (!$this->initializing) {
				$this->rebuildPrelude($mediaText);
			}
		});
		$this->mediaList->setOwnerInternal($this);
	}
}
