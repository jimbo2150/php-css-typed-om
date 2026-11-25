<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
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

	/**
	 * @throws InvalidArgumentException When $selectorText is empty or invalid
	 */
	public function __construct(
		string $selectorText,
		?CSSStyleDeclaration $style = null,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		$selectorText = self::validateSelector($selectorText, $maxBytes, 'A CSS style rule requires a selector.');
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		parent::__construct('style', '', $maxBytes);
		$this->prelude = $selectorText;
		$this->selectorTextValue = $selectorText;
		if ($style === null) {
			$this->styleDeclaration = new CSSStyleProperties();
			$this->styleDeclaration->setMaxBytesInternal($maxBytes);
		} else {
			$this->styleDeclaration = CSSStyleProperties::fromDeclaration($style);
			$this->styleDeclaration->clampLimitsInternal($maxBytes, null, null, null, false);
		}
		$this->styleDeclaration->setParentRule($this);
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
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
	 * @throws InvalidArgumentException When $selectorText is empty or structurally invalid
	 */
	public function setSelectorText(string $selectorText): void
	{
		$selectorText = self::validateSelector($selectorText, $this->resolveMaxBytesInternal(), 'Invalid CSS selector text.');

		$this->selectorTextValue = $selectorText;
		$this->prelude = $selectorText;
	}

	private static function validateSelector(string $selectorText, ?int $maxBytes, string $emptyMessage): string
	{
		if ($maxBytes !== null && strlen($selectorText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum size of %d bytes.', $maxBytes));
		}
		$selectorText = CSSSyntaxScanner::trimCssWhitespace($selectorText);
		if ($selectorText === '') {
			throw new InvalidArgumentException($emptyMessage);
		}
		CSSSyntaxScanner::assertValidFragment($selectorText, 'CSS selector', [';', '{', '}']);
		$withoutComments = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($selectorText));
		if ($withoutComments === '' || str_starts_with($withoutComments, '@')) {
			throw new InvalidArgumentException($emptyMessage);
		}

		return $selectorText;
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
