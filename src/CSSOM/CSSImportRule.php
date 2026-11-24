<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@import` rule. Import rules retain URL, layer, supports,
 * and media query metadata without fetching external stylesheets.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImportRule
 */
final class CSSImportRule extends CSSAtRule
{
	private MediaList $media;
	private string $urlToken;
	private string $href;
	private ?string $layerName;
	private ?string $supportsText;
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
		$this->href = $parts['href'];
		$this->layerName = $parts['layerName'];
		$this->supportsText = $parts['supportsText'];
		$this->media = new MediaList($parts['media'], function (string $mediaText): void {
			if (!$this->initializing) {
				$this->rebuildPrelude($mediaText);
			}
		});
		$this->initializing = false;
	}

	public function getHref(): string
	{
		return $this->href;
	}

	public function getMedia(): MediaList
	{
		return $this->media;
	}

	public function getMediaText(): string
	{
		return $this->media->getMediaText();
	}

	public function getLayerName(): ?string
	{
		return $this->layerName;
	}

	public function getSupportsText(): ?string
	{
		return $this->supportsText;
	}

	public function getType(): int
	{
		return self::IMPORT_RULE;
	}

	public function getStyleSheet(): ?CSSStyleSheet
	{
		return null;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'href' => $this->href,
			'media' => $this->media,
			'mediaText' => $this->media->getMediaText(),
			'styleSheet' => null,
			'layerName' => $this->layerName,
			'supportsText' => $this->supportsText,
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'href', 'media', 'mediaText' => true,
			'styleSheet' => false,
			'layerName' => $this->layerName !== null,
			'supportsText' => $this->supportsText !== null,
			default => parent::__isset($name),
		};
	}

	private function rebuildPrelude(string $mediaText): void
	{
		$parts = [$this->urlToken];
		if ($this->layerName !== null) {
			$parts[] = $this->layerName === '' ? 'layer' : 'layer(' . $this->layerName . ')';
		}
		if ($this->supportsText !== null) {
			$parts[] = 'supports(' . $this->supportsText . ')';
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
		$inMedia = false;

		$p = 0;
		$tLen = strlen($tail);
		while ($p < $tLen) {
			$p += strspn($tail, CSSSyntaxScanner::CSS_WHITESPACE, $p, $tLen - $p);
			if ($p >= $tLen) {
				break;
			}

			if (!$inMedia) {
				if ($p + 6 <= $tLen && substr_compare($tail, 'layer(', $p, 6, true) === 0) {
					$close = CSSSyntaxScanner::findMatchingDelimiter($tail, $p + 5, $tLen);
					if ($close === null) {
						throw new InvalidArgumentException('Malformed CSS @import layer() clause.');
					}
					if ($supportsText !== null) {
						throw new InvalidArgumentException('CSS @import layer() must precede supports().');
					}
					if ($layerName !== null) {
						throw new InvalidArgumentException('Duplicate CSS @import layer() clause.');
					}
					$name = CSSSyntaxScanner::trimCssWhitespace(substr($tail, $p + 6, $close - ($p + 6)));
					if ($name === '') {
						throw new InvalidArgumentException('Empty CSS @import layer() clause is not allowed.');
					}
					$layerName = $name;
					$p = $close + 1;
					continue;
				}
				if ($p + 5 <= $tLen
					&& substr_compare($tail, 'layer', $p, 5, true) === 0
					&& ($p + 5 === $tLen || str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $tail[$p + 5]))
				) {
					if ($supportsText !== null) {
						throw new InvalidArgumentException('CSS @import layer() must precede supports().');
					}
					if ($layerName !== null) {
						throw new InvalidArgumentException('Duplicate CSS @import layer clause.');
					}
					$layerName = '';
					$p += 5;
					continue;
				}
				if ($p + 9 <= $tLen && substr_compare($tail, 'supports(', $p, 9, true) === 0) {
					$close = CSSSyntaxScanner::findMatchingDelimiter($tail, $p + 8, $tLen);
					if ($close === null) {
						throw new InvalidArgumentException('Malformed CSS @import supports() clause.');
					}
					if ($supportsText !== null) {
						throw new InvalidArgumentException('Duplicate CSS @import supports() clause.');
					}
					$cond = CSSSyntaxScanner::trimCssWhitespace(substr($tail, $p + 9, $close - ($p + 9)));
					if ($cond === '') {
						throw new InvalidArgumentException('Empty CSS @import supports() clause is not allowed.');
					}
					$supportsText = $cond;
					$p = $close + 1;
					continue;
				}
			}

			$inMedia = true;
			foreach (CSSSyntaxScanner::splitTopLevelWhitespace(substr($tail, $p)) as $token) {
				if (strcasecmp($token, 'layer') === 0
					|| (strncasecmp($token, 'layer(', 6) === 0 && str_ends_with($token, ')'))
				) {
					throw new InvalidArgumentException('CSS @import layer() must precede supports() and media queries.');
				}
				if (strncasecmp($token, 'supports(', 9) === 0 && str_ends_with($token, ')')) {
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
		$this->media = $this->media->cloneWithCallback(function (string $mediaText): void {
			if (!$this->initializing) {
				$this->rebuildPrelude($mediaText);
			}
		});
	}
}
