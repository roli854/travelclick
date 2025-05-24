# ReservationService

**Full Class Name:** `App\TravelClick\Services\ReservationService`

**File:** `Services/ReservationService.php`

**Type:** Class

## Description

Service class for handling reservation operations with TravelClick
This service encapsulates the business logic for processing reservation
operations including new bookings, modifications, and cancellations.

## Methods

### `__construct`

Constructor

```php
public function __construct(SoapServiceInterface $soapService, ReservationXmlBuilder $xmlBuilder, ReservationParser $parser)
```

**Parameters:**

- `$soapService` (SoapServiceInterface): 
- `$xmlBuilder` (ReservationXmlBuilder): 
- `$parser` (ReservationParser): 

---

### `processModification`

Process a reservation modification
Main entry point for handling reservation modifications

```php
public function processModification(ReservationDataDto $reservationData, bool $validateRoomTypes = true): ReservationResponseDto
```

**Parameters:**

- `$reservationData` (ReservationDataDto): The reservation data to process
- `$validateRoomTypes` (bool): Whether to validate room types (default: true)

**Returns:** ReservationResponseDto - Response with results of the operation

---

### `findOriginalReservation`

Find the original reservation data for comparison
Retrieves the original reservation data before modification
from TravelClick or local storage

```php
public function findOriginalReservation(string $confirmationNumber): ReservationDataDto|null
```

**Parameters:**

- `$confirmationNumber` (string): 

**Returns:** ReservationDataDto|null - 

---

### `processNewReservation`

Process a new reservation
Handle creating a new reservation in the system

```php
public function processNewReservation(ReservationDataDto $reservationData, bool $validateRoomTypes = true): ReservationResponseDto
```

**Parameters:**

- `$reservationData` (ReservationDataDto): 
- `$validateRoomTypes` (bool): 

**Returns:** ReservationResponseDto - 

---

### `processCancellation`

Process a reservation cancellation
Handle cancelling a reservation in the system

```php
public function processCancellation(ReservationDataDto $reservationData): ReservationResponseDto
```

**Parameters:**

- `$reservationData` (ReservationDataDto): 

**Returns:** ReservationResponseDto - 

---

