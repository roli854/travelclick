# HTTP Components

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [SoapController](#soapcontroller) (Class)
- [SoapAuthMiddleware](#soapauthmiddleware) (Class)
- [SyncStatusRequest](#syncstatusrequest) (Class)
- [TravelClickSyncStatusCollection](#travelclicksyncstatuscollection) (Class)
- [TravelClickSyncStatusResource](#travelclicksyncstatusresource) (Class)

## Complete API Reference

---

### SoapController

**Type:** Class
**Full Name:** `App\TravelClick\Http\Controllers\SoapController`

#### Methods

```php
public function handle(Illuminate\Http\Request $request): Illuminate\Http\Response;
```

---

### SoapAuthMiddleware

**Type:** Class
**Full Name:** `App\TravelClick\Http\Middleware\SoapAuthMiddleware`

#### Methods

```php
public function handle(Illuminate\Http\Request $request, Closure $next);
```

---

### SyncStatusRequest

**Type:** Class
**Full Name:** `App\TravelClick\Http\Requests\SyncStatusRequest`

**Description:** Request validation for TravelClick Sync Status operations

#### Methods

```php
public function authorize(): bool;
public function rules(): array;
public function messages(): array;
public function attributes(): array;
public function withValidator($validator): void;
public function getSyncStatusData(): array;
public function isUpdateRequest(): bool;
public function isCreateRequest(): bool;
public static function getApiDocumentation(): array;
```

---

### TravelClickSyncStatusCollection

**Type:** Class
**Full Name:** `App\TravelClick\Http\Resources\TravelClickSyncStatusCollection`

**Description:** TravelClick Sync Status Collection Resource

#### Properties

```php
public $collects;
```

#### Methods

```php
public function toArray(Illuminate\Http\Request $request): array;
public function with(Illuminate\Http\Request $request): array;
public function withResponse(Illuminate\Http\Request $request, $response): void;
```

---

### TravelClickSyncStatusResource

**Type:** Class
**Full Name:** `App\TravelClick\Http\Resources\TravelClickSyncStatusResource`

**Description:** TravelClick Sync Status Resource

#### Methods

```php
public function toArray(Illuminate\Http\Request $request): array;
public static function collection($resource): TravelClickSyncStatusCollection;
public function with(Illuminate\Http\Request $request): array;
public function withResponse(Illuminate\Http\Request $request, $response): void;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [SoapController](SoapController.md)
- [SoapAuthMiddleware](SoapAuthMiddleware.md)
- [SyncStatusRequest](SyncStatusRequest.md)
- [TravelClickSyncStatusCollection](TravelClickSyncStatusCollection.md)
- [TravelClickSyncStatusResource](TravelClickSyncStatusResource.md)
