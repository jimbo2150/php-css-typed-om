# CSSContext

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext`  
**Source:** [`src/TypedOM/CSSContext.php`](../../../src/TypedOM/CSSContext.php)  
**Purpose:** Holds font/viewport dimensions for CSS length conversions and runtime color state (`colorScheme`, `currentColor`) for CSS Color 4/5 evaluation.

## Public API

No public properties, constants, or enum cases. Dimensions and color state are stored privately.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(float $fontSize = 16.0, float $viewportWidth = 960.0, float $viewportHeight = 540.0, string $colorScheme = 'light', ?CSSColorValue $currentColor = null)`; initializes font/viewport dimensions (finite positive), color scheme (`'light'` or `'dark'`), and optional cloned `currentColor`. Example: `$context = new CSSContext(18.0, 1440.0, 900.0, 'dark');`. |
| `getFontSize` | `public function getFontSize(): float`; returns font-size context in pixels. Example: `$fontSize = $context->getFontSize();`. |
| `setFontSize` | `public function setFontSize(float $fontSize): self`; validates/sets a finite positive pixel size, clears cached factors, and returns this context for chaining; invalid input throws `InvalidArgumentException`. Example: `$context->setFontSize(20.0);`. |
| `getViewportWidth` | `public function getViewportWidth(): float`; returns viewport width in pixels. Example: `$width = $context->getViewportWidth();`. |
| `setViewportWidth` | `public function setViewportWidth(float $viewportWidth): self`; validates/sets a finite positive width, clears factor cache, and returns this context; invalid input throws `InvalidArgumentException`. Example: `$context->setViewportWidth(1280.0);`. |
| `getViewportHeight` | `public function getViewportHeight(): float`; returns viewport height in pixels. Example: `$height = $context->getViewportHeight();`. |
| `setViewportHeight` | `public function setViewportHeight(float $viewportHeight): self`; validates/sets a finite positive height, clears factor cache, and returns this context; invalid input throws `InvalidArgumentException`. Example: `$context->setViewportHeight(720.0);`. |
| `getColorScheme` | `public function getColorScheme(): string`; returns `'light'` or `'dark'`. Example: `$scheme = $context->getColorScheme();`. |
| `setColorScheme` | `public function setColorScheme(string $colorScheme): self`; validates case-insensitive `'light'` or `'dark'` (rejecting control bytes/other values with `InvalidArgumentException`) and returns `$this`. Example: `$context->setColorScheme('dark');`. |
| `getCurrentColor` | `public function getCurrentColor(): ?CSSColorValue`; returns a cloned copy of the configured `currentColor`, or `null`. Example: `$color = $context->getCurrentColor();`. |
| `setCurrentColor` | `public function setCurrentColor(?CSSColorValue $currentColor): self`; stores a cloned copy of `$currentColor` (or `null`) and returns `$this`. Example: `$context->setCurrentColor(new CSSRGB(1, 0, 0));`. |
| `getToPxFactor` | `public function getToPxFactor(string $unit): float`; returns/caches a factor to multiply a value by to convert it to px. Known units use context-dependent approximations and validate against non-finite overflow or underflow to zero (`InvalidArgumentException`); unrecognized units fall back to `1.0`. Example: `$factor = $context->getToPxFactor('em');`. |
| `getFromPxFactor` | `public function getFromPxFactor(string $unit): float`; returns/caches the reciprocal of `getToPxFactor($unit)`, validating against overflow/underflow to zero (`InvalidArgumentException`), or `1.0` when the factor is not positive. Example: `$factor = $context->getFromPxFactor('vw');`. |
| `__clone` | `public function __clone(): void`; deep-clones `$currentColor` when present so cloned contexts remain isolated. |

## Conversion model

The implementation uses `fontSize` for `em`/`rem`/`ic`/`ric`, fractions of it for several font metrics, and viewport width/height fractions for viewport/container-relative units. Several metrics are simplified fixed ratios; this context does not model browser style/layout state.
