<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

class CSSColorValue extends CSSStyleValue
{

    public function __construct(string $colorString)
    {
        $this->initializeSimpleValue($colorString, 'color');
        parent::__construct('color');
    }

    public static function parse(string $cssText): self
    {
        $cssText = trim($cssText);

        // Named colors (simplified list for demonstration)
        $namedColors = [
            'red', 'green', 'blue', 'black', 'white', 'transparent',
            'currentColor', 'inherit', 'initial', 'unset',
        ];
        if (in_array(strtolower($cssText), $namedColors, true)) {
            return new self($cssText);
        }

        // Hex colors
        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $cssText)) {
            return new self($cssText);
        }

        // rgb() or rgba()
        if (preg_match('/^rgb\(/i', $cssText) || preg_match('/^rgba\(/i', $cssText)) {
            return new self($cssText);
        }

        // hsl() or hsla()
        if (preg_match('/^hsl\(/i', $cssText) || preg_match('/^hsla\(/i', $cssText)) {
            return new self($cssText);
        }

        throw new \InvalidArgumentException('Invalid CSS color value: '.$cssText);
    }

    public function toString(): string
    {
        return $this->getValue();
    }

    public function isValid(): bool
    {
        $value = strtolower(trim($this->getValue()));
        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
            return true;
        }
        if (str_starts_with($value, 'rgb(') || str_starts_with($value, 'rgba(')) {
            // A full implementation would parse the contents of rgb()/rgba()
            return true;
        }
        if (str_starts_with($value, 'hsl(') || str_starts_with($value, 'hsla(')) {
            // A full implementation would parse the contents of hsl()/hsla()
            return true;
        }

        return false;
    }

    public function clone(): CSSStyleValue
    {
        return new self($this->getValue());
    }
}
