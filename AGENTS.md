# AGENTS.md — `jimbo2150/php-css-typed-om`

## Project Overview

`php-css-typed-om` is a standalone, zero-dependency PHP 8.4+ implementation of the **W3C CSS Object Model (CSSOM)** and **CSS Typed Object Model (Typed OM)**. It parses, models, manipulates, simplifies, and serializes CSS stylesheets, rules, declarations, descriptors, math calculation trees, colors, transforms, positions, and geometry matrices without requiring a browser engine or DOM.

## Mandatory Specification Adherence

This library **must adhere as strictly as possible** to the governing W3C / CSS Houdini specifications (and modern Web Platform / `GoogleChromeLabs/cssomnom` Typed OM conventions):

- **CSS Object Model (CSSOM):** <https://drafts.csswg.org/cssom/>
- **CSS Typed OM Level 1 & Level 2:** <https://drafts.css-houdini.org/css-typed-om/>
  - Keep the standard Typed OM Level 1 math classes (`CSSMathSum`, `CSSMathProduct`, `CSSMathNegate`, `CSSMathInvert`, `CSSMathMin`, `CSSMathMax`, `CSSMathClamp`).
  - Represent CSS Values Level 4 math functions (`round`, `mod`, `rem`, `sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`, `pow`, `sqrt`, `hypot`, `log`, `exp`, `abs`, `sign`) using **`CSSMathFunction`** (whose `operator` returns the function name) rather than proliferating non-standard per-function subclasses.
- **CSS Values and Units Module Level 4:** <https://drafts.csswg.org/css-values-4/>
  - Math functions (§10.4–§10.7), math constants & degenerate IEEE-754 clamping (§10.8–§10.9), type checking (§10.9), and calculation-tree simplification / canonical serialization (§10.10).
- **CSS Syntax Module Level 3:** <https://drafts.csswg.org/css-syntax-3/>
- **CSS Color Module Level 4 & Color 5 Typed OM:** <https://drafts.csswg.org/css-color-4/>, <https://drafts.csswg.org/css-color-5/>
- **CSS Properties and Values API Level 1:** <https://drafts.css-houdini.org/css-properties-values-api/>
- **Geometry Interfaces Module Level 1 (`DOMMatrix` / `DOMMatrixReadOnly`):** <https://drafts.fxtf.org/geometry/>

**Rule:** Never invent proprietary or non-standard public APIs or extra classes when a standard W3C / Typed OM interface exists. Match W3C/WebIDL class names, property/method names, serialization formats, dimensional type-checking, and IEEE-754 / CSS edge-case behavior strictly.

## Architecture Map

- `src/CSS.php` — Static CSS namespace (`CSS::px()`, `CSS::escape()`, `CSS::supports()`, `CSS::registerProperty()`).
- `src/CSSOM/` — Stylesheets, rules (`CSSStyleRule`, `CSSMediaRule`, `CSSPropertyRule`, etc.), declarations (`CSSStyleDeclaration`, strict descriptor subclasses), `CSSParser`, `CSSParserOptions`, `CSSParserLimitException`, and `CSSRenderer`.
- `src/Parser/` — `CSSSyntaxScanner` (token/delimiter/escape/comment scanning) and `CSSCalcParser` (iterative shunting-yard & math function parser).
- `src/Geometry/` — `DOMMatrixReadOnly` and `DOMMatrix`.
- `src/TypedOM/` — `CSSContext`, `StylePropertyMapReadOnly`, `StylePropertyMap`, and values (`src/TypedOM/Values/`, `Color/`, `Numeric/`, `Numeric/Math/`).
- `docs/interfaces.md` & `docs/interfaces/` — Public API reference catalog.
- `tests/` — PHPUnit 11/12 unit, spec-compliance, fuzz, and security/limits test suites.
- `benchmarks/bench.php` — Performance benchmark runner.

## Engineering & Security Invariants

1. **Language & Tooling Standards:**
   - PHP 8.4+ with `declare(strict_types=1);` in every source and test file.
   - Tab indentation (`\t`), PSR-4 autoloading (`Jimbo2150\PhpCssTypedOm\`).
   - Clean **PHPStan Level 6** (`phpstan.neon`) and **PHP-CS-Fixer** (`.php-cs-fixer.dist.php`).
2. **Iterative (Non-Recursive) Traversals:**
   - Parsers, serializers, cloners, type evaluators, and simplifiers over arbitrary ASTs/rule trees **must use explicit iterative work stacks** (never unbounded PHP call recursion) to prevent stack exhaustion on hostile inputs.
3. **Resource Limits (`CSSParserLimitException`):**
   - Respect `CSSParserOptions` (`maxBytes`, `maxDepth`, `maxRules`, `maxMathDepth`, `maxErrors`, `maxDeclarations`) and hard caps (`CSSNumericValue::MAX_MATH_NODES`, `CSSNumericValue::MAX_MATH_TREE_DEPTH`, `CSSStyleValue::MAX_LIST_ITEMS`, `CSS::MAX_REGISTERED_PROPERTIES`).
   - Track both node visits and newly allocated nodes during AST transformation/simplification so exponential DAG blowups are blocked deterministically.
4. **Atomic Input Validation & Injection Defense:**
   - Reject control bytes, unbalanced delimiters/quotes/comments, and structural CSS injection (top-level `;`, `{`, `}`) atomically before mutating state.
5. **Finite `CSSUnitValue` Semantics:**
   - `CSSUnitValue::$value` must remain strictly finite per WebIDL `double` semantics. Non-finite/degenerate math results (`infinity`, `-infinity`, `NaN`) and constants (`e`, `pi`) are represented via `CSSMathConstant` / math calculation trees.

## Scope & Boundaries

- Models CSS source and object structures; **not** a browser layout engine, cascade/selector matcher, or HTML sanitizer.
- Serialized CSS is normalized CSS source and is not safe for raw inline `<style>` interpolation without HTML-context escaping (`CSSRenderOptions::$escapeHtmlStyleTag`).

## Development & Verification Commands

```bash
composer test        # ./vendor/bin/phpunit
composer stan        # ./vendor/bin/phpstan analyse --memory-limit=512M
composer cs-check    # ./vendor/bin/php-cs-fixer fix --dry-run --diff
composer cs-fix      # ./vendor/bin/php-cs-fixer fix
composer bench       # XDEBUG_MODE=off php -d opcache.enable_cli=1 benchmarks/bench.php
```
