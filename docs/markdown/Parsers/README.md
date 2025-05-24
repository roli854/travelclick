# Parsers

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [ErrorResponseParser](#errorresponseparser) (Class)
- [InventoryResponseParser](#inventoryresponseparser) (Class)
- [RateResponseParser](#rateresponseparser) (Class)
- [ReservationParser](#reservationparser) (Class)
- [SoapResponseParser](#soapresponseparser) (Class)

## Complete API Reference

---

### ErrorResponseParser

**Type:** Class
**Full Name:** `App\TravelClick\Parsers\ErrorResponseParser`

**Description:** Specialized parser for error responses from TravelClick SOAP API

#### Methods

```php
public function parseError(string $messageId, string $rawResponse, float|null $durationMs = null): SoapResponseDto;
public function parseFromFault(string $messageId, SoapFault $fault, float|null $durationMs = null): SoapResponseDto;
public function categorizeError(string $errorCode, string $errorMessage): ErrorType;
public function categorizeFromException(Throwable $exception): ErrorType;
public function getHtngErrorDescription(string $errorCode): string;
public function isRetryableError(string $errorCode, string $errorMessage): bool;
public function getRetryDelay(string $errorCode, string $errorMessage): int;
```

---

### InventoryResponseParser

**Type:** Class
**Full Name:** `App\TravelClick\Parsers\InventoryResponseParser`

**Description:** Parser for TravelClick Inventory Response Messages

#### Methods

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): SoapResponseDto;
```

---

### RateResponseParser

**Type:** Class
**Full Name:** `App\TravelClick\Parsers\RateResponseParser`

**Description:** Parser specialized in handling and interpreting TravelClick rate responses.

#### Methods

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): SoapResponseDto;
```

---

### ReservationParser

**Type:** Class
**Full Name:** `App\TravelClick\Parsers\ReservationParser`

**Description:** Parser for TravelClick reservation responses

#### Methods

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): ReservationResponseDto;
```

---

### SoapResponseParser

**Type:** Class
**Full Name:** `App\TravelClick\Parsers\SoapResponseParser`

**Description:** Base class for parsing SOAP responses from TravelClick

#### Methods

```php
public function parse(string $messageId, string $rawResponse, float|null $durationMs = null, array $headers = []): SoapResponseDto;
public function parseFromFault(string $messageId, SoapFault $fault, float|null $durationMs = null): SoapResponseDto;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [ErrorResponseParser](ErrorResponseParser.md)
- [InventoryResponseParser](InventoryResponseParser.md)
- [RateResponseParser](RateResponseParser.md)
- [ReservationParser](ReservationParser.md)
- [SoapResponseParser](SoapResponseParser.md)
