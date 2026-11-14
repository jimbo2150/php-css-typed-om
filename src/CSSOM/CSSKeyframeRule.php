<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSKeyframeRule extends CSSStyleRule
{
	public function getKeyText(): string
	{
		return $this->getSelectorText();
	}

	public function setKeyText(string $keyText): void
	{
		$keyText = trim($keyText);
		if ($keyText === '' || !preg_match('/^(?:from|to|(?:\d+(?:\.\d+)?%)(?:\s*,\s*(?:from|to|\d+(?:\.\d+)?%))*)$/iD', $keyText)) {
			throw new \InvalidArgumentException('Invalid keyframe selector text.');
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
}
