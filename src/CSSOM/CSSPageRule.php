<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Represents a CSS `@page` rule, including its page descriptors and nested `@margin` rules.
 *
 * @property string $selectorText
 * @property-read CSSPageDescriptors $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPageRule
 */
final class CSSPageRule extends CSSGroupingRule
{
	private CSSPageDescriptors $styleDescriptors;

	/** @param list<CSSMarginRule> $marginRules */
	public function __construct(
		string $selector,
		?CSSStyleDeclaration $style = null,
		array $marginRules = [],
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($selector) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @page selector exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($style !== null && $maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$selector = CSSSyntaxScanner::trimCssWhitespace($selector);

		$seenMarginRules = [];
		$marginBytes = 0;
		foreach ($marginRules as $rule) {
			if (!$rule instanceof CSSMarginRule) {
				throw new InvalidArgumentException('@page marginRules must contain only CSSMarginRule instances.');
			}
			if ($rule->getParentRule() !== null || $rule->getParentStyleSheet() !== null) {
				throw new InvalidArgumentException('A CSSRule that already has a parent cannot be inserted.');
			}
			$id = spl_object_id($rule);
			if (isset($seenMarginRules[$id])) {
				throw new InvalidArgumentException('Duplicate CSSMarginRule cannot be inserted into @page.');
			}
			$seenMarginRules[$id] = true;
			if ($maxBytes !== null) {
				$marginStats = CSSRuleList::inspectSubtree($rule, $maxBytes - $marginBytes);
				$marginBytes += $marginStats['bytes'];
			}
		}

		parent::__construct('page', $selector, $maxBytes);

		if ($style instanceof CSSPageDescriptors) {
			$descriptors = $style->getParentRule() !== null ? clone $style : $style;
		} else {
			$descriptors = new CSSPageDescriptors();
			if ($style !== null) {
				$descriptors->setLimitsInternal(
					self::minLimitInternal($maxBytes, $style->resolveMaxBytesInternal()),
					$style->resolveMaxDeclarationsInternal(),
					$style->resolveMaxDepthInternal(),
					$style->resolveMaxMathDepthInternal(),
					true,
				);
				$descriptors->copyDeclarationsFromInternal($style);
			}
		}
		$descriptors->clampLimitsInternal($maxBytes, null, null, null, false);
		$descriptors->setParentRule($this);
		$this->styleDescriptors = $descriptors;

		if ($marginRules !== []) {
			foreach ($marginRules as $rule) {
				$rule->clampMaxBytesInternal($maxBytes);
				$this->getCssRules()->appendDetached($rule);
			}
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
		$this->styleDescriptors->clampLimitsInternal(
			$maxBytes,
			$syncDeclarations ? $maxDeclarations : null,
			$maxDepth,
			$maxMathDepth,
			$syncDepths,
		);
	}

	public function getType(): int
	{
		return self::PAGE_RULE;
	}

	public function getStyle(): CSSPageDescriptors
	{
		return $this->styleDescriptors;
	}

	/** @return list<CSSMarginRule> */
	public function getMarginRules(): array
	{
		$rules = [];
		foreach ($this->getCssRules()->getRulesRaw() as $rule) {
			if ($rule instanceof CSSMarginRule) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	public function getSelectorText(): string
	{
		return $this->prelude;
	}

	/**
	 * @throws InvalidArgumentException When $selector contains disallowed syntax or exceeds size limits
	 */
	public function setSelectorText(string $selector): void
	{
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($selector) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @page selector exceeds maximum size of %d bytes.', $maxBytes));
		}
		$selector = CSSSyntaxScanner::trimCssWhitespace($selector);
		if ($selector !== '') {
			CSSSyntaxScanner::assertValidFragment($selector, '@page selector', [';', '{', '}']);
		}
		$this->prelude = $selector;
	}

	/**
	 * Validates and inserts a page margin rule into this `@page` rule.
	 *
	 * @throws LogicException When the `@page` rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws InvalidArgumentException When $ruleText is not a valid `@margin` rule or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		$sheet = $this->getParentStyleSheet();
		if ($sheet === null) {
			throw new LogicException('A detached @page rule cannot insert margin rules.');
		}
		if ($index < 0 || $index > count($this->getCssRules())) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$effectiveOptions = $sheet->resolveParserOptionsInternal($options);
		$marginRule = CSSParser::parseSingleMarginRule(
			$ruleText,
			$effectiveOptions,
			$this->getNestingDepth(),
			$sheet->getTotalRuleCount(),
			$sheet->getTotalDeclarationCount(),
		);
		$this->getCssRules()->insertWithOptionsInternal($index, $marginRule, $effectiveOptions);

		return $index;
	}

	public function getCssText(): string
	{
		if ($this->getCssRules()->getRulesRaw() === []) {
			$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
			return '@page' . $prelude . ' { ' . $this->styleDescriptors->getCssText() . ' }';
		}

		return CSSGroupingRule::serializeTreeIteratively($this);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->styleDescriptors = clone $this->styleDescriptors;
		$this->styleDescriptors->setParentRule($this);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'selectorText' => $this->getSelectorText(),
			'style' => $this->getStyle(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'selectorText', 'style' => true,
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
