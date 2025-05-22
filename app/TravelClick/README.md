# TravelClick SOAP Endpoint Implementation

This documentation explains how the SOAP endpoint was implemented for bidirectional integration with TravelClick using the HTNG 2011B protocol.

## Implemented Components

1. **SoapController**: Handles incoming SOAP requests, identifies message types, and sends appropriate responses.
2. **SoapAuthMiddleware**: Validates credentials and hotel_code in incoming SOAP requests.
3. **SOAP Routes**: Configuration of routes for the SOAP endpoint and WSDL.
4. **WSDL File**: Defines the SOAP services available to TravelClick.
5. **Configuration Command**: Facilitates SOAP endpoint setup.

## Installation Commands

To correctly set up the integration, follow these steps:

```bash
# 1. Create the necessary directories
php artisan make:directory --recursive storage/soap/wsdl

# 2. Publish configuration files
php artisan vendor:publish --tag=travelclick-config
php artisan vendor:publish --tag=travelclick-validation-config
php artisan vendor:publish --tag=travelclick-schemas

# 3. Run migrations
php artisan migrate

# 4. Configure the SOAP endpoint
php artisan travelclick:setup-soap --domain=https://your-domain.com
```

## TravelClick Configuration

Once configured, provide TravelClick with the following information:

1. **WSDL URL**: `https://your-domain.com/api/travelclick/soap/wsdl`
2. **SOAP Endpoint URL**: `https://your-domain.com/api/travelclick/soap`
3. **Credentials**: The same as configured in `config/travelclick.php`
4. **Message Format**: HTNG 2011B (SOAP 1.1 or 1.2)

## Testing the Endpoint

You can verify that the endpoint is working correctly with the following curl command:

```bash
curl -X GET https://your-domain.com/api/travelclick/soap/wsdl
```

You should receive the WSDL file as a response.

## Troubleshooting

If you encounter issues with the integration:

1. Check the logs in `storage/logs/travelclick-*.log`
2. Ensure that the credentials configured in `config/travelclick.php` match those used by TravelClick
3. Verify that the WSDL and routes are publicly accessible

## Next Steps

To complete the bidirectional integration:

1. Implement Group Block message handling
2. Enhance XML validation with XSD schemas
3. Implement automated tests for the SOAP endpoint

## Implementation Details

### Created Files:

1. **`app/TravelClick/Http/Controllers/SoapController.php`**
   - Controller for handling incoming SOAP requests
   - Identifies and processes message types (reservations, group blocks, etc.)
   - Sends responses in SOAP format

2. **`app/TravelClick/Http/Middleware/SoapAuthMiddleware.php`**
   - Authenticates SOAP requests using WSSE credentials
   - Validates hotel_code and permissions
   - Returns formatted SOAP errors

3. **`routes/travelclick.php`**
   - Defines routes for the SOAP endpoint
   - Serves the WSDL file
   - Includes health endpoint

4. **`storage/soap/wsdl/travelclick.wsdl`**
   - WSDL file for TravelClick to consume
   - Defines SOAP interfaces according to HTNG 2011B

5. **`app/TravelClick/Console/Commands/SetupSoapEndpointCommand.php`**
   - Command to configure the SOAP endpoint
   - Configures the WSDL with the correct URL
   - Updates the configuration

6. **`app/TravelClick/Exceptions/TravelClickAuthenticationException.php`**
   - Specific exception for authentication errors

### Updates:

1. **`app/Providers/TravelClickServiceProvider.php`**
   - Loads TravelClick routes
   - Registers the new configuration command

With this implementation, the system can now receive incoming SOAP messages from TravelClick, process them asynchronously using jobs, and respond appropriately, completing the bidirectional integration according to the HTNG 2011B document requirements.