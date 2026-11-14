<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

final class CSSPerspective extends CSSTransformComponent
{
	private CSSNumericValue $length;

	public function __construct(CSSNumericValue|string $lengthOrFunction, array|CSSNumericValue|string|null $values = null)
	{
		if (is_string($lengthOrFunction) && is_array($values)) {
			if (strtolower($lengthOrFunction) !== 'perspective' || count($values) !== 1) throw new InvalidArgumentException('perspective() requires one length.');
			$this->length = self::numericValue($values[0], 'px', ['length']);
			parent::__construct('perspective', [$this->length], false);
			return;
		}
		$this->length = self::numericValue($lengthOrFunction, 'px', ['length']);
		parent::__construct('perspective', [$this->length], false);
	}

	public function getLength(): CSSNumericValue { return $this->length; }
	public function toMatrix(): array
	{
		$matrix = self::identityMatrix();
		$perspective = self::scalar($this->length, 'length');
		if ($perspective <= 0) {
			throw new InvalidArgumentException('Perspective length must be positive.');
		}
		$matrix[11] = -1.0 / $perspective;
		return $matrix;
	}
	public function clone(): static { return new static($this->functionName, $this->values); }
	public function __get(string $name): mixed { return $name === 'length' ? $this->length : parent::__get($name); }
}
