# `StyleSheet`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\StyleSheet`
- **Source:** [`src/CSSOM/StyleSheet.php`](../../../src/CSSOM/StyleSheet.php)
- **Purpose:** Abstract base for standalone stylesheet metadata and disabled state.

## Properties

Constructor metadata `href`, `title`, `ownerRule`, and `parentStyleSheet` are private readonly. Magic properties `type`, `href`, `title`, `disabled`, `media`, `ownerRule`, and `parentStyleSheet` expose getters; `disabled` can be assigned a boolean. No public constants or properties.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(?string $href = null, string $title = '', MediaList|string|null $media = null, ?CSSRule $ownerRule = null, ?StyleSheet $parentStyleSheet = null, bool $disabled = false)` | Validates `$href` and `$title` against disallowed control characters, accepts a `MediaList` (cloned/detached from any prior rule callback) or media-query string, and sets initial `disabled` state. | `$sheet = new CSSStyleSheet('/css/site.css', 'Site', 'screen');` |
| `getType(): string` | Returns `'text/css'`. | `$sheet->getType();` |
| `getHref(): ?string` | Returns configured href or `null`. | `$sheet->getHref();` |
| `getTitle(): string` | Returns configured title. | `$sheet->getTitle();` |
| `isDisabled(): bool` | Returns current disabled state; initially false unless set in constructor. | `$sheet->isDisabled();` |
| `setDisabled(bool $disabled): void` | Updates disabled state. | `$sheet->setDisabled(true);` |
| `getMedia(): MediaList` | Returns the sheet media list. | `$sheet->getMedia()->appendMedium('print');` |
| `getOwnerRule(): ?CSSRule` | Returns owning rule or `null`. | `$sheet->getOwnerRule();` |
| `getParentStyleSheet(): ?StyleSheet` | Returns parent sheet or `null`. | `$sheet->getParentStyleSheet();` |
| `__get(string $name): mixed` | Maps the seven virtual properties above; unknown properties throw `Error`. | `$sheet->media;` |
| `__isset(string $name): bool` | True for `type`, `title`, `disabled`, and `media`, and for non-null `href`, `ownerRule`, and `parentStyleSheet`. | `isset($sheet->media);` |
| `__set(string $name, mixed $value): void` | Boolean `disabled` assignment calls setter; every other assignment throws `Error`. | `$sheet->disabled = true;` |
| `__clone(): void` | Clones the `MediaList` detached from any prior rule callback. | `$copy = clone $sheet;` |

The class has no public abstract methods, but is abstract and cannot be directly instantiated. `CSSStyleSheet` supplies rule-specific behavior.
