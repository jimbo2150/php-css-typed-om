<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

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

	public function __construct(string $prelude)
	{
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@import requires a URL.');
		}
		parent::__construct('import', $trimmed);

		$parts = self::parseTailParts($this->prelude);
		$this->urlToken = $parts['urlToken'];
		$this->hrefValue = $parts['href'];
		$this->layerNameValue = $parts['layerName'];
		$this->supportsTextValue = $parts['supportsText'];
		$this->mediaList = new MediaList($parts['media'], function (string $mediaText): void {
			if (!$this->initializing) {
				$this->rebuildPrelude($mediaText);
			}
		});
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
		$this->prelude = implode(' ', $parts);
	}

	/** @return array{urlToken: string, href: string, layerName: ?string, supportsText: ?string, media: string} */
	private static function parseTailParts(string $prelude): array
	{
		$prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
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
			$tokens = CSSSyntaxScanner::splitTopLevelWhitespace($prelude);
			$urlToken = array_shift($tokens) ?? '';
			$tail = implode(' ', $tokens);
		}

		$href = self::unwrapUrlToken($urlToken);
		$layerName = null;
		$supportsText = null;
		$mediaTokens = [];
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

			foreach (CSSSyntaxScanner::splitTopLevelWhitespace(substr($tail, $p)) as $token) {
				$tokLen = strlen($token);
				$tokIdent = CSSSyntaxScanner::decodeIdentAt($token, 0, $tokLen);
				$tokLower = $tokIdent !== null ? strtolower($tokIdent['ident']) : null;
				$tokEnd = $tokIdent !== null ? $tokIdent['end'] : -1;
				if (($tokLower === 'layer' && $tokEnd === $tokLen)
					|| ($tokLower === 'layer' && $tokEnd < $tokLen && $token[$tokEnd] === '(' && str_ends_with($token, ')'))
				) {
					throw new InvalidArgumentException('CSS @import layer() must precede supports() and media queries.');
				}
				if ($tokLower === 'supports' && $tokEnd < $tokLen && $token[$tokEnd] === '(' && str_ends_with($token, ')')) {
					throw new InvalidArgumentException('CSS @import supports() must precede media queries.');
				}
				$mediaTokens[] = $token;
			}
			break;
		}

		return [
			'urlToken' => $urlToken,
			'href' => $href,
			'layerName' => $layerName,
			'supportsText' => $supportsText,
			'media' => implode(' ', $mediaTokens),
		];
	}

	private static function unwrapUrlToken(string $token): string
	{
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
	}
}
