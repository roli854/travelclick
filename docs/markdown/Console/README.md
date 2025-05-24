# Console Commands

## Overview

This namespace contains 5 classes/interfaces/enums.

## Table of Contents

- [CacheConfigurationCommand](#cacheconfigurationcommand) (Class)
- [ImportTravelClickSamplesCommand](#importtravelclicksamplescommand) (Class)
- [SetupBddStructureCommand](#setupbddstructurecommand) (Class)
- [SetupSoapEndpointCommand](#setupsoapendpointcommand) (Class)
- [ValidateConfigurationCommand](#validateconfigurationcommand) (Class)

## Complete API Reference

---

### CacheConfigurationCommand

**Type:** Class
**Full Name:** `App\TravelClick\Console\Commands\CacheConfigurationCommand`

**Description:** Cache TravelClick Configuration Command

#### Methods

```php
public function __construct(ConfigurationService $configService);
public function handle(): int;
```

---

### ImportTravelClickSamplesCommand

**Type:** Class
**Full Name:** `App\TravelClick\Console\Commands\ImportTravelClickSamplesCommand`

#### Methods

```php
public function handle(): int;
```

---

### SetupBddStructureCommand

**Type:** Class
**Full Name:** `App\TravelClick\Console\Commands\SetupBddStructureCommand`

#### Methods

```php
public function handle(): int;
```

---

### SetupSoapEndpointCommand

**Type:** Class
**Full Name:** `App\TravelClick\Console\Commands\SetupSoapEndpointCommand`

#### Methods

```php
public function handle();
```

---

### ValidateConfigurationCommand

**Type:** Class
**Full Name:** `App\TravelClick\Console\Commands\ValidateConfigurationCommand`

**Description:** Validate TravelClick Configuration Command

#### Methods

```php
public function __construct(ConfigurationService $configService);
public function handle(): int;
```

## Detailed Documentation

For detailed documentation of each class, see:

- [CacheConfigurationCommand](CacheConfigurationCommand.md)
- [ImportTravelClickSamplesCommand](ImportTravelClickSamplesCommand.md)
- [SetupBddStructureCommand](SetupBddStructureCommand.md)
- [SetupSoapEndpointCommand](SetupSoapEndpointCommand.md)
- [ValidateConfigurationCommand](ValidateConfigurationCommand.md)
