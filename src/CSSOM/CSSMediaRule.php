<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents a CSS `@media` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMediaRule
 */
final class CSSMediaRule extends CSSConditionRule
{
	private MediaList $media;

	public function __construct(string $conditionText)
	{
		parent::__construct('media', $conditionText);
		$this->media = new MediaList($conditionText, function (string $text): void {
			$this->prelude = $text;
		});
		$this->prelude = $this->media->getMediaText();
	}

	public function getType(): int
	{
		return self::MEDIA_RULE;
	}

	public function getMedia(): MediaList
	{
		return $this->media;
	}

	public function getConditionText(): string
	{
		return $this->media->getMediaText();
	}

	public function setConditionText(string $conditionText): void
	{
		$this->media->setMediaText($conditionText);
	}

	public function __get(string $name): mixed
	{
		return $name === 'media' ? $this->getMedia() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'media' || parent::__isset($name);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->media = $this->media->cloneWithCallback(function (string $text): void {
			$this->prelude = $text;
		});
	}
}
