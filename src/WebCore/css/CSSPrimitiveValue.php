<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css;

use Jimbo2150\PhpCssTypedOm\Css\ClassType; // Assuming this enum exists
use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;
use Jimbo2150\PhpCssTypedOm\Css\CSSValue;
use Jimbo2150\PhpCssTypedOm\Css\CSSValueID;
use Jimbo2150\PhpCssTypedOm\WebCore\css\values\SerializationContext; // Added use
use Jimbo2150\PhpCssTypedOm\WTF\wtf\Hasher; // Added use

/**
 * Represents a single, primitive CSS value, such as a length, percentage, color, string, or identifier.
 *
 * This class is designed to be immutable after creation.
 * Use the static factory methods (create*, from*) to instantiate.
 */
class CSSPrimitiveValue extends CSSValue
{
	// Use readonly properties for immutability (PHP 8.1+)
	// Use constructor property promotion (PHP 8.0+)
	private function __construct(
		public readonly CSSUnitType $unitType,
		private readonly float|string|int|CSSCalcValue|CSSAttrValue|CSSValueID|CSSPropertyID $value,
		// Removed m_hasCachedCSSText, m_isImplicitInitialValue - manage state differently if needed
	) {
		// Call parent constructor, assuming CSSPrimitiveValue is a specific ClassType
		parent::__construct(ClassType::PrimitiveValue); // Adjust ClassType::PrimitiveValue as needed
	}

	// --- Static Factory Methods ---

	/**
	 * Creates a CSSPrimitiveValue representing a numeric value with a unit.
	 *
	 * @param float       $value    the numeric value
	 * @param CSSUnitType $unitType the unit type (must be numeric)
	 *
	 * @throws \InvalidArgumentException if the unit type is not valid for numeric conversion
	 */
	public static function createNumeric(float $value, CSSUnitType $unitType): self
	{
		if (!self::isValidCSSUnitTypeForDoubleConversion($unitType)) {
			throw new \InvalidArgumentException("Unit type '{$unitType->name}' is not valid for a numeric value.");
		}

		// Ensure integer types store integers if possible/desired, though float covers it.
		// If strict integer storage is needed, add checks and store as int.
		return new self($unitType, $value);
	}

