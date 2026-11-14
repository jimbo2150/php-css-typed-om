<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSImportRule extends CSSAtRule
{
	public function __construct(string $prelude)
	{
		parent::__construct('import', $prelude);
	}

	public function getHref(): string
	{
		$prelude = trim($this->prelude);
		if (preg_match('/^url\((.*)\)/is', $prelude, $match)) {
			return trim(trim($match[1]), " \t\n\r\0\x0B\"'");
		}

		return trim(strtok($prelude, " \t\n\r"), "\"'");
	}

	public function getMediaText(): string
	{
		$prelude = trim($this->prelude);
		if (preg_match('/^url\([^)]*\)\s*(.*)$/is', $prelude, $match)) {
			return trim($match[1]);
		}
		if (preg_match('/^(?:"[^"]*"|\'[^\']*\'|\S+)\s*(.*)$/s', $prelude, $match)) {
			return trim($match[1]);
		}
		return '';
	}

	public function getLayerName(): ?string
	{
		if (!preg_match('/(?:^|\s)layer(?:\(\s*([^)]*)\s*\))?(?:\s|$)/i', $this->prelude, $match)) {
			return null;
		}
		return trim($match[1] ?? '');
	}

	public function getSupportsText(): string
	{
		if (!preg_match('/\bsupports\(\s*(.*?)\s*\)/i', $this->prelude, $match)) {
			return '';
		}
		return trim($match[1]);
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
			'mediaText' => $this->getMediaText(),
			'styleSheet' => $this->getStyleSheet(),
			'layerName' => $this->getLayerName(),
			'supportsText' => $this->getSupportsText(),
			default => parent::__get($name),
		};
	}
}
