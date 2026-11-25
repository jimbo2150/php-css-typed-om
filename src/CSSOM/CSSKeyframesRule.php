<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Represents a CSS `@keyframes` rule.
 *
 * @property string $name
 * @property-read int $length
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeyframesRule
 */
final class CSSKeyframesRule extends CSSGroupingRule
{
	/**
	 * @throws InvalidArgumentException When the keyframes name is empty or invalid
	 */
	public function __construct(
		string $name,
		string $atRuleName = 'keyframes',
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		$validated = self::validateName($name, $maxBytes);
		parent::__construct($atRuleName, $validated, $maxBytes);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	/**
	 * @throws InvalidArgumentException When $name is empty, structurally invalid, or exceeds size limits
	 */
	public function setNameText(string $name): void
	{
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @keyframes name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->prelude = self::validateName($name);
	}

	public function getType(): int
	{
		return self::KEYFRAMES_RULE;
	}

	public function getLength(): int
	{
		return count($this->getCssRules());
	}

	public function appendRule(string $ruleText, ?CSSParserOptions $options = null): void
	{
		$this->insertRule($ruleText, count($this->getCssRules()), $options);
	}

	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		if ($this->getParentStyleSheet() === null) {
			throw new LogicException('A detached keyframes rule cannot insert keyframes.');
		}
		if ($index < 0 || $index > count($this->getCssRules())) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$sheet = $this->getParentStyleSheet();
		$effectiveOptions = $sheet->resolveParserOptionsInternal($options);
		$keyframe = CSSParser::parseSingleKeyframeRule(
			$ruleText,
			$effectiveOptions,
			$this->getNestingDepth(),
			$sheet->getTotalRuleCount(),
			$sheet->getTotalDeclarationCount(),
		);
		$this->getCssRules()->insertWithOptionsInternal($index, $keyframe, $effectiveOptions);

		return $index;
	}

	public function deleteRule(int|string $keyText): void
	{
		if ($this->getParentStyleSheet() === null) {
			throw new LogicException('A detached CSSGroupingRule cannot delete rules.');
		}
		if (is_int($keyText)) {
			parent::deleteRule($keyText);
			return;
		}

		$target = CSSSyntaxScanner::trimCssWhitespace($keyText);
		foreach ($this->getCssRules() as $index => $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), $target) === 0) {
				$this->getCssRules()->remove($index);
				return;
			}
		}
	}

	public function findRule(string $keyText): ?CSSKeyframeRule
	{
		$target = CSSSyntaxScanner::trimCssWhitespace($keyText);
		foreach ($this->getCssRules() as $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), $target) === 0) {
				return $rule;
			}
		}
		return null;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'length' => $this->getLength(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'name' || $name === 'length' || parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'name' && is_string($value)) {
			$this->setNameText($value);
			return;
		}
		parent::__set($name, $value);
	}

	private static function validateName(string $name, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): string
	{
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Keyframes name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$name = CSSSyntaxScanner::trimCssWhitespace($name);
		if ($name === '') {
			throw new InvalidArgumentException('Keyframes name cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($name, 'Keyframes name', [';', '{', '}', ',']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($name)) === '') {
			throw new InvalidArgumentException('Keyframes name cannot be empty.');
		}

		return $name;
	}
}
