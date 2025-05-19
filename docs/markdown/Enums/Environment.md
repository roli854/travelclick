# Environment

**Full Class Name:** `App\TravelClick\Enums\Environment`

**File:** `Enums/Environment.php`

**Type:** Class

## Description

Environment Enum for TravelClick Integration
Defines the different environments available for TravelClick operations.
Each environment has specific endpoints, credentials, and behavior.

## Methods

### `label`

Get the display label for the environment

```php
public function label(): string
```

---

### `endpoint`

Get the endpoint URL for this environment

```php
public function endpoint(): string
```

---

### `wsdlUrl`

Get WSDL URL for this environment

```php
public function wsdlUrl(): string
```

---

### `isProduction`

Check if this is a production environment

```php
public function isProduction(): bool
```

---

### `isTest`

Check if this is a test environment (testing, staging, development)

```php
public function isTest(): bool
```

---

### `timeouts`

Get timeout settings for this environment

```php
public function timeouts(): array
```

---

### `retryPolicy`

Get retry policy for this environment

```php
public function retryPolicy(): array
```

---

### `debugLevel`

Get debug level for this environment

```php
public function debugLevel(): string
```

---

### `all`

Get all environments

```php
public function all(): array
```

---

### `fromApp`

Get environment from current app environment

```php
public function fromApp(): self
```

---

### `color`

Get color for UI representation

```php
public function color(): string
```

---

### `icon`

Get icon for UI representation

```php
public function icon(): string
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

