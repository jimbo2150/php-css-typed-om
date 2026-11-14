<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Error;

/**
 * Base metadata model for standalone stylesheets.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/StyleSheet
 */
abstract class StyleSheet
{
	private bool $disabled = false;
	private MediaList $media;

	public function __construct(
		private readonly ?string $href = null,
		private readonly string $title = '',
		?MediaList $media = null,
		private readonly ?CSSRule $ownerRule = null,
		private readonly ?StyleSheet $parentStyleSheet = null,
	) {
		$this->media = $media ?? new MediaList();
	}

	public function getType(): string
	{
		return 'text/css';
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

	public function getOwnerRule(): ?CSSRule
	{
		return $this->ownerRule;
	}

	public function getParentStyleSheet(): ?StyleSheet
	{
		return $this->parentStyleSheet;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'type' => $this->getType(),
			'href' => $this->getHref(),
			'title' => $this->getTitle(),
			'disabled' => $this->isDisabled(),
			'media' => $this->getMedia(),
			'ownerRule' => $this->getOwnerRule(),
			'parentStyleSheet' => $this->getParentStyleSheet(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'disabled' && is_bool($value)) {
			$this->setDisabled($value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
