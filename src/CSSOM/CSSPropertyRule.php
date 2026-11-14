<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSPropertyRule extends CSSAtRule
{
	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
		parent::__construct('property', $name, $descriptors, true);
	}

	public function getNameText(): string { return $this->prelude; }
	public function getSyntax(): string { return trim($this->style?->getPropertyValue('syntax') ?? '', " \t\n\r\0\x0B\"'"); }
	public function getInherits(): bool { return strtolower($this->style?->getPropertyValue('inherits') ?? '') === 'true'; }
	public function getInitialValue(): string { return $this->style?->getPropertyValue('initial-value') ?? ''; }

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'syntax' => $this->getSyntax(),
			'inherits' => $this->getInherits(),
			'initialValue' => $this->getInitialValue(),
			default => parent::__get($name),
		};
	}
}
