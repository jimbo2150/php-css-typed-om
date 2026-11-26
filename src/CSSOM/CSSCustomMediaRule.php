<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@custom-media` rule (Media Queries Level 5).
 *
 * @property-read string $name
 * @property-read MediaList|bool $query
 *
 * @see https://drafts.csswg.org/mediaqueries-5/#custom-mq
 */
final class CSSCustomMediaRule extends CSSAtRule
{
	private string $extensionName;
	private MediaList|bool $queryValue;
	private bool $initializing = true;

	/**
	 * @throws InvalidArgumentException When the `@custom-media` prelude is malformed
	 */
	public function __construct(
		string $prelude,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $browserErrorRecovery = false,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($prelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @custom-media rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@custom-media requires an extension name and query.');
		}
		if (!$browserErrorRecovery) {
			CSSSyntaxScanner::assertValidFragment($trimmed, '@custom-media prelude', [';', '{', '}']);
		}
		$clean = str_contains($trimmed, '/*')
			? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed))
			: $trimmed;
		if ($clean === '') {
			throw new InvalidArgumentException('@custom-media requires an extension name and query.');
		}
		if ($browserErrorRecovery && CSSSyntaxScanner::hasControlCharacters($clean)) {
			throw new InvalidArgumentException('@custom-media prelude contains disallowed control characters.');
		}

		$len = strlen($clean);
		$ident = CSSSyntaxScanner::decodeIdentAt($clean, 0, $len);
		if ($ident === null
			|| $ident['end'] >= $len
			|| !str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $clean[$ident['end']])
		) {
			throw new InvalidArgumentException('@custom-media requires a dashed identifier followed by a query.');
		}
		$rawName = substr($clean, 0, $ident['end']);
		$normalizedName = CSSStyleDeclaration::normalizePropertyName($rawName);
		if (!str_starts_with($normalizedName, '--') || strlen($normalizedName) < 3) {
			throw new InvalidArgumentException('@custom-media requires an extension name starting with "--".');
		}

		$rawQuery = CSSSyntaxScanner::trimCssWhitespace(substr($clean, $ident['end']));
		if ($rawQuery === '') {
			throw new InvalidArgumentException('@custom-media requires a media query list or boolean literal.');
		}

		$boolQuery = self::parseSoleBooleanLiteral($rawQuery);
		if ($boolQuery !== null) {
			$canonicalPrelude = $normalizedName . ' ' . ($boolQuery ? 'true' : 'false');
			parent::__construct('custom-media', $canonicalPrelude, null, false, $maxBytes);
			$this->extensionName = $normalizedName;
			$this->queryValue = $boolQuery;
			$this->initializing = false;
			return;
		}

		$mediaList = new MediaList(
			$rawQuery,
			function (string $mediaText): void {
				if (!$this->initializing) {
					$this->rebuildPrelude($mediaText);
				}
			},
			$maxBytes,
			$browserErrorRecovery,
		);
		if (count($mediaList) === 0) {
			throw new InvalidArgumentException('@custom-media requires a non-empty media query list.');
		}
		$canonicalPrelude = $normalizedName . ' ' . $mediaList->getMediaText();
		if ($maxBytes !== null && strlen($canonicalPrelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @custom-media rule exceeds maximum size of %d bytes.', $maxBytes));
		}

		parent::__construct('custom-media', $canonicalPrelude, null, false, $maxBytes);
		$this->extensionName = $normalizedName;
		$this->queryValue = $mediaList;
		$this->queryValue->setOwnerInternal($this);
		$this->initializing = false;
	}

	public function getNameText(): string
	{
		return $this->extensionName;
	}

	public function getQuery(): MediaList|bool
	{
		return $this->queryValue;
	}

	public function getType(): int
	{
		return self::CUSTOM_MEDIA_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->extensionName,
			'query' => $this->queryValue,
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'query' => true,
			default => parent::__isset($name),
		};
	}

	protected function syncChildLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		bool $syncDeclarations = false,
		?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH,
		?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
		bool $syncDepths = false,
	): void {
		if ($this->queryValue instanceof MediaList) {
			$this->queryValue->setMaxBytesInternal(CSSRule::minLimitInternal($this->queryValue->getMaxBytesInternal(), $maxBytes));
		}
	}

	public function __clone(): void
	{
		parent::__clone();
		if ($this->queryValue instanceof MediaList) {
			$this->queryValue = $this->queryValue->cloneWithCallback(function (string $mediaText): void {
				if (!$this->initializing) {
					$this->rebuildPrelude($mediaText);
				}
			});
			$this->queryValue->setOwnerInternal($this);
		}
	}

	private function rebuildPrelude(string $mediaText): void
	{
		if ($mediaText === '') {
			throw new InvalidArgumentException('@custom-media requires a non-empty media query list.');
		}
		$nextPrelude = $this->extensionName . ' ' . $mediaText;
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($nextPrelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @custom-media rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->prelude = $nextPrelude;
	}

	private static function parseSoleBooleanLiteral(string $rawQuery): ?bool
	{
		$len = strlen($rawQuery);
		$decoded = CSSSyntaxScanner::decodeIdentAt($rawQuery, 0, $len);
		if ($decoded === null || $decoded['end'] !== $len) {
			return null;
		}

		return match (strtolower($decoded['ident'])) {
			'true' => true,
			'false' => false,
			default => null,
		};
	}
}