	/**
	 * Creates a CSSPrimitiveValue representing an integer value with a unit.
	 *
	 * @param int         $value    the integer value
	 * @param CSSUnitType $unitType the unit type (must be numeric, typically CSS_INTEGER)
	 *
	 * @throws \InvalidArgumentException if the unit type is not valid for numeric conversion
	 */
	public static function createInteger(int $value, CSSUnitType $unitType): self
	{
		// Often CSS_INTEGER is the target, but other numeric types might accept integers.
		if (!self::isValidCSSUnitTypeForDoubleConversion($unitType)) {
			throw new \InvalidArgumentException("Unit type '{$unitType->name}' is not valid for an integer value.");
		}

		return new self($unitType, $value);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a string-based value.
	 *
	 * @param string      $value    the string value
	 * @param CSSUnitType $unitType the unit type (must be a string type like CSS_STRING, CSS_URI, CustomIdent)
	 *
	 * @throws \InvalidArgumentException if the unit type is not a valid string type
	 */
	public static function createString(string $value, CSSUnitType $unitType): self
	{
		if (!self::isStringType($unitType)) {
			throw new \InvalidArgumentException("Unit type '{$unitType->name}' is not valid for a string value.");
		}

		return new self($unitType, $value);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a CSS identifier (value ID).
	 *
	 * @param CSSValueID $valueID the CSS Value ID enum case
	 */
	public static function createIdentifier(CSSValueID $valueID): self
	{
		return new self(CSSUnitType::CSS_VALUE_ID, $valueID);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a CSS custom identifier.
	 *
	 * @param string $ident the custom identifier string
	 */
	public static function createCustomIdent(string $ident): self
	{
		// Consider adding validation for custom ident syntax if needed
		return new self(CSSUnitType::CustomIdent, $ident);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a CSS property ID (used in specific contexts like var()).
	 *
	 * @param CSSPropertyID $propertyID the CSS Property ID enum case
	 */
	public static function createPropertyId(CSSPropertyID $propertyID): self
	{
		return new self(CSSUnitType::CSS_PROPERTY_ID, $propertyID);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a CSS calculation.
	 *
	 * @param CSSCalcValue $calcValue the calculation object
	 */
	public static function createCalculation(CSSCalcValue $calcValue): self
	{
		// The unit type reflects the calculation's nature (length, angle, etc.)
		// We store CSS_CALC internally, but primitiveType() resolves further.
		return new self(CSSUnitType::CSS_CALC, $calcValue);
	}

	/**
	 * Creates a CSSPrimitiveValue representing a CSS attr() function.
	 *
	 * @param CSSAttrValue $attrValue the attr() object
	 */
	public static function createAttr(CSSAttrValue $attrValue): self
	{
		return new self(CSSUnitType::CSS_ATTR, $attrValue);
	}

	// --- Type Checking (using match - PHP 8.0+) ---

	/**
	 * Checks if the unit type represents a value typically stored as a float/double.
	 */
	public static function isValidCSSUnitTypeForDoubleConversion(CSSUnitType $unitType): bool
	{
		// Match expression is often cleaner for this than switch
		return match ($unitType) {
			CSSUnitType::CSS_CALC,
			CSSUnitType::CSS_CALC_PERCENTAGE_WITH_ANGLE, // These might need special handling
			CSSUnitType::CSS_CALC_PERCENTAGE_WITH_LENGTH, // depending on CSSCalcValue structure
			CSSUnitType::CSS_CAP, CSSUnitType::CSS_CH, CSSUnitType::CSS_IC,
			CSSUnitType::CSS_CM, CSSUnitType::CSS_DEG, CSSUnitType::CSS_DIMENSION,
			CSSUnitType::CSS_DVB, CSSUnitType::CSS_DVH, CSSUnitType::CSS_DVI,
			CSSUnitType::CSS_DVMAX, CSSUnitType::CSS_DVMIN, CSSUnitType::CSS_DVW,
			CSSUnitType::CSS_EM, CSSUnitType::CSS_EX, CSSUnitType::CSS_FR,
			CSSUnitType::CSS_GRAD, CSSUnitType::CSS_HZ, CSSUnitType::CSS_IN,
			CSSUnitType::CSS_KHZ, CSSUnitType::CSS_MM, CSSUnitType::CSS_MS,
			CSSUnitType::CSS_NUMBER, CSSUnitType::CSS_INTEGER, // Integer is convertible
			CSSUnitType::CSS_PC, CSSUnitType::CSS_PERCENTAGE, CSSUnitType::CSS_PT,
			CSSUnitType::CSS_PX, CSSUnitType::CSS_Q, CSSUnitType::CSS_LH,
			CSSUnitType::CSS_LVB, CSSUnitType::CSS_LVH, CSSUnitType::CSS_LVI,
			CSSUnitType::CSS_LVMAX, CSSUnitType::CSS_LVMIN, CSSUnitType::CSS_LVW,
			CSSUnitType::CSS_RLH, CSSUnitType::CSS_QUIRKY_EM, CSSUnitType::CSS_RAD,
			CSSUnitType::CSS_RCAP, CSSUnitType::CSS_RCH, CSSUnitType::CSS_REM,
			CSSUnitType::CSS_REX, CSSUnitType::CSS_RIC, CSSUnitType::CSS_S,
			CSSUnitType::CSS_SVB, CSSUnitType::CSS_SVH, CSSUnitType::CSS_SVI,
			CSSUnitType::CSS_SVMAX, CSSUnitType::CSS_SVMIN, CSSUnitType::CSS_SVW,
			CSSUnitType::CSS_TURN, CSSUnitType::CSS_VB, CSSUnitType::CSS_VH,
			CSSUnitType::CSS_VI, CSSUnitType::CSS_VMAX, CSSUnitType::CSS_VMIN,
			CSSUnitType::CSS_VW, CSSUnitType::CSS_DPCM, CSSUnitType::CSS_DPI,
			CSSUnitType::CSS_DPPX, CSSUnitType::CSS_X,
			CSSUnitType::CSS_CQW, CSSUnitType::CSS_CQH, CSSUnitType::CSS_CQI,
			CSSUnitType::CSS_CQB, CSSUnitType::CSS_CQMIN, CSSUnitType::CSS_CQMAX => true,
			// Types that are not typically doubles
			CSSUnitType::CSS_ATTR, CSSUnitType::CSS_FONT_FAMILY, CSSUnitType::CustomIdent,
			CSSUnitType::CSS_PROPERTY_ID, CSSUnitType::CSS_STRING, CSSUnitType::CSS_UNKNOWN,
			CSSUnitType::CSS_URI, CSSUnitType::CSS_VALUE_ID, CSSUnitType::CSS_IDENT => false,
			// Default case for safety, though all enum cases should be covered
			// default => false, // Not needed if all cases covered
		};
	}

	/**
	 * Checks if the unit type represents a value typically stored as a string.
	 */
	public static function isStringType(CSSUnitType $type): bool
	{
		return match ($type) {
			CSSUnitType::CSS_STRING,
			CSSUnitType::CustomIdent, // Custom identifiers are string-like
			CSSUnitType::CSS_URI,
			CSSUnitType::CSS_ATTR, // attr() resolves to a string or other type, but the function itself is string-like in parsing
			CSSUnitType::CSS_FONT_FAMILY => true, // Font family names are strings
			default => false,
		};
	}

	// --- Value Accessors ---

	/**
	 * Gets the primitive unit type of the value.
	 * For calculated values, this might return a more specific type (e.g., length)
	 * based on the calculation's result category.
	 */
	public function primitiveType(): CSSUnitType
	{
		return match ($this->unitType) {
			// Map internal representations to exposed types if needed
			CSSUnitType::CSS_PROPERTY_ID,
			CSSUnitType::CSS_VALUE_ID,
			CSSUnitType::CustomIdent => CSSUnitType::CSS_IDENT, // Or keep distinct? CSS OM often exposes VALUE_ID as IDENT. Check spec.

			CSSUnitType::CSS_FONT_FAMILY => CSSUnitType::CSS_STRING, // As per original comment

			CSSUnitType::CSS_CALC => $this->value instanceof CSSCalcValue ?
										$this->value->primitiveType() : // Delegate to CSSCalcValue
										CSSUnitType::CSS_UNKNOWN, // Should not happen

			// Default: return the stored unit type
			default => $this->unitType,
		};
	}

	/**
	 * Gets the value as a float.
	 *
	 * @throws \LogicException if the value is not numeric
	 */
	public function getFloatValue(): float
	{
		if (!is_float($this->value) && !is_int($this->value)) { // Allow ints too
			throw new \LogicException("Value is not numeric (type: {$this->unitType->name}). Cannot get float value.");
		}
		if (!self::isValidCSSUnitTypeForDoubleConversion($this->unitType)) {
			// This check might be redundant if factory methods are strict, but good for safety
			throw new \LogicException("Unit type {$this->unitType->name} is not convertible to float.");
		}

		return (float) $this->value;
	}

	/**
	 * Gets the value as an integer.
	 *
	 * @throws \LogicException if the value is not an integer
	 */
	public function getIntegerValue(): int
	{
		if (!is_int($this->value)) {
			throw new \LogicException("Value is not an integer (type: {$this->unitType->name}). Cannot get integer value.");
		}

		// No need to check isValidCSSUnitTypeForDoubleConversion here, focus on actual stored type
		return $this->value;
	}

	/**
	 * Gets the value as a string.
	 *
	 * @throws \LogicException if the value is not string-representable in this context
	 */
	public function getStringValue(): string
	{
		if (!is_string($this->value)) {
			// Allow specific non-string types to be stringified if needed (e.g., identifiers)
			if (CSSUnitType::CSS_VALUE_ID === $this->unitType && $this->value instanceof CSSValueID) {
				return $this->value->name; // Or a specific string representation if defined
			}
			if (CSSUnitType::CustomIdent === $this->unitType) {
				// Should already be a string, but check for safety
				if (is_string($this->value)) {
					return $this->value;
				}
			}
			// Add other cases like CSS_PROPERTY_ID if needed
			throw new \LogicException("Value is not a string or identifier (type: {$this->unitType->name}). Cannot get string value.");
		}
		if (!self::isStringType($this->unitType)) {
			throw new \LogicException("Unit type {$this->unitType->name} is not typically represented as a string.");
		}

		return $this->value;
	}

	/**
	 * Gets the value as a CSSValueID (identifier).
	 *
	 * @throws \LogicException if the value is not a CSS_VALUE_ID
	 */
	public function getIdentifierValue(): CSSValueID
	{
		if (CSSUnitType::CSS_VALUE_ID !== $this->unitType || !$this->value instanceof CSSValueID) {
			throw new \LogicException("Value is not a CSS Identifier (type: {$this->unitType->name}).");
		}

		return $this->value;
	}

	/**
	 * Gets the value as a CSSPropertyID.
	 *
	 * @throws \LogicException if the value is not a CSS_PROPERTY_ID
	 */
	public function getPropertyIdValue(): CSSPropertyID
	{
		if (CSSUnitType::CSS_PROPERTY_ID !== $this->unitType || !$this->value instanceof CSSPropertyID) {
			throw new \LogicException("Value is not a CSS Property ID (type: {$this->unitType->name}).");
		}

		return $this->value;
	}

	/**
	 * Gets the value as a CSSCalcValue.
	 *
	 * @throws \LogicException if the value is not a CSS_CALC
	 */
	public function getCalcValue(): CSSCalcValue
	{
		if (CSSUnitType::CSS_CALC !== $this->unitType || !$this->value instanceof CSSCalcValue) {
			throw new \LogicException("Value is not a CSS Calculation (type: {$this->unitType->name}).");
		}

		return $this->value;
	}

	/**
	 * Gets the value as a CSSAttrValue.
	 *
	 * @throws \LogicException if the value is not a CSS_ATTR
	 */
	public function getAttrValue(): CSSAttrValue
	{
		if (CSSUnitType::CSS_ATTR !== $this->unitType || !$this->value instanceof CSSAttrValue) {
			throw new \LogicException("Value is not a CSS attr() value (type: {$this->unitType->name}).");
		}

		return $this->value;
	}

	// --- CSSValue Overrides ---

	/**
	 * Checks for equality with another CSSValue.
	 */
	public function equals(CSSValue $other): bool
	{
		if (!$other instanceof self) {
			// Handle comparison with single-item lists if necessary, as per parent::equals
			if ($other instanceof CSSValueList) { // Assuming CSSValueList exists
				return $other->containsSingleEqualItem($this);
			}

			return false;
		}

		if ($this->unitType !== $other->unitType) {
			return false;
		}

		// Compare values based on type
		return match ($this->unitType) {
			CSSUnitType::CSS_CALC => $this->value instanceof CSSCalcValue && $other->value instanceof CSSCalcValue && $this->value->equals($other->value), // Assuming CSSCalcValue has equals()
			CSSUnitType::CSS_ATTR => $this->value instanceof CSSAttrValue && $other->value instanceof CSSAttrValue && $this->value->equals($other->value), // Assuming CSSAttrValue has equals()
			CSSUnitType::CSS_VALUE_ID, CSSUnitType::CSS_PROPERTY_ID => $this->value === $other->value, // Enum comparison
			default => $this->value === $other->value, // Strict comparison for float, int, string
		};
	}

	/**
	 * Adds the hash representation of this value to the hasher.
	 * Needs a Hasher class/interface defined.
	 */
	public function addDerivedHash(Hasher $hasher): bool // Assuming Hasher interface/class exists
	{
		$hasher->add($this->unitType->value); // Add enum value (or name)

		match ($this->unitType) {
			CSSUnitType::CSS_CALC => $this->value instanceof CSSCalcValue ? $this->value->addHash($hasher) : $hasher->add(null),
			CSSUnitType::CSS_ATTR => $this->value instanceof CSSAttrValue ? $this->value->addHash($hasher) : $hasher->add(null),
			CSSUnitType::CSS_VALUE_ID => $hasher->add($this->value->value), // Add enum value
			CSSUnitType::CSS_PROPERTY_ID => $hasher->add($this->value->value), // Add enum value
			default => $hasher->add($this->value), // Add primitive value
		};

		return true; // Indicate hashing was done
	}

	/**
	 * Generates the CSS text representation of the value.
	 */
	public function customCSSText(?SerializationContext $context = null): string
	{
		// Use match for cleaner handling
		return match ($this->unitType) {
			CSSUnitType::CSS_NUMBER, CSSUnitType::CSS_INTEGER => (string) $this->value,
			CSSUnitType::CSS_PERCENTAGE => $this->value.'%',
			CSSUnitType::CSS_EM => $this->value.'em',
			CSSUnitType::CSS_EX => $this->value.'ex',
			CSSUnitType::CSS_CAP => $this->value.'cap',
			CSSUnitType::CSS_CH => $this->value.'ch',
			CSSUnitType::CSS_IC => $this->value.'ic',
			CSSUnitType::CSS_REM => $this->value.'rem',
			CSSUnitType::CSS_LH => $this->value.'lh',
			CSSUnitType::CSS_RLH => $this->value.'rlh',
			CSSUnitType::CSS_VW => $this->value.'vw',
			CSSUnitType::CSS_VH => $this->value.'vh',
			CSSUnitType::CSS_VI => $this->value.'vi',
			CSSUnitType::CSS_VB => $this->value.'vb',
			CSSUnitType::CSS_VMIN => $this->value.'vmin',
			CSSUnitType::CSS_VMAX => $this->value.'vmax',
			// Add all other length, angle, time, frequency, resolution, flex units...
			CSSUnitType::CSS_PX => $this->value.'px',
			CSSUnitType::CSS_CM => $this->value.'cm',
			CSSUnitType::CSS_MM => $this->value.'mm',
			CSSUnitType::CSS_IN => $this->value.'in',
			CSSUnitType::CSS_PT => $this->value.'pt',
			CSSUnitType::CSS_PC => $this->value.'pc',
			CSSUnitType::CSS_Q => $this->value.'q',

			CSSUnitType::CSS_DEG => $this->value.'deg',
			CSSUnitType::CSS_GRAD => $this->value.'grad',
			CSSUnitType::CSS_RAD => $this->value.'rad',
			CSSUnitType::CSS_TURN => $this->value.'turn',

			CSSUnitType::CSS_MS => $this->value.'ms',
			CSSUnitType::CSS_S => $this->value.'s',

			CSSUnitType::CSS_HZ => $this->value.'hz',
			CSSUnitType::CSS_KHZ => $this->value.'khz',

			CSSUnitType::CSS_DPPX => $this->value.'dppx',
			CSSUnitType::CSS_X => $this->value.'x', // Alias for dppx
			CSSUnitType::CSS_DPI => $this->value.'dpi',
			CSSUnitType::CSS_DPCM => $this->value.'dpcm',

			CSSUnitType::CSS_FR => $this->value.'fr',

			CSSUnitType::CSS_STRING => '"'.$this->escapeCSSString($this->value).'"', // Need escape function
			CSSUnitType::CSS_URI => 'url("'.$this->escapeCSSString($this->value).'")', // Handle context for resolved URLs if needed

			CSSUnitType::CSS_IDENT => (string) $this->value, // Should be safe if validated
			CSSUnitType::CustomIdent => (string) $this->value, // Should be safe if validated
			CSSUnitType::CSS_VALUE_ID => $this->value instanceof CSSValueID ? $this->value->toCssString() : 'invalid-value-id', // Requires CSSValueID::toCssString() method
			CSSUnitType::CSS_PROPERTY_ID => $this->value instanceof CSSPropertyID ? $this->value->toCssString() : 'invalid-property-id', // Requires CSSPropertyID::toCssString() method

			CSSUnitType::CSS_CALC => $this->value instanceof CSSCalcValue ? $this->value->cssText($context) : 'invalid-calc()',
			CSSUnitType::CSS_ATTR => $this->value instanceof CSSAttrValue ? $this->value->cssText($context) : 'invalid-attr()',

			// Add SV*, LV*, DV* units
			CSSUnitType::CSS_SVW => $this->value.'svw',
			CSSUnitType::CSS_SVH => $this->value.'svh',
			// ... etc ...
			CSSUnitType::CSS_LVW => $this->value.'lvw',
			// ... etc ...
			CSSUnitType::CSS_DVW => $this->value.'dvw',
			// ... etc ...

			// Add CQ* units
			CSSUnitType::CSS_CQW => $this->value.'cqw',
			CSSUnitType::CSS_CQH => $this->value.'cqh',
			CSSUnitType::CSS_CQI => $this->value.'cqi',
			CSSUnitType::CSS_CQB => $this->value.'cqb',
			CSSUnitType::CSS_CQMIN => $this->value.'cqmin',
			CSSUnitType::CSS_CQMAX => $this->value.'cqmax',

			// Handle remaining types or throw error
			CSSUnitType::CSS_UNKNOWN, CSSUnitType::CSS_DIMENSION => 'invalid', // Or throw?
			default => '/* unhandled unit type: '.$this->unitType->name.' */', // Placeholder
		};
	}

	/**
	 * Collects dependencies needed to compute the final value.
	 */
	public function collectComputedStyleDependencies(ComputedStyleDependencies $dependencies): void
	{
		// Use match for clarity
		match ($this->unitType) {
			// Font-relative units depend on font metrics of the element or parent
			CSSUnitType::CSS_EM, CSSUnitType::CSS_REM, CSSUnitType::CSS_EX,
			CSSUnitType::CSS_REX, CSSUnitType::CSS_CAP, CSSUnitType::CSS_RCAP,
			CSSUnitType::CSS_CH, CSSUnitType::CSS_RCH, CSSUnitType::CSS_IC,
			CSSUnitType::CSS_RIC, CSSUnitType::CSS_LH, CSSUnitType::CSS_RLH =>
				// These depend on font properties, potentially on the same element or root
				// Need specific dependency tracking in ComputedStyleDependencies
				$dependencies->addFontRelativeDependency($this->unitType), // Assumes such a method exists

			// Viewport units depend on viewport dimensions
			CSSUnitType::CSS_VW, CSSUnitType::CSS_VH, CSSUnitType::CSS_VI, CSSUnitType::CSS_VB,
			CSSUnitType::CSS_VMIN, CSSUnitType::CSS_VMAX,
			CSSUnitType::CSS_SVW, CSSUnitType::CSS_SVH, CSSUnitType::CSS_SVI, CSSUnitType::CSS_SVB,
			CSSUnitType::CSS_SVMIN, CSSUnitType::CSS_SVMAX,
			CSSUnitType::CSS_LVW, CSSUnitType::CSS_LVH, CSSUnitType::CSS_LVI, CSSUnitType::CSS_LVB,
			CSSUnitType::CSS_LVMIN, CSSUnitType::CSS_LVMAX,
			CSSUnitType::CSS_DVW, CSSUnitType::CSS_DVH, CSSUnitType::CSS_DVI, CSSUnitType::CSS_DVB,
			CSSUnitType::CSS_DVMIN, CSSUnitType::CSS_DVMAX => $dependencies->setViewportDimensions(true), // Assumes setter exists

			// Container query units depend on container dimensions
			CSSUnitType::CSS_CQW, CSSUnitType::CSS_CQH, CSSUnitType::CSS_CQI, CSSUnitType::CSS_CQB,
			CSSUnitType::CSS_CQMIN, CSSUnitType::CSS_CQMAX => $dependencies->setContainerDimensions(true), // Assumes setter exists

			// Calculations inherit dependencies from their children
			CSSUnitType::CSS_CALC => $this->value instanceof CSSCalcValue ?
										$this->value->collectComputedStyleDependencies($dependencies) :
										null, // No-op if value is wrong type

			// attr() depends on the attribute value, which is element-specific but doesn't usually affect layout *computation* dependencies directly unless the attribute *value* contains dependent units (handled during attr resolution).
			CSSUnitType::CSS_ATTR => $this->value instanceof CSSAttrValue ?
										$this->value->collectComputedStyleDependencies($dependencies) :
										null, // No-op if value is wrong type

			// Percentage depends on the context property (e.g., width % depends on containing block width)
			CSSUnitType::CSS_PERCENTAGE =>
				// This needs context - the dependency depends on *which* property this value is for.
				// The dependency should likely be added by the StyleBuilder based on the property ID.
				// $dependencies->addPercentageDependency(PROPERTY_ID_CONTEXT)
				// For now, we can't add the specific dependency here without context.
				null, // No-op here, handled higher up.

			// Absolute lengths, numbers, identifiers, strings etc., have no computation dependencies
			default => null, // No-op
		};
	}

	// Helper for escaping strings for CSS text output
	private function escapeCSSString(string $str): string
	{
		// Basic escaping for quotes and backslashes. Needs to be more robust for full CSS compliance.
		// See CSS Syntax Module Level 3: https://www.w3.org/TR/css-syntax-3/#escaping
		// This is a simplified version.
		$str = str_replace('\\', '\\\\', $str);
		$str = str_replace('"', '\\"', $str);

		// Add escaping for control characters, etc. if needed
		return $str;
	}

	// --- Potentially needed overrides from CSSValue ---
	// Add overrides for customVisitChildren, customMayDependOnBaseURL etc. if needed
	// based on the specific types (e.g., CSS_URI might depend on base URL).

	public function customMayDependOnBaseURL(): bool
	{
		return CSSUnitType::CSS_URI === $this->unitType;
	}
}
