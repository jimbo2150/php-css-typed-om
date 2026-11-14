<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a single keyframe rule inside `@keyframes`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeyframeRule
 */
final class CSSKeyframeRule extends CSSStyleRule
{
	public function getKeyText(): string
	{
		return $this->getSelectorText();
	}

	/**
	 * @throws InvalidArgumentException When $keyText is not a valid keyframe selector list
	 */
	public function setKeyText(string $keyText): void
	{
		$keyText = trim($keyText);
		if ($keyText === '') {
			throw new InvalidArgumentException('Invalid keyframe selector text.');
		}
		CSSSyntaxScanner::assertValidFragment($keyText, 'Keyframe selector', [';', '{', '}']);
		if (!preg_match('/^(?:from|to|(?:\d+(?:\.\d+)?%)(?:\s*,\s*(?:from|to|\d+(?:\.\d+)?%))*)$/iD', $keyText)) {
			throw new InvalidArgumentException('Invalid keyframe selector text.');
		}
		$this->setSelectorText($keyText);
	}

	public function getType(): int
	{
		return self::KEYFRAME_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'keyText' ? $this->getKeyText() : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'keyText' && is_string($value)) {
			$this->setKeyText($value);
			return;
		}
		parent::__set($name, $value);
	}
}
