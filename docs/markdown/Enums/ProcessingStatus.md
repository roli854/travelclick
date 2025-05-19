# ProcessingStatus

**Full Class Name:** `App\TravelClick\Enums\ProcessingStatus`

**File:** `Enums/ProcessingStatus.php`

**Type:** Class

## Description

ProcessingStatus Enum
Represents the processing status of TravelClick messages.
It's like tracking the stages of a letter delivery system.

## Methods

### `getDisplayName`

Get the display name for the UI

```php
public function getDisplayName(): string
```

---

### `getColor`

Get the color for UI representation

```php
public function getColor(): string
```

---

### `getIcon`

Get the icon for UI representation

```php
public function getIcon(): string
```

---

### `isCompleted`

Check if this status indicates a completed state

```php
public function isCompleted(): bool
```

---

### `isSuccessful`

Check if this status indicates success

```php
public function isSuccessful(): bool
```

---

### `isFailed`

Check if this status indicates failure

```php
public function isFailed(): bool
```

---

### `isInProgress`

Check if this status indicates the message is in progress

```php
public function isInProgress(): bool
```

---

### `getNextStatus`

Get the next logical status in the processing flow

```php
public function getNextStatus(): self|null
```

---

### `getFilterableStatuses`

Get all statuses that can be filtered in queries

```php
public function getFilterableStatuses(): array
```

---

### `getActiveStatuses`

Get statuses that indicate active processing

```php
public function getActiveStatuses(): array
```

---

### `getCompletedStatuses`

Get statuses that indicate completed processing

```php
public function getCompletedStatuses(): array
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

