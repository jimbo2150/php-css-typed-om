# `CSSSelectorList`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Parser\CSSSelectorList`
- **Source:** [`src/Parser/CSSSelectorList.php`](../../../src/Parser/CSSSelectorList.php)
- **Implements:** `Countable`, `Stringable`
- **Purpose:** Iterative Selectors Level 4 parser, canonical CSSOM §6.7.1 serializer, and `(A, B, C)` specificity calculator.

## Public Constants

- `MAX_SELECTOR_NODES = 16_384`

## Public Methods

- `parse(string $selectorText, ?CSSParserOptions $options = null): self` (static) — strictly parses a non-empty complex selector list.
- `parseForgiving(string $selectorText, ?CSSParserOptions $options = null): self` (static) — parses forgiving selector syntax (dropping invalid complex items cleanly when empty/invalid).
- `parseRelative(string $selectorText, ?CSSParserOptions $options = null): self` (static) — parses relative selectors (allowing leading combinators such as `> .item`).
- `getSelectors(): list<string>` — returns canonical serialized complex selectors.
- `getCssText(): string` / `__toString(): string` — returns the canonical comma-joined selector list text.
- `count(): int` — returns number of selectors in the list.
- `hasLeadingCombinator(): bool` — true if any selector begins with a relative combinator.
- `getSpecificities(?array $parentSpecificity = null): list<array{0: int, 1: int, 2: int}>` — computes `(A, B, C)` specificity per selector (`:where()` contributes `[0, 0, 0]`; `:is()`, `:not()`, `:has()`, `:nth-child(... of S)` take max branch specificity).
- `getMaxSpecificity(?array $parentSpecificity = null): ?array{0: int, 1: int, 2: int}` — returns highest `(A, B, C)` specificity in the list.
- `compareSpecificity(array $left, array $right): int` (static) — compares two `(A, B, C)` tuples.
