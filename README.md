# PHP Typed Object Model

## A PHP implementation of the CSS Typed Object Model (OM).

## THIS IS A WORK IN PROGRESS (WIP)

## Installation

Install via Composer:

```bash
composer require jimbo2150/php-css-typed-om
```

## Supported Typed OM Classes

The following CSS Typed OM classes are currently supported:

| Class | Description |
|-------|-------------|
| `CSSStyleValue` | Base class for all CSS values |
| `CSSNumericValue` | Abstract base for numeric values |
| `CSSUnitValue` | Represents a value with a unit (e.g., `10px`, `2em`) |
| `CSSNumericArray` | Array of numeric values |
| `CSSNumericType` | Represents the type of a numeric value |
| `CSSUnitEnum` | Enumeration of CSS units |
| `CSSUnitTypeEnum` | Enumeration of CSS unit types |
| `CSSMathValue` | Abstract base for math operations |
| `CSSMathSum` | Represents addition operations |
| `CSSMathProduct` | Represents multiplication operations |
| `CSSMathMin` | Represents min() function |
| `CSSMathMax` | Represents max() function |
| `CSSMathInvert` | Represents inversion operations |
| `CSSMathNegate` | Represents negation operations |

## Unsupported Typed OM Classes

The following CSS Typed OM classes are not yet implemented:

| Class | Description |
|-------|-------------|
| `CSSKeywordValue` | Represents CSS keywords (e.g., `auto`, `none`) |
| `CSSImageValue` | Represents CSS image values |
| `CSSColorValue` | Represents CSS color values |
| `CSSTransformValue` | Represents CSS transform values |
| `CSSPositionValue` | Represents CSS position values |
| `CSSUnparsedValue` | Represents unparsed CSS values |

### Why are these not supported?
The unsupported Typed OM classes listed above generally require a connection to a DOM object so that it can parse
and apply values to elements or CSS stylesheets. Without it, they are not all that useful. In the future, if there
is sufficient demand and need for these (or if I connect this class to the new DOM Document object), they will be
implemented.

## Usage Examples

### Creating Unit Values

```php
use Jimbo2150\PhpCssTypedOm\CSS;

// Using the CSS utility class
$width = CSS::px(100);
$height = CSS::em(2);
$margin = CSS::percent(10);

// Direct instantiation
$length = new CSSUnitValue(50, 'px');
```

### Parsing CSS Values

```php
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

$value = CSSNumericValue::parse('10px');
echo $value->value; // 10
echo $value->unit;  // 'px'
```

### Math Operations

```php
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSMathSum;

$value1 = CSS::px(10);
$value2 = CSS::px(5);

// Addition
$sum = $value1->add($value2); // CSSUnitValue(15, 'px')

// Subtraction
$diff = $value1->sub($value2); // CSSUnitValue(5, 'px')

// Multiplication
$product = $value1->mul(CSS::number(2)); // CSSMathProduct

// Division
$quotient = $value1->div(CSS::number(2)); // CSSMathProduct

// Min/Max
$min = $value1->min(new CSSNumericArray([$value2, CSS::px(20)])); // CSSMathMin
$max = $value1->max(new CSSNumericArray([$value2, CSS::px(20)])); // CSSMathMax
```

### Unit Conversion

```php
$value = CSS::px(16);

// Convert to em
$emValue = $value->to('em'); // CSSUnitValue(1, 'em')

// Convert to sum with multiple units
$sum = $value->toSum('px', 'em', 'pt'); // CSSMathSum with 3 values
```

### String Representation

```php
$value = CSS::px(100);
echo (string) $value; // "100px"

$calc = $value->add(CSS::em(2));
echo (string) $calc; // "calc(100px + 2em)"
```

## Resources

### Mozilla Developer Network (MDN)
[Mozilla Developer Network's documentation of the CSS Typed Object Model API](https://developer.mozilla.org/en-US/docs/Web/API/CSS_Typed_OM_API)
