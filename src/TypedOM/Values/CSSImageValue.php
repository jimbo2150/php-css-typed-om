<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents a CSS image value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
class CSSImageValue extends CSSStyleValue
{

    public function __construct(string $value)
    {
        $this->validateValue($value);
        parent::__construct($value);
    }

    public function getUrl(): string
    {
        return $this->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    private function isValidUrl($url): bool
    {
        return false !== filter_var($url, FILTER_VALIDATE_URL);
    }

    /**
     * Validates that the provided value is a valid CSS image value.
     *
     * @param string $value The CSS image value to validate
     *
     * @throws \InvalidArgumentException If the value is not a valid CSS image
     */
    protected static function validateValue(mixed $value): bool
    {
        $trimmedValue = trim($value);
        
        if ($trimmedValue === '') {
            throw new \InvalidArgumentException('CSS image value cannot be empty');
        }

		$isValid = self::isValidUrlFunction($trimmedValue) ||
			self::isValidGradient($trimmedValue) ||
			self::isValidImageSet($trimmedValue) ||
			self::isValidImageFunction($trimmedValue) ||
			self::isValidBareUrl($trimmedValue);

		if($isValid) {
			return true;
		}

        throw new \InvalidArgumentException(sprintf(
            'Invalid CSS image value: "%s". Must be a valid CSS url() function, gradient, image-set, or other valid CSS <image> type.',
            $value
        ));
    }

    /**
     * Checks if the value is a valid CSS url() function.
     */
    private static function isValidUrlFunction(string $value): bool
    {
        return preg_match('/^url\(\s*["\']?([^"\']+)["\']?\s*\)$/i', $value) === 1;
    }

    /**
     * Checks if the value is a valid CSS gradient.
     */
    private static function isValidGradient(string $value): bool
    {
        $gradientPatterns = [
            '/^linear-gradient\s*\(/i',
            '/^radial-gradient\s*\(/i',
            '/^conic-gradient\s*\(/i',
            '/^repeating-linear-gradient\s*\(/i',
            '/^repeating-radial-gradient\s*\(/i',
            '/^repeating-conic-gradient\s*\(/i'
        ];

        foreach ($gradientPatterns as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the value is a valid CSS image-set.
     */
    private static function isValidImageSet(string $value): bool
    {
        return preg_match('/^image-set\s*\(/i', $value) === 1;
    }

    /**
     * Checks if the value is a valid CSS image function (cross-fade, element, etc.).
     */
    private static function isValidImageFunction(string $value): bool
    {
        $imageFunctions = [
            'cross-fade',
            'element',
            'image',
            'paint'
        ];

        foreach ($imageFunctions as $function) {
            if (preg_match('/^' . preg_quote($function, '/') . '\s*\(/i', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the value is a valid bare URL (without url() wrapper).
     */
    private static function isValidBareUrl(string $value): bool
    {
        // Check for common URL patterns
        if (preg_match('/^https?:\/\//i', $value)) {
            return true;
        }
        
        // Check for relative URLs
        if (preg_match('/^[\w\-]+\.[\w\-]+/', $value)) {
            return true;
        }
        
        // Check for data URLs
        if (preg_match('/^data:/i', $value)) {
            return true;
        }

        return false;
    }

    public function clone(): static
    {
        return new self($this->value);
    }
}
