<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a single keyframe rule inside `@keyframes`.
 *
 * @property string $keyText
 * @property-read CSSStyleProperties $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeyframeRule
 */
final class CSSKeyframeRule extends CSSRule
{
	private string $keyTextValue;
	private CSSStyleProperties $styleProperties;

	/**
	 * @throws InvalidArgumentException When $keyText is not a valid keyframe selector list
	 */
	public function __construct(string $keyText, ?CSSStyleDeclaration $style = null)
	{
		$this->setKeyText($keyText);
		$this->styleProperties = CSSStyleProperties::fromDeclaration($style);
		$this->styleProperties->setParentRule($this);
	}

	public function getKeyText(): string
	{
		return $this->keyTextValue;
	}

	/**
	 * @throws InvalidArgumentException When $keyText is not a valid keyframe selector list or exceeds size limits
	 */
	public function setKeyText(string $keyText): void
	{
		if (strlen($keyText) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('Keyframe selector exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$keyText = CSSSyntaxScanner::trimCssWhitespace($keyText);
		if ($keyText === '') {
			throw new InvalidArgumentException('Invalid keyframe selector text.');
		}
		CSSSyntaxScanner::assertValidFragment($keyText, 'Keyframe selector', [';', '{', '}']);
		if (!preg_match('/^(?:from|to|(?:\d+(?:\.\d+)?|\.\d+)%)(?:[ \t\n\r\f]*,[ \t\n\r\f]*(?:from|to|(?:\d+(?:\.\d+)?|\.\d+)%))*$/iD', $keyText)) {
			throw new InvalidArgumentException('Invalid keyframe selector text.');
		}
		foreach (explode(',', $keyText) as $part) {
			$token = CSSSyntaxScanner::trimCssWhitespace($part);
			if (str_ends_with($token, '%')) {
				$percentage = (float) substr($token, 0, -1);
				if (!is_finite($percentage) || $percentage < 0.0 || $percentage > 100.0) {
					throw new InvalidArgumentException('Keyframe percentage must be between 0% and 100%.');
				}
			}
		}

		$this->keyTextValue = $keyText;
	}

	public function getStyle(): CSSStyleProperties
	{
		return $this->styleProperties;
	}

	public function getType(): int
	{
		return self::KEYFRAME_RULE;
	}

	public function getCssText(): string
	{
		return $this->keyTextValue . ' { ' . $this->styleProperties->getCssText() . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'keyText' => $this->getKeyText(),
			'style' => $this->getStyle(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'keyText' || $name === 'style' || parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'keyText' && is_string($value)) {
			$this->setKeyText($value);
			return;
		}
		parent::__set($name, $value);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->styleProperties = clone $this->styleProperties;
		$this->styleProperties->setParentRule($this);
	}
}
