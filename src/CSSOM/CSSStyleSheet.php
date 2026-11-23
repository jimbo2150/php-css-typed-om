<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use OutOfBoundsException;

/**
 * Standalone, mutable CSS stylesheet model.
 *
 * Serialization outputs normalized CSS and is not an HTML `<style>` sanitizer;
 * callers embedding serialized CSS into HTML must apply HTML-context escaping
 * (or enable `CSSRenderOptions::$escapeHtmlStyleTag` when embedding inside `<style>`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleSheet
 */
final class CSSStyleSheet extends StyleSheet
{
	private CSSRuleList $cssRules;

	/** @var list<array{message: string, offset: int}> */
	private array $parseErrors = [];

	private readonly ?string $baseURL;

	private int $totalRuleCount = 0;

	private ?int $maxErrors = null;

	private bool $errorsTruncated = false;

	public function __construct(
		?string $href = null,
		string $title = '',
		MediaList|string|null $media = null,
		?CSSRule $ownerRule = null,
		?StyleSheet $parentStyleSheet = null,
		bool $disabled = false,
		?string $baseURL = null,
	) {
		if ($baseURL !== null && CSSSyntaxScanner::hasControlCharacters($baseURL)) {
			throw new InvalidArgumentException('CSSStyleSheet baseURL cannot contain control characters.');
		}
		parent::__construct($href, $title, $media, $ownerRule, $parentStyleSheet, $disabled);
		$this->baseURL = $baseURL;
		$this->cssRules = new CSSRuleList($this);
	}

	public function getBaseURL(): ?string
	{
		return $this->baseURL;
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

	/**
	 * @internal
	 */
	public function configureErrorLimit(?int $maxErrors): void
	{
		$this->maxErrors = $maxErrors;
		$this->errorsTruncated = false;
	}

	/**
	 * @internal
	 */
	public function getTotalRuleCount(): int
	{
		return $this->totalRuleCount;
	}

	/**
	 * @internal
	 */
	public function adjustTotalRuleCount(int $delta): void
	{
		$this->totalRuleCount = max(0, $this->totalRuleCount + $delta);
	}

	public function getCssText(): string
	{
		$rules = $this->cssRules->getRulesRaw();
		if ($rules === []) {
			return '';
		}

		$lines = [];
		foreach ($rules as $rule) {
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

		$rule = CSSParser::parseSingleRule($ruleText, $options, 0, $this->totalRuleCount);
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
		$replacement = CSSParser::parseStylesheet($cssText, $options, true);
		$this->cssRules->replaceAll($replacement->getCssRules());
		$this->parseErrors = $replacement->getParseErrors();
	}

	public function recordParseError(string $message, int $offset): void
	{
		if ($this->maxErrors !== null && count($this->parseErrors) >= $this->maxErrors) {
			if (!$this->errorsTruncated) {
				$this->errorsTruncated = true;
				$this->parseErrors[] = [
					'message' => sprintf('Maximum parse error limit of %d exceeded; further diagnostics suppressed.', $this->maxErrors),
					'offset' => $offset,
				];
			}

			return;
		}

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
			'baseURL' => $this->getBaseURL(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'cssRules', 'cssText', 'parseErrors' => true,
			'baseURL' => $this->baseURL !== null,
			default => parent::__isset($name),
		};
	}
}
