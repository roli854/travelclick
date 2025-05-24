# TravelClickPropertyMappingObserver

**Full Class Name:** `App\TravelClick\Observers\TravelClickPropertyMappingObserver`

**File:** `Observers/TravelClickPropertyMappingObserver.php`

**Type:** Class

## Description

Observer for TravelClickPropertyMapping model
This observer handles all model events for property mappings, ensuring
that related configurations are kept in sync and caches are invalidated
when necessary. It's like a vigilant supervisor who makes sure every
change to property mappings is properly recorded and synchronized.

## Methods

### `creating`

Handle the "creating" event
This is triggered before a new mapping is saved to the database

```php
public function creating(TravelClickPropertyMapping $mapping): void
```

---

### `created`

Handle the "created" event
This is triggered after a new mapping is successfully saved

```php
public function created(TravelClickPropertyMapping $mapping): void
```

---

### `updating`

Handle the "updating" event
This is triggered before a mapping is updated

```php
public function updating(TravelClickPropertyMapping $mapping): void
```

---

### `updated`

Handle the "updated" event
This is triggered after a mapping is successfully updated

```php
public function updated(TravelClickPropertyMapping $mapping): void
```

---

### `deleting`

Handle the "deleting" event
This is triggered before a mapping is deleted

```php
public function deleting(TravelClickPropertyMapping $mapping): void
```

---

### `deleted`

Handle the "deleted" event
This is triggered after a mapping is successfully deleted

```php
public function deleted(TravelClickPropertyMapping $mapping): void
```

---

### `restored`

Handle the "restored" event (for soft deletes, if implemented)
This is triggered after a soft-deleted mapping is restored

```php
public function restored(TravelClickPropertyMapping $mapping): void
```

---

