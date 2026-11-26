<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@color-profile` rule (CSS Color Module Level 4 / Level 5).
 *
 * @property-read string $name
 * @property-read string $src
 * @property-read string $renderingIntent
 * @property-read string $components
 *
 * @see https://drafts.csswg.org/css-color-5/#the-csscolorprofilerule
 */
final class CSSColorProfileRule extends CSSAtRule
{
	/** @var array<string, true> */
	private const array RENDERING_INTENTS = [
		'relative-colorimetric' => true,
		'absolute-colorimetric' => true,
		'perceptual' => true,
		'saturation' => true,
	];

	/**
	 * @throws InvalidArgumentException When $name is not a valid `<dashed-ident>` or `device-cmyk`
	 */
	public function __construct(
		string $name,
		CSSStyleDeclaration $descriptors,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		$validatedName = self::validateProfileName($name, $maxBytes);
		parent::__construct('color-profile', $validatedName, $descriptors, true, $maxBytes);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getSrc(): string
	{
		return $this->readDescriptor('src');
	}

	public function getRenderingIntent(): string
	{
		$raw = $this->readDescriptor('rendering-intent');
		if ($raw === '') {
			return '';
		}
		$decoded = CSSSyntaxScanner::decodeIdentAt($raw, 0, strlen($raw));
		$candidate = ($decoded !== null && $decoded['end'] === strlen($raw))
			? strtolower($decoded['ident'])
			: strtolower($raw);

		return isset(self::RENDERING_INTENTS[$candidate]) ? $candidate : '';
	}

	public function getComponents(): string
	{
		return $this->readDescriptor('components');
	}

	public function getType(): int
	{
		return self::COLOR_PROFILE_RULE;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'src' => $this->getSrc(),
			'renderingIntent' => $this->getRenderingIntent(),
			'components' => $this->getComponents(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'name', 'src', 'renderingIntent', 'components' => true,
			default => parent::__isset($name),
		};
	}

	private function readDescriptor(string $descriptor): string
	{
		$raw = CSSSyntaxScanner::trimCssWhitespace($this->styleDeclaration?->getPropertyValue($descriptor) ?? '');
		if ($raw !== '' && str_contains($raw, '/*')) {
			$raw = CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($raw));
		}

		return $raw;
	}

	private static function validateProfileName(string $name, ?int $maxBytes): string
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($name) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @color-profile name exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($name);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@color-profile requires a profile name.');
		}
		if (CSSSyntaxScanner::hasControlCharacters($trimmed)) {
			throw new InvalidArgumentException('CSS @color-profile name contains disallowed control characters.');
		}
		$decoded = CSSSyntaxScanner::decodeIdentAt($trimmed, 0, strlen($trimmed));
		if ($decoded === null || $decoded['end'] !== strlen($trimmed)) {
			throw new InvalidArgumentException('Invalid @color-profile name: ' . $name);
		}
		if (strtolower($decoded['ident']) === 'device-cmyk') {
			return 'device-cmyk';
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($trimmed);
		if (!str_starts_with($normalized, '--') || strlen($normalized) < 3) {
			throw new InvalidArgumentException('@color-profile requires a dashed identifier starting with "--" or "device-cmyk".');
		}

		return $normalized;
	}
}
