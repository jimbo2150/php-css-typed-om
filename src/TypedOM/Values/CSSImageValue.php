<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS image value such as `url(...)` or a CSS gradient function.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
final class CSSImageValue extends CSSStyleValue
{
	public readonly string $image;

	/**
	 * @throws InvalidArgumentException When $image is not a single balanced CSS image function or exceeds size limits
	 */
	public function __construct(string $image, ?CSSParserOptions $options = null)
	{
		$maxBytes = $options !== null ? $options->maxBytes : CSSParserOptions::DEFAULT_MAX_BYTES;
		if ($maxBytes !== null && strlen($image) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS image value exceeds maximum size of %d bytes.', $maxBytes));
		}
		$image = CSSSyntaxScanner::trimCssWhitespace($image);
		if ($image === '' || CSSSyntaxScanner::hasControlCharacters($image) || !CSSSyntaxScanner::isBalanced($image)) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		$open = strpos($image, '(');
		$fn = $open !== false ? CSSSyntaxScanner::decodeLeadingFunctionName($image, $open) : null;
		if ($fn === null || preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)$/D', $fn) !== 1) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		if ($fn === 'url') {
			$close = CSSSyntaxScanner::findMatchingUrlEnd($image, $open);
		} else {
			CSSSyntaxScanner::assertValidFragment($image, 'CSS image value', [';', '{', '}'], [';', '{', '}']);
			$close = CSSSyntaxScanner::findMatchingDelimiter($image, $open);
		}
		if ($close !== strlen($image) - 1) {
			throw new InvalidArgumentException('CSS image value cannot contain trailing tokens.');
		}
		$maxMathDepth = $options !== null ? $options->maxMathDepth : CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;
		if ($fn !== 'url' && $maxMathDepth !== null) {
			self::assertEmbeddedMathDepthWithinInternal($image, $maxMathDepth, $open + 1, $close);
		}

		$this->image = $image;
	}

	/**
	 * @internal Verifies embedded CSS math functions inside image expressions respect maxMathDepth.
	 */
	public static function assertEmbeddedMathDepthWithinInternal(
		string $image,
		int $maxMathDepth,
		?int $scanStart = null,
		?int $scanEnd = null,
	): void {
		$len = strlen($image);
		if ($scanStart === null || $scanEnd === null) {
			$open = strpos($image, '(');
			if ($open === false) {
				return;
			}
			$fn = CSSSyntaxScanner::decodeLeadingFunctionName($image, $open);
			if ($fn === 'url') {
				return;
			}
			$close = CSSSyntaxScanner::findMatchingDelimiter($image, $open, $len);
			if ($close === null) {
				return;
			}
			$start = $open + 1;
			$end = $close;
		} else {
			$start = $scanStart;
			$end = $scanEnd;
		}

		/** @var list<array{isMath: bool, depth: int}> $parenStack */
		$parenStack = [];
		$quote = '';

		for ($i = $start; $i < $end; $i++) {
			$ch = $image[$i];
			if ($quote !== '') {
				if ($ch === '\\') {
					$i++;
				} elseif ($ch === $quote) {
					$quote = '';
				}
				continue;
			}
			if ($ch === '"' || $ch === '\'') {
				$quote = $ch;
				continue;
			}
			if ($ch === '/' && ($i + 1) < $end && $image[$i + 1] === '*') {
				$cEnd = strpos($image, '*/', $i + 2);
				if ($cEnd === false || $cEnd >= $end) {
					break;
				}
				$i = $cEnd + 1;
				continue;
			}
			if ($ch === '(') {
				$top = $parenStack !== [] ? $parenStack[count($parenStack) - 1] : null;
				if ($top !== null && $top['isMath']) {
					$nextDepth = $top['depth'] + 1;
					if ($nextDepth > $maxMathDepth) {
						throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
					}
					$parenStack[] = ['isMath' => true, 'depth' => $nextDepth];
				} else {
					$parenStack[] = ['isMath' => false, 'depth' => 0];
				}
				continue;
			}
			if ($ch === ')') {
				if ($parenStack !== []) {
					array_pop($parenStack);
				}
				continue;
			}

			// Identifier start: ASCII letter, '_', '\\', '-', or non-ASCII
			if (($ch >= 'a' && $ch <= 'z')
				|| ($ch >= 'A' && $ch <= 'Z')
				|| $ch === '_'
				|| $ch === '\\'
				|| $ch === '-'
				|| ord($ch) >= 0x80
			) {
				$ident = CSSSyntaxScanner::decodeIdentAt($image, $i, $end);
				if ($ident === null) {
					continue;
				}
				$identEnd = $ident['end'];
				if ($identEnd < $end && $image[$identEnd] === '(') {
					$lower = strtolower($ident['ident']);
					if ($lower === 'url') {
						$urlClose = CSSSyntaxScanner::findMatchingUrlEnd($image, $identEnd, $end);
						if ($urlClose !== null) {
							$i = $urlClose;
							continue;
						}
					}
					$isMathFn = $lower === 'calc' || $lower === 'min' || $lower === 'max' || $lower === 'clamp';
					$top = $parenStack !== [] ? $parenStack[count($parenStack) - 1] : null;
					if ($top !== null && $top['isMath']) {
						$nextDepth = $top['depth'] + 1;
						if ($nextDepth > $maxMathDepth) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
						}
						$parenStack[] = ['isMath' => true, 'depth' => $nextDepth];
					} elseif ($isMathFn) {
						$fnDepth = $lower === 'calc' ? 0 : 1;
						if ($fnDepth > $maxMathDepth) {
							throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
						}
						$parenStack[] = ['isMath' => true, 'depth' => $fnDepth];
					} else {
						$parenStack[] = ['isMath' => false, 'depth' => 0];
					}
					$i = $identEnd;
					continue;
				}
				$i = $identEnd - 1;
			}
		}
	}

	public function getImage(): string
	{
		return $this->image;
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return $this->image;
	}

	public function __get(string $name): mixed
	{
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return false;
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
