<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Error;

/**
 * Base class for all CSSOM rule nodes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSRule
 */
abstract class CSSRule
{
	public const int UNKNOWN_RULE = 0;
	public const int STYLE_RULE = 1;
	public const int CHARSET_RULE = 2;
	public const int IMPORT_RULE = 3;
	public const int MEDIA_RULE = 4;
	public const int FONT_FACE_RULE = 5;
	public const int PAGE_RULE = 6;
	public const int KEYFRAMES_RULE = 7;
	public const int KEYFRAME_RULE = 8;
	public const int MARGIN_RULE = 9;
	public const int NAMESPACE_RULE = 10;
	public const int COUNTER_STYLE_RULE = 11;
	public const int SUPPORTS_RULE = 12;
	public const int FONT_FEATURE_VALUES_RULE = 14;
	public const int FONT_PALETTE_VALUES_RULE = 15;
	public const int FUNCTION_RULE = 16;
	public const int PROPERTY_RULE = 17;
	public const int LAYER_BLOCK_RULE = 18;
	public const int LAYER_STATEMENT_RULE = 19;
	public const int STARTING_STYLE_RULE = 20;
	public const int POSITION_TRY_RULE = 21;
	public const int FUNCTION_DECLARATIONS_RULE = 22;
	public const int SCOPE_RULE = 24;
	public const int CONTAINER_RULE = 25;
	public const int VIEW_TRANSITION_RULE = 26;
	public const int NESTED_DECLARATIONS_RULE = 27;

	private ?CSSStyleSheet $parentStyleSheet = null;
	private ?CSSGroupingRule $parentRule = null;

	abstract public function getType(): int;

	abstract public function getCssText(): string;

	public function getParentStyleSheet(): ?CSSStyleSheet
	{
		return $this->parentStyleSheet;
	}

	public function getParentRule(): ?CSSGroupingRule
	{
		return $this->parentRule;
	}

	/** @internal Updates parent pointers without traversing child subtrees. */
	public function setParentLinks(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$this->parentStyleSheet = $sheet;
		$this->parentRule = $parentRule;
	}

	/** @internal Attaches or detaches ownership metadata on this rule node. */
	public function attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$this->parentStyleSheet = $sheet;
		$this->parentRule = $parentRule;
	}

	public function __toString(): string
	{
		return $this->getCssText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'type' => $this->getType(),
			'cssText' => $this->getCssText(),
			'parentStyleSheet' => $this->getParentStyleSheet(),
			'parentRule' => $this->getParentRule(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'type', 'cssText' => true,
			'parentStyleSheet' => $this->getParentStyleSheet() !== null,
			'parentRule' => $this->getParentRule() !== null,
			default => false,
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	public function __clone(): void
	{
		$this->parentStyleSheet = null;
		$this->parentRule = null;
	}
}
