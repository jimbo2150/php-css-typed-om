<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Represents a CSS `@view-transition` rule.
 *
 * @property-read string $navigation
 * @property-read list<string> $types
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSViewTransitionRule
 */
final class CSSViewTransitionRule extends CSSAtRule
{
	public function __construct(CSSStyleDeclaration $descriptors, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		$this->initAtRuleInternal('view-transition', '', $descriptors, true, $maxBytes);
	}

	public function validatesDescriptorPropertyValuesInternal(): bool
	{
		return false;
	}

	public function getNavigation(): string
	{
		return $this->readCleanDescriptor('navigation');
	}

	/** @return list<string> */
	public function getTypes(): array
	{
		$raw = $this->readCleanDescriptor('types');
		if ($raw === '') {
			return [];
		}
		if (str_contains($raw, '\\')) {
			$decoded = CSSSyntaxScanner::decodeIdentAt($raw, 0, strlen($raw));
			if ($decoded !== null && $decoded['end'] === strlen($raw) && strtolower($decoded['ident']) === 'none') {
				return [];
			}
		} elseif (strtolower($raw) === 'none') {
			return [];
		}

		return CSSSyntaxScanner::splitTopLevelWhitespace($raw, 0, null, CSSStyleValue::MAX_LIST_ITEMS);
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
