# CSSContext

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext`  
**Source:** [`src/TypedOM/CSSContext.php`](../../../src/TypedOM/CSSContext.php)  
**Purpose:** Holds font and viewport dimensions used to convert CSS length units to and from pixels, with cached factors.

## Public API

No public properties, constants, or enum cases. Dimensions are stored privately in pixels.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(float $fontSize = 16.0, float $viewportWidth = 960.0, float $viewportHeight = 540.0)`; initializes font size and viewport dimensions; each must be finite and positive or `InvalidArgumentException` is thrown. Example: `$context = new CSSContext(18.0, 1440.0, 900.0);`. |
| `getFontSize` | `public function getFontSize(): float`; returns font-size context in pixels. Example: `$fontSize = $context->getFontSize();`. |
| `setFontSize` | `public function setFontSize(float $fontSize): self`; validates/sets a finite positive pixel size, clears cached factors, and returns this context for chaining; invalid input throws `InvalidArgumentException`. Example: `$context->setFontSize(20.0);`. |
| `getViewportWidth` | `public function getViewportWidth(): float`; returns viewport width in pixels. Example: `$width = $context->getViewportWidth();`. |
| `setViewportWidth` | `public function setViewportWidth(float $viewportWidth): self`; validates/sets a finite positive width, clears factor cache, and returns this context; invalid input throws `InvalidArgumentException`. Example: `$context->setViewportWidth(1280.0);`. |
| `getViewportHeight` | `public function getViewportHeight(): float`; returns viewport height in pixels. Example: `$height = $context->getViewportHeight();`. |
| `setViewportHeight` | `public function setViewportHeight(float $viewportHeight): self`; validates/sets a finite positive height, clears factor cache, and returns this context; invalid input throws `InvalidArgumentException`. Example: `$context->setViewportHeight(720.0);`. |
| `getToPxFactor` | `public function getToPxFactor(string $unit): float`; returns/caches a factor to multiply a value by to convert it to px. Known units use context-dependent approximations; unrecognized units fall back to `1.0`. Example: `$factor = $context->getToPxFactor('em');`. |
| `getFromPxFactor` | `public function getFromPxFactor(string $unit): float`; returns/caches the reciprocal of `getToPxFactor($unit)`, or `1.0` when the factor is not positive. Example: `$factor = $context->getFromPxFactor('vw');`. |

## Conversion model

The implementation uses `fontSize` for `em`/`rem`/`ic`/`ric`, fractions of it for several font metrics, and viewport width/height fractions for viewport/container-relative units. Several metrics are simplified fixed ratios; this context does not model browser style/layout state.
