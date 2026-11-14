<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@container` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSContainerRule
 */
final class CSSContainerRule extends CSSConditionRule
{
	public function __construct(string $conditionText)
	{
		parent::__construct('container', $conditionText);
	}

	public function getType(): int
	{
		return self::CONTAINER_RULE;
	}

	public function getContainerName(): string
	{
		[$name] = $this->splitPrelude();
		return $name;
	}

	public function getContainerQuery(): string
	{
		[, $query] = $this->splitPrelude();
		return $query;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'containerName' => $this->getContainerName(),
			'containerQuery' => $this->getContainerQuery(),
			default => parent::__get($name),
		};
	}

	/** @return array{string, string} */
	private function splitPrelude(): array
	{
		$prelude = trim($this->prelude);
		$paren = CSSSyntaxScanner::findTopLevel($prelude, '(');
		if ($paren === null) {
			return [$prelude, ''];
		}

		$name = trim(substr($prelude, 0, $paren));
		$query = trim(substr($prelude, $paren));
		return [$name, $query];
	}
}
