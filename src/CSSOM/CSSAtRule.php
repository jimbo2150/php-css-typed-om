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
	protected readonly string $atRuleName;
	protected string $prelude;
	protected readonly ?CSSStyleDeclaration $styleDeclaration;

	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		string $name,
		string $prelude = '',
		?CSSStyleDeclaration $style = null,
		protected readonly bool $hasBlock = false,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && (strlen($name) > $maxBytes || strlen($prelude) > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS at-rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->maxBytes = $maxBytes;
		$trimmedName = CSSSyntaxScanner::trimCssWhitespace($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS at-rule name: ' . $name);
		}
		$this->atRuleName = $trimmedName;
		$this->prelude = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($this->prelude !== '') {
			CSSSyntaxScanner::assertValidFragment($this->prelude, 'CSS at-rule prelude', [';', '{', '}']);
		}
		$this->styleDeclaration = $style !== null && $style->getParentRule() !== null ? clone $style : $style;
		if ($this->styleDeclaration !== null) {
			$this->styleDeclaration->clampLimitsInternal($maxBytes, null, null, null, false);
			$this->styleDeclaration->setParentRule($this);
		}
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
		if ($this->styleDeclaration === null) {
			return;
		}
		$this->styleDeclaration->clampLimitsInternal(
			$maxBytes,
			$syncDeclarations ? $maxDeclarations : null,
			$maxDepth,
			$maxMathDepth,
			$syncDepths,
		);
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
