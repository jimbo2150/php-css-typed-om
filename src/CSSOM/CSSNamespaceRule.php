<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@namespace` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNamespaceRule
 */
final class CSSNamespaceRule extends CSSAtRule
{
	public function __construct(string $prelude)
	{
		if (trim($prelude) === '') {
			throw new InvalidArgumentException('@namespace requires a namespace URI.');
		}
		parent::__construct('namespace', $prelude);
	}

	public function getPrefix(): string
	{
		$parts = CSSSyntaxScanner::splitTopLevelWhitespace($this->prelude);
		return count($parts) >= 2 ? $parts[0] : '';
	}

	public function getNamespaceURI(): string
	{
		$parts = CSSSyntaxScanner::splitTopLevelWhitespace($this->prelude);
		$uri = count($parts) >= 2 ? $parts[1] : ($parts[0] ?? '');
		if (strncasecmp($uri, 'url(', 4) === 0 && str_ends_with($uri, ')')) {
			$uri = trim(substr($uri, 4, -1));
		}
		if (strlen($uri) >= 2 && (($uri[0] === '"' && $uri[-1] === '"') || ($uri[0] === "'" && $uri[-1] === "'"))) {
			return substr($uri, 1, -1);
		}

		return $uri;
	}

	public function getType(): int
	{
		return self::NAMESPACE_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'prefix' => $this->getPrefix(),
			'namespaceURI' => $this->getNamespaceURI(),
			default => parent::__get($name),
		};
	}
}
