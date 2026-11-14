<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Base class for statement and descriptor-block CSS at-rules.
 */
class CSSAtRule extends CSSRule
{
	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		protected readonly string $name,
		protected string $prelude = '',
		protected readonly ?CSSStyleDeclaration $style = null,
		protected readonly bool $hasBlock = false,
	) {
		$trimmedName = trim($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS at-rule name: ' . $name);
		}
		$this->prelude = trim($prelude);
		if ($this->prelude !== '') {
			CSSSyntaxScanner::assertValidFragment($this->prelude, 'CSS at-rule prelude', [';', '{', '}']);
		}
		$this->style?->setParentRule($this);
	}

	public function getType(): int
	{
		return match (strtolower($this->name)) {
			'charset' => self::CHARSET_RULE,
			'import' => self::IMPORT_RULE,
			'namespace' => self::NAMESPACE_RULE,
			'font-face' => self::FONT_FACE_RULE,
			'page' => self::PAGE_RULE,
			'property' => self::PROPERTY_RULE,
			'counter-style' => self::COUNTER_STYLE_RULE,
			'font-feature-values' => self::FONT_FEATURE_VALUES_RULE,
			'font-palette-values' => self::FONT_PALETTE_VALUES_RULE,
			'position-try' => self::POSITION_TRY_RULE,
			'view-transition' => self::VIEW_TRANSITION_RULE,
			default => self::UNKNOWN_RULE,
		};
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getPrelude(): string
	{
		return $this->prelude;
	}

	public function getStyle(): ?CSSStyleDeclaration
	{
		return $this->style;
	}

	public function getCssText(): string
	{
		$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
		if (!$this->hasBlock) {
			return '@' . $this->name . $prelude . ';';
		}

		return '@' . $this->name . $prelude . ' { ' . ($this->style?->getCssText() ?? '') . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->name,
			'prelude' => $this->prelude,
			'style' => $this->style,
			default => parent::__get($name),
		};
	}
}
