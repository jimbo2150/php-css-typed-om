<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use OutOfBoundsException;

/**
 * Standalone, mutable CSS stylesheet model.
 *
 * Serialization outputs normalized CSS and is not an HTML `<style>` sanitizer;
 * callers embedding serialized CSS into HTML must apply HTML-context escaping.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleSheet
 */
final class CSSStyleSheet extends StyleSheet
{
	private CSSRuleList $cssRules;

	/** @var list<array{message: string, offset: int}> */
	private array $parseErrors = [];

	public function __construct(
		?string $href = null,
		string $title = '',
		?MediaList $media = null,
		?CSSRule $ownerRule = null,
		?StyleSheet $parentStyleSheet = null,
	) {
		parent::__construct($href, $title, $media, $ownerRule, $parentStyleSheet);
		$this->cssRules = new CSSRuleList($this);
	}

	/**
	 * Parses CSS text into a stylesheet, recovering from malformed rules and recording diagnostics.
	 */
	public static function parse(string $cssText, ?CSSParserOptions $options = null): self
	{
		return CSSParser::parseStylesheet($cssText, $options);
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRules;
	}

	/** @return list<array{message: string, offset: int}> */
	public function getParseErrors(): array
	{
		return $this->parseErrors;
	}

	public function getCssText(): string
	{
		$lines = [];
		foreach ($this->cssRules as $rule) {
			$lines[] = $rule->getCssText();
		}

		return implode("\n", $lines);
	}

	/**
	 * Validates and inserts a single CSS rule at $index without mutating on failure.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 * @throws InvalidArgumentException When $ruleText is malformed or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		if ($index < 0 || $index > count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$rule = CSSParser::parseSingleRule($ruleText, $options);
		$this->cssRules->insert($index, $rule);

		return $index;
	}

	/**
	 * Deletes the rule at $index.
	 *
	 * @throws OutOfBoundsException When $index is out of bounds
	 */
	public function deleteRule(int $index = 0): void
	{
		if ($index < 0 || $index >= count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$this->cssRules->remove($index);
	}

	/**
	 * Replaces stylesheet rules with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When $cssText exceeds configured parser resource limits
	 */
	public function replaceSync(string $cssText, ?CSSParserOptions $options = null): void
	{
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new InvalidArgumentException(sprintf('CSS input exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$replacement = self::parse($cssText, $options);
		$this->cssRules->replaceAll($replacement->getCssRules());
		$this->parseErrors = $replacement->getParseErrors();
	}

	public function recordParseError(string $message, int $offset): void
	{
		$this->parseErrors[] = ['message' => $message, 'offset' => $offset];
	}

	public function __toString(): string
	{
		return $this->getCssText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssRules' => $this->getCssRules(),
			'cssText' => $this->getCssText(),
			'parseErrors' => $this->getParseErrors(),
			default => parent::__get($name),
		};
	}
}
