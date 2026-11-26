<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Base class for statement and descriptor-block CSS at-rules.
 */
/**
 * @property-read string $name
 * @property-read string $prelude
 * @property-read ?CSSStyleDeclaration $style
 */
class CSSAtRule extends CSSRule
{
	protected string $atRuleName;
	protected string $prelude;
	protected ?CSSStyleDeclaration $styleDeclaration;
	protected bool $hasBlock;

	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		string $name,
		string $prelude = '',
		?CSSStyleDeclaration $style = null,
		bool $hasBlock = false,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && (strlen($name) > $maxBytes || strlen($prelude) > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS at-rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmedName = CSSSyntaxScanner::trimCssWhitespace($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS at-rule name: ' . $name);
		}
		$trimmedPrelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmedPrelude !== '') {
			CSSSyntaxScanner::assertValidFragment($trimmedPrelude, 'CSS at-rule prelude', [';', '{', '}']);
		}
		$this->initAtRuleInternal($trimmedName, $trimmedPrelude, $style, $hasBlock, $maxBytes);
	}

	/** @internal Initializes already-validated at-rule fields without regex or fragment rescanning. */
	protected function initAtRuleInternal(
		string $atRuleName,
		string $prelude = '',
		?CSSStyleDeclaration $style = null,
		bool $hasBlock = false,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): void {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && (strlen($atRuleName) > $maxBytes || strlen($prelude) > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS at-rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->maxBytes = $maxBytes;
		$this->atRuleName = $atRuleName;
		$this->prelude = $prelude;
		$this->hasBlock = $hasBlock;
		$this->styleDeclaration = $style !== null && $style->getParentRule() !== null ? clone $style : $style;
		if ($this->styleDeclaration !== null) {
			$this->styleDeclaration->clampLimitsInternal($maxBytes, null, null, null, false);
			$this->styleDeclaration->setParentRule($this);
		}
	}

	/** @internal */
	public function getOwnedStyleInternal(): ?CSSStyleDeclaration
	{
		return $this->styleDeclaration;
	}

	protected function readCleanDescriptor(string $descriptor): string
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($this->styleDeclaration?->getPropertyValue($descriptor) ?? '');

		return ($raw !== '' && str_contains($raw, '/*'))
			? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw))
			: $raw;
	}

	public function getType(): int
	{
		return match (strtolower($this->atRuleName)) {
			'charset' => self::CHARSET_RULE,
			'import' => self::IMPORT_RULE,
			'namespace' => self::NAMESPACE_RULE,
			'font-face' => self::FONT_FACE_RULE,
			'page' => self::PAGE_RULE,
			'property' => self::PROPERTY_RULE,
			'counter-style' => self::COUNTER_STYLE_RULE,
			'font-feature-values' => self::FONT_FEATURE_VALUES_RULE,
			'font-palette-values' => self::FONT_PALETTE_VALUES_RULE,
			'position-try' => self::POSITION_TRY_RULE,
			'view-transition' => self::VIEW_TRANSITION_RULE,
			'color-profile' => self::COLOR_PROFILE_RULE,
			'custom-media' => self::CUSTOM_MEDIA_RULE,
			default => self::UNKNOWN_RULE,
		};
	}

	public function getName(): string
	{
		return $this->atRuleName;
	}

	public function getPrelude(): string
	{
		return $this->prelude;
	}

	public function getStyle(): ?CSSStyleDeclaration
	{
		return $this->styleDeclaration;
	}

	public function hasBlock(): bool
	{
		return $this->hasBlock;
	}

	public function getCssText(): string
	{
		$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
		if (!$this->hasBlock) {
			return '@' . $this->atRuleName . $prelude . ';';
		}

		return '@' . $this->atRuleName . $prelude . ' { ' . ($this->styleDeclaration?->getCssText() ?? '') . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->atRuleName,
			'prelude' => $this->prelude,
			'style' => $this->styleDeclaration,
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'prelude' => true,
			'style' => $this->styleDeclaration !== null,
			default => parent::__isset($name),
		};
	}

	public function __clone(): void
	{
		parent::__clone();
		if ($this->styleDeclaration !== null) {
			$this->styleDeclaration = clone $this->styleDeclaration;
			$this->styleDeclaration->setParentRule($this);
		}
	}
}
