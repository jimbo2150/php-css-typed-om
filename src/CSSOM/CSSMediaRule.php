<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSMediaRule extends CSSConditionRule
{
	private MediaList $media;

	public function __construct(string $conditionText)
	{
		parent::__construct('media', $conditionText);
		$this->media = new MediaList($conditionText, fn (string $text) => parent::setConditionText($text));
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
}
