<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Base class for all CSSOM rule nodes.
 *
 * @property-read int $type
 * @property-read string $cssText
 * @property-read ?CSSStyleSheet $parentStyleSheet
 * @property-read ?CSSGroupingRule $parentRule
 * @property-read ?CSSSourceSpan $sourceSpan
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
	public const int COLOR_PROFILE_RULE = 28;
	public const int CUSTOM_MEDIA_RULE = 29;

	private ?CSSStyleSheet $parentSheetRef = null;
	private ?CSSGroupingRule $parentRuleRef = null;
	private ?CSSSourceSpan $sourceSpanValue = null;
	protected ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;

	abstract public function getType(): int;

	abstract public function getCssText(): string;

	public function getParentStyleSheet(): ?CSSStyleSheet
	{
		return $this->parentSheetRef;
	}

	public function getSourceSpan(): ?CSSSourceSpan
	{
		return $this->sourceSpanValue;
	}

	/**
	 * @internal Sets the originating CSS source span when `CSSParserOptions::$trackSpans` is enabled.
	 */
	public function setSourceSpanInternal(?CSSSourceSpan $span): void
	{
		$this->sourceSpanValue = $span;
	}

	public function getParentRule(): ?CSSGroupingRule
	{
		return $this->parentRuleRef;
	}

	/** @internal */
	public static function assertValidMaxBytesInternal(?int $maxBytes): void
	{
		if ($maxBytes !== null && $maxBytes < 1) {
			throw new InvalidArgumentException('maxBytes must be null or at least 1.');
		}
	}

	/** @internal */
	public static function minLimitInternal(?int $a, ?int $b): ?int
	{
		if ($a === null) {
			return $b;
		}
		if ($b === null) {
			return $a;
		}

		return $a < $b ? $a : $b;
	}

	/** @internal */
	public function resolveMaxBytesInternal(): ?int
	{
		$sheetBytes = $this->parentSheetRef?->getMaxBytesInternal();
		if ($sheetBytes === null) {
			return $this->maxBytes;
		}
		if ($this->maxBytes === null) {
			return $sheetBytes;
		}

		return $this->maxBytes < $sheetBytes ? $this->maxBytes : $sheetBytes;
	}

	/** @internal */
	public function clampMaxBytesInternal(?int $maxBytes): void
	{
		$clamped = self::minLimitInternal($this->maxBytes, $maxBytes);
		if ($clamped !== $this->maxBytes || $maxBytes !== null) {
			$this->maxBytes = $clamped;
			$this->syncChildLimitsInternal($clamped, null, false);
		}
	}

	/** @internal Returns false for descriptor-only at-rules (`@property`, `@counter-style`, `@font-palette-values`, `@view-transition`, `@font-face`, `@color-profile`). */
	public function validatesDescriptorPropertyValuesInternal(): bool
	{
		return true;
	}

	/** @internal */
	public function getOwnedStyleInternal(): ?CSSStyleDeclaration
	{
		return null;
	}

	/** @internal */
	public static function validateNonEmptyFragmentInternal(
		string $text,
		?int $maxBytes,
		string $bytesLabel,
		string $emptyMessage,
		string $fragmentLabel,
	): string {
		if ($maxBytes !== null && strlen($text) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('%s exceeds maximum size of %d bytes.', $bytesLabel, $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($text);
		if ($trimmed === '' || (str_contains($trimmed, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '')) {
			throw new InvalidArgumentException($emptyMessage);
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, $fragmentLabel, [';', '{', '}']);

		return $trimmed;
	}

	/** @internal */
	public static function validateDashedIdentInternal(
		string $name,
		?int $maxBytes,
		string $bytesLabel,
		string $missingPrefixMessage,
	): string {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('%s exceeds maximum size of %d bytes.', $bytesLabel, $maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($name);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException($missingPrefixMessage);
		}

		return $normalized;
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
		$this->getOwnedStyleInternal()?->clampLimitsInternal(
			$maxBytes,
			$syncDeclarations ? $maxDeclarations : null,
			$maxDepth,
			$maxMathDepth,
			$syncDepths,
		);
	}

	/** @internal Updates parent pointers without traversing child subtrees. */
	public function setParentLinks(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$limitSource = $sheet ?? $this->parentSheetRef;
		if ($limitSource !== null) {
			$clampedMaxBytes = self::minLimitInternal($this->maxBytes, $limitSource->getMaxBytesInternal());
			$this->maxBytes = $clampedMaxBytes;
			$this->syncChildLimitsInternal(
				$clampedMaxBytes,
				$limitSource->getMaxDeclarationsInternal(),
				true,
				$limitSource->getMaxDepthInternal(),
				$limitSource->getMaxMathDepthInternal(),
				true,
			);
		}
		$this->parentSheetRef = $sheet;
		$this->parentRuleRef = $parentRule;
	}

	/** @internal Attaches or detaches ownership metadata on this rule node. */
	public function attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		$this->setParentLinks($sheet, $parentRule);
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
			'sourceSpan' => $this->getSourceSpan(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'type', 'cssText' => true,
			'parentStyleSheet' => $this->getParentStyleSheet() !== null,
			'parentRule' => $this->getParentRule() !== null,
			'sourceSpan' => $this->sourceSpanValue !== null,
			default => false,
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	public function __clone(): void
	{
		$this->maxBytes = $this->resolveMaxBytesInternal();
		$this->parentSheetRef = null;
		$this->parentRuleRef = null;
	}
}
