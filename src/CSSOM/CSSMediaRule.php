<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents a CSS `@media` rule.
 *
 * @property-read MediaList $media
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMediaRule
 */
final class CSSMediaRule extends CSSConditionRule
{
	private MediaList $mediaList;

	public function __construct(
		string $conditionText,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $browserErrorRecovery = false,
	) {
		$this->initGroupingRuleInternal('media', '', $maxBytes);
		$this->mediaList = new MediaList($conditionText, function (string $text): void {
			$this->prelude = $text;
		}, $maxBytes, $browserErrorRecovery);
		$this->mediaList->setOwnerInternal($this);
		$this->prelude = $this->mediaList->getMediaText();
	}

	public function getType(): int
	{
		return self::MEDIA_RULE;
	}

	public function getMedia(): MediaList
	{
		return $this->mediaList;
	}

	public function getConditionText(): string
	{
		return $this->mediaList->getMediaText();
	}

	public function setConditionText(string $conditionText): void
	{
		$this->mediaList->setMediaText($conditionText);
	}

	public function __get(string $name): mixed
	{
		return $name === 'media' ? $this->getMedia() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'media' || parent::__isset($name);
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
		$this->mediaList->setMaxBytesInternal(CSSRule::minLimitInternal($this->mediaList->getMaxBytesInternal(), $maxBytes));
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->mediaList = $this->mediaList->cloneWithCallback(function (string $text): void {
			$this->prelude = $text;
		});
		$this->mediaList->setOwnerInternal($this);
	}
}
