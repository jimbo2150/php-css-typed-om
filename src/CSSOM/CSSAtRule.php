<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

class CSSAtRule extends CSSRule
{
	public function __construct(
		protected readonly string $name,
		protected string $prelude = '',
		protected readonly ?CSSStyleDeclaration $style = null,
		protected readonly bool $hasBlock = false,
	) {
	}

	public function getType(): int
	{
		return match (strtolower($this->name)) {
			'import' => self::IMPORT_RULE,
			'namespace' => self::NAMESPACE_RULE,
			'font-face' => self::FONT_FACE_RULE,
			'page' => self::PAGE_RULE,
			'property' => self::PROPERTY_RULE,
			'counter-style' => self::COUNTER_STYLE_RULE,
			'font-feature-values' => self::FONT_FEATURE_VALUES_RULE,
			'font-palette-values' => self::FONT_PALETTE_VALUES_RULE,
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

	public function __set(string $name, mixed $value): void
	{
		throw new \Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
