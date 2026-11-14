<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

abstract class StyleSheet
{
	private bool $disabled = false;
	private MediaList $media;

	public function __construct(
		private readonly ?string $href = null,
		private readonly string $title = '',
		?MediaList $media = null,
	) {
		$this->media = $media ?? new MediaList();
	}

	public function getHref(): ?string
	{
		return $this->href;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function isDisabled(): bool
	{
		return $this->disabled;
	}

	public function setDisabled(bool $disabled): void
	{
		$this->disabled = $disabled;
	}

	public function getMedia(): MediaList
	{
		return $this->media;
	}
}
