<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@view-transition` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSViewTransitionRule
 */
final class CSSViewTransitionRule extends CSSAtRule
{
	public function __construct(CSSStyleDeclaration $descriptors)
	{
		parent::__construct('view-transition', '', $descriptors, true);
	}

	public function getNavigation(): string
	{
		return $this->style?->getPropertyValue('navigation') ?? '';
	}

	/** @return list<string> */
	public function getTypes(): array
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($this->style?->getPropertyValue('types') ?? '');
		if ($raw === '' || strtolower($raw) === 'none') {
			return [];
		}

		return CSSSyntaxScanner::splitTopLevelWhitespace($raw);
	}

	public function getType(): int
	{
		return self::VIEW_TRANSITION_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'navigation' => $this->getNavigation(),
			'types' => $this->getTypes(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'navigation', 'types' => true,
			default => parent::__isset($name),
		};
	}
}
