<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSelectorList;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use LogicException;
use OutOfBoundsException;

/**
 * Represents a CSS style rule with a selector, declaration block, and optional nested rules.
 *
 * @property string $selectorText
 * @property-read CSSStyleProperties $style
 * @property-read StylePropertyMap $styleMap
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleRule
 */
class CSSStyleRule extends CSSGroupingRule
{
	private string $selectorTextValue;
	private CSSStyleProperties $styleDeclaration;
	private ?StylePropertyMap $cachedStyleMap = null;
	private ?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH;
	private bool $allowRelativeContext;
	private bool $hasLeadingCombinator;

	/**
	 * @throws InvalidArgumentException When $selectorText is empty or invalid
	 */
	public function __construct(
		string $selectorText,
		?CSSStyleDeclaration $style = null,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $allowRelative = false,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		$parsedSelector = self::validateSelector(
			$selectorText,
			$maxBytes,
			CSSParserOptions::DEFAULT_MAX_DEPTH,
			$allowRelative,
			false,
			'A CSS style rule requires a selector.',
		);
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		parent::__construct('style', '', $maxBytes);
		$canonical = $parsedSelector->getCssText();
		$this->prelude = $canonical;
		$this->selectorTextValue = $canonical;
		$this->allowRelativeContext = $allowRelative;
		$this->hasLeadingCombinator = $parsedSelector->hasLeadingCombinator();
		if ($style === null) {
			$this->styleDeclaration = new CSSStyleProperties();
			$this->styleDeclaration->setMaxBytesInternal($maxBytes);
		} else {
			$this->styleDeclaration = CSSStyleProperties::fromDeclaration($style);
			$this->styleDeclaration->clampLimitsInternal($maxBytes, null, null, null, false);
		}
		$this->styleDeclaration->setParentRule($this);
	}

	/**
	 * @internal Fast parser constructor enforcing stylesheet depth and browser comment-control-byte policy.
	 */
	public static function createParsedInternal(
		string $selectorText,
		?CSSStyleDeclaration $style,
		CSSParserOptions $options,
		bool $allowRelative,
		bool $allowCommentControlBytes,
	): self {
		self::assertValidMaxBytesInternal($options->maxBytes);
		$parsedSelector = self::validateSelector(
			$selectorText,
			$options->maxBytes,
			$options->maxDepth,
			$allowRelative,
			$allowCommentControlBytes,
			'A CSS style rule requires a selector.',
		);
		if ($style !== null && $options->maxBytes !== null) {
			$style->assertContentBytesWithinInternal($options->maxBytes);
		}
		$canonical = $parsedSelector->getCssText();
		$rule = new self('a', $style, $options->maxBytes, false);
		$rule->prelude = $canonical;
		$rule->selectorTextValue = $canonical;
		$rule->maxDepth = $options->maxDepth;
		$rule->allowRelativeContext = $allowRelative;
		$rule->hasLeadingCombinator = $parsedSelector->hasLeadingCombinator();

		return $rule;
	}

