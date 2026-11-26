# `CSSMediaQueryParser`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Parser\CSSMediaQueryParser`
- **Source:** [`src/Parser/CSSMediaQueryParser.php`](../../../src/Parser/CSSMediaQueryParser.php)
- **Purpose:** Iterative Media Queries Level 4/5 parser, validator, and CSSOM §6.7.1 canonical serializer.

## Public Constants

- `MAX_MEDIA_NODES = 32_768`

## Public Methods

- `parseList(string $mediaText, ?CSSParserOptions $options = null, bool $browserErrorRecovery = false): array` — parses a comma-separated `<media-query-list>` and returns canonicalized query strings; invalid queries recover to `not all` only when `$browserErrorRecovery` is `true` and throw `InvalidArgumentException` otherwise.
- `parseSingle(string $medium, ?CSSParserOptions $options = null, bool $browserErrorRecovery = false): string` — parses and canonically serializes a single `<media-query>`, supporting range syntax (`(400px <= width <= 800px)`), boolean `and`/`or`/`not`, and MQ L5 boolean `<dashed-ident>` custom media features.
