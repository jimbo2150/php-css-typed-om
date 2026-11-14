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

	public function __construct(string $prelude)
	{
		$trimmed = trim($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@import requires a URL.');
		}
		parent::__construct('import', $trimmed);
		$this->media = new MediaList($this->extractTailParts()['media']);
	}

	public function getHref(): string
	{
		return $this->extractTailParts()['href'];
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
		return $this->extractTailParts()['layerName'];
	}

	public function getSupportsText(): string
	{
		return $this->extractTailParts()['supportsText'];
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
			'href' => $this->getHref(),
			'media' => $this->getMedia(),
			'mediaText' => $this->getMediaText(),
			'styleSheet' => $this->getStyleSheet(),
			'layerName' => $this->getLayerName(),
			'supportsText' => $this->getSupportsText(),
			default => parent::__get($name),
		};
	}

	/** @return array{href: string, layerName: ?string, supportsText: string, media: string} */
	private function extractTailParts(): array
	{
		$tokens = CSSSyntaxScanner::splitTopLevelWhitespace($this->prelude);
		if ($tokens === []) {
			return ['href' => '', 'layerName' => null, 'supportsText' => '', 'media' => ''];
		}

		$urlToken = array_shift($tokens);
		$href = self::unwrapUrlToken($urlToken);
		$layerName = null;
		$supportsText = '';
		$mediaTokens = [];

		foreach ($tokens as $token) {
			if (strcasecmp($token, 'layer') === 0 && $layerName === null) {
				$layerName = '';
				continue;
			}
			if (strncasecmp($token, 'layer(', 6) === 0 && str_ends_with($token, ')') && $layerName === null) {
				$layerName = trim(substr($token, 6, -1));
				continue;
			}
			if (strncasecmp($token, 'supports(', 9) === 0 && str_ends_with($token, ')') && $supportsText === '') {
				$supportsText = trim(substr($token, 9, -1));
				continue;
			}
			$mediaTokens[] = $token;
		}

		return [
			'href' => $href,
			'layerName' => $layerName,
			'supportsText' => $supportsText,
			'media' => implode(' ', $mediaTokens),
		];
	}

	private static function unwrapUrlToken(string $token): string
	{
		if (strncasecmp($token, 'url(', 4) === 0 && str_ends_with($token, ')')) {
			$token = trim(substr($token, 4, -1));
		}

		if (strlen($token) >= 2 && (($token[0] === '"' && $token[-1] === '"') || ($token[0] === "'" && $token[-1] === "'"))) {
			return substr($token, 1, -1);
		}

		return $token;
	}
}