	/** @internal */
	public function hasLeadingCombinatorInternal(): bool
	{
		return $this->hasLeadingCombinator;
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
		if ($syncDepths) {
			$this->maxDepth = $maxDepth;
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
		return self::STYLE_RULE;
	}

	public function getSelectorText(): string
	{
		return $this->selectorTextValue;
	}

	/**
	 * @throws InvalidArgumentException When $selectorText is empty or invalid per Selectors Level 4
	 */
	public function setSelectorText(string $selectorText): void
	{
		$parsedSelector = self::validateSelector(
			$selectorText,
			$this->resolveMaxBytesInternal(),
			$this->resolveMaxDepthInternal(),
			$this->shouldAllowRelativeSelectors(),
			false,
			'Invalid CSS selector text.',
		);
		$canonical = $parsedSelector->getCssText();

		$this->selectorTextValue = $canonical;
		$this->prelude = $canonical;
		$this->hasLeadingCombinator = $parsedSelector->hasLeadingCombinator();
	}

	private function resolveMaxDepthInternal(): ?int
	{
		$sheetDepth = $this->getParentStyleSheet()?->getMaxDepthInternal();
		return self::minLimitInternal($this->maxDepth, $sheetDepth);
	}

	private function shouldAllowRelativeSelectors(): bool
	{
		$parent = $this->getParentRule();
		if ($parent === null) {
			return $this->getParentStyleSheet() === null && $this->allowRelativeContext;
		}

		$visited = [];
		while ($parent !== null) {
			if ($parent instanceof self || $parent instanceof CSSScopeRule) {
				return true;
			}
			$id = spl_object_id($parent);
			if (isset($visited[$id])) {
				return false;
			}
			$visited[$id] = true;
			$parent = $parent->getParentRule();
		}

		return false;
	}

	private static function validateSelector(
		string $selectorText,
		?int $maxBytes,
		?int $maxDepth,
		bool $allowRelative,
		bool $allowCommentControlBytes,
		string $emptyMessage,
	): CSSSelectorList {
		if ($maxBytes !== null && strlen($selectorText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($selectorText);
		if ($trimmed === '') {
			throw new InvalidArgumentException($emptyMessage);
		}
		if (!$allowCommentControlBytes) {
			CSSSyntaxScanner::assertValidFragment($trimmed, 'CSS selector', [';', '{', '}']);
		}
		$withoutComments = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed));
		if ($withoutComments === '' || str_starts_with($withoutComments, '@')) {
			throw new InvalidArgumentException($emptyMessage);
		}

		return CSSSelectorList::parseForStyleRuleInternal(
			$trimmed,
			new CSSParserOptions(maxBytes: $maxBytes, maxDepth: $maxDepth),
			$allowRelative,
			$allowCommentControlBytes,
		);
	}

	public function getStyle(): CSSStyleProperties
	{
		return $this->styleDeclaration;
	}

	public function getStyleMap(): StylePropertyMap
	{
		return $this->cachedStyleMap ??= new StylePropertyMap($this->styleDeclaration);
	}

	/**
	 * Validates and inserts a nested rule into this style rule.
	 *
	 * @throws LogicException When the style rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws InvalidArgumentException When $ruleText is invalid or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		$sheet = $this->getParentStyleSheet();
		if ($sheet === null) {
			throw new LogicException('A detached style rule cannot insert nested rules.');
		}
		if ($index < 0 || $index > count($this->getCssRules())) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$effectiveOptions = $sheet->resolveParserOptionsInternal($options);
		$rule = CSSParser::parseSingleNestedRule(
			$ruleText,
			$effectiveOptions,
			$this->getNestingDepth(),
			$sheet->getTotalRuleCount(),
			$sheet->getTotalDeclarationCount(),
		);
		$this->getCssRules()->insertWithOptionsInternal($index, $rule, $effectiveOptions);

		return $index;
	}

	public function getCssText(): string
	{
		if ($this->getCssRules()->getRulesRaw() === []) {
			return $this->selectorTextValue . ' { ' . $this->styleDeclaration->getCssText() . ' }';
		}

		return CSSGroupingRule::serializeTreeIteratively($this);
	}

	public function __clone(): void
	{
		$this->maxDepth = $this->resolveMaxDepthInternal();
		parent::__clone();
		$this->styleDeclaration = clone $this->styleDeclaration;
		$this->styleDeclaration->setParentRule($this);
		$this->cachedStyleMap = null;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'selectorText' => $this->getSelectorText(),
			'style' => $this->getStyle(),
			'styleMap' => $this->getStyleMap(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'selectorText', 'style', 'styleMap' => true,
			default => parent::__isset($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'selectorText' && is_string($value)) {
			$this->setSelectorText($value);
			return;
		}
		parent::__set($name, $value);
	}
}
