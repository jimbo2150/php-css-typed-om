<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Base metadata model for standalone stylesheets.
 *
 * @property-read string $type
 * @property-read ?string $href
 * @property-read string $title
 * @property bool $disabled
 * @property-read MediaList $media
 * @property-read ?CSSRule $ownerRule
 * @property-read ?StyleSheet $parentStyleSheet
 * @see https://developer.mozilla.org/en-US/docs/Web/API/StyleSheet
 */
abstract class StyleSheet
{
	private bool $disabledFlag = false;
	private MediaList $mediaList;
	private readonly ?string $hrefValue;
	private readonly string $titleValue;
	private readonly ?CSSRule $ownerRuleRef;
	private readonly ?StyleSheet $parentSheetRef;

	public function __construct(
		?string $href = null,
		string $title = '',
		MediaList|string|null $media = null,
		?CSSRule $ownerRule = null,
		?StyleSheet $parentStyleSheet = null,
		bool $disabled = false,
	) {
		if ($href !== null && CSSSyntaxScanner::hasControlCharacters($href)) {
			throw new InvalidArgumentException('StyleSheet href cannot contain control characters.');
		}
		if (CSSSyntaxScanner::hasControlCharacters($title)) {
			throw new InvalidArgumentException('StyleSheet title cannot contain control characters.');
		}
		$this->hrefValue = $href;
		$this->titleValue = $title;
		$this->ownerRuleRef = $ownerRule;
		$this->parentSheetRef = $parentStyleSheet;
		$this->mediaList = $media instanceof MediaList ? $media->cloneWithCallback(null) : new MediaList($media ?? '');
		$this->disabledFlag = $disabled;
	}

	public function getType(): string
	{
		return 'text/css';
	}

	public function getHref(): ?string
	{
		return $this->hrefValue;
	}

	public function getTitle(): string
	{
		return $this->titleValue;
	}

	public function isDisabled(): bool
	{
		return $this->disabledFlag;
	}

	public function setDisabled(bool $disabled): void
	{
		$this->disabledFlag = $disabled;
	}

	public function getMedia(): MediaList
	{
		return $this->mediaList;
	}

	public function getOwnerRule(): ?CSSRule
	{
		return $this->ownerRuleRef;
	}

	public function getParentStyleSheet(): ?StyleSheet
	{
		return $this->parentSheetRef;
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

	public function __isset(string $name): bool
	{
		return match ($name) {
			'type', 'title', 'disabled', 'media' => true,
			'href' => $this->hrefValue !== null,
			'ownerRule' => $this->ownerRuleRef !== null,
			'parentStyleSheet' => $this->parentSheetRef !== null,
			default => false,
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

	public function __clone(): void
	{
		$this->mediaList = $this->mediaList->cloneWithCallback(null);
	}
}
