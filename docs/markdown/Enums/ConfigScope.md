# ConfigScope

**Full Class Name:** `App\TravelClick\Enums\ConfigScope`

**File:** `Enums/ConfigScope.php`

**Type:** Enum

## Description

ConfigScope Enum for TravelClick Configuration
Defines the different scopes for configuration management,
allowing for granular control over caching and configuration updates.

## Constants

### `GLOBAL`

**Value:** `\App\TravelClick\Enums\ConfigScope::GLOBAL`

---

### `PROPERTY`

**Value:** `\App\TravelClick\Enums\ConfigScope::PROPERTY`

---

### `ENDPOINT`

**Value:** `\App\TravelClick\Enums\ConfigScope::ENDPOINT`

---

### `CREDENTIALS`

**Value:** `\App\TravelClick\Enums\ConfigScope::CREDENTIALS`

---

### `QUEUE`

**Value:** `\App\TravelClick\Enums\ConfigScope::QUEUE`

---

### `CACHE`

**Value:** `\App\TravelClick\Enums\ConfigScope::CACHE`

---

### `ALL`

**Value:** `\App\TravelClick\Enums\ConfigScope::ALL`

---

## Properties

### `$name`

**Type:** `string`

---

### `$value`

**Type:** `string`

---

## Methods

### `label`

Get the display label for the scope

```php
public function label(): string
```

---

### `cacheKeyPrefix`

Get the cache key prefix for this scope

```php
public function cacheKeyPrefix(): string
```

---

### `cacheTtl`

Get cache TTL (time to live) for this scope in seconds

```php
public function cacheTtl(): int
```

---

### `requiresPropertyId`

Check if this scope requires property ID

```php
public function requiresPropertyId(): bool
```

---

### `isEnvironmentSpecific`

Check if this scope is environment-specific

```php
public function isEnvironmentSpecific(): bool
```

---

### `configKeys`

Get configuration keys that belong to this scope

```php
public function configKeys(): array
```

---

### `validationRules`

Get validation rules for this scope

```php
public function validationRules(): array
```

---

### `priority`

Get priority for this scope (lower number = higher priority)

```php
public function priority(): int
```

---

### `isCacheable`

Check if this scope can be cached

```php
public function isCacheable(): bool
```

---

### `icon`

Get icon for UI representation

```php
public function icon(): string
```

---

### `color`

Get color for UI representation

```php
public function color(): string
```

---

### `all`

Get all scopes

```php
public function all(): array
```

---

### `validatable`

Get scopes that require validation

```php
public function validatable(): array
```

---

### `cases`

```php
public function cases(): array
```

---

### `from`

```php
public function from(string|int $value): static
```

---

### `tryFrom`

```php
public function tryFrom(string|int $value): static|null
```

---

