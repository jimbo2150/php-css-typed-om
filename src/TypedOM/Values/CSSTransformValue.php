<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents the transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformValue
 */
class CSSTransformValue extends CSSStyleValue
{
    /** @var CSSTransformComponent[] */
    private array $components;

    public function __construct(array $components)
    {
        $this->components = $components;
        parent::__construct('transform');
    }

    public function toString(): string
    {
        return implode(' ', array_map(fn($c) => $c->toString(), $this->components));
    }

    public function isValid(): bool
    {
        return true;
    }

    public function clone(): CSSStyleValue
    {
        return new self($this->components);
    }
}
