<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use OutOfBoundsException;

/** A standalone, mutable CSS stylesheet model. */
final class CSSStyleSheet extends StyleSheet
{
	private CSSRuleList $cssRules;

	/** @var list<array{message: string, offset: int}> */
	private array $parseErrors = [];

	public function __construct(?string $href = null, string $title = '', ?MediaList $media = null)
	{
		parent::__construct($href, $title, $media);
		$this->cssRules = new CSSRuleList($this);
	}

	public static function parse(string $cssText): self
	{
		return CSSParser::parseStylesheet($cssText);
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRules;
	}

	public function getParseErrors(): array
	{
		return $this->parseErrors;
	}

	public function getCssText(): string
	{
		$rules = array_map(static fn (CSSRule $rule): string => $rule->getCssText(), iterator_to_array($this->cssRules));
		return implode("\n", $rules);
	}

	public function insertRule(string $ruleText, int $index = 0): int
	{
		if ($index < 0 || $index > count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$rule = CSSParser::parseSingleRule($ruleText);
		$this->cssRules->insert($index, $rule);

		return $index;
	}

	public function deleteRule(int $index = 0): void
	{
		if ($index < 0 || $index >= count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$this->cssRules->remove($index);
	}

	public function replaceSync(string $cssText): void
	{
		$replacement = self::parse($cssText);
		while (count($this->cssRules) > 0) {
			$this->cssRules->remove(count($this->cssRules) - 1);
		}
		foreach ($replacement->getCssRules() as $rule) {
			$this->cssRules->append($rule);
		}
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
			'href' => $this->getHref(),
			'title' => $this->getTitle(),
			'disabled' => $this->isDisabled(),
			'media' => $this->getMedia(),
			default => throw new \Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'disabled' && is_bool($value)) {
			$this->setDisabled($value);
			return;
		}
		throw new \Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
