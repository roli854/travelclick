<?php

namespace App\TravelClick\Builders;

/**
 * Extends the ReservationXmlBuilder with methods specific to reservation response messages
 * for the HTNG 2011B protocol.
 */
class ReservationResponseXmlBuilder extends ReservationXmlBuilder
{
    /**
     * Builds a success response for a reservation transaction.
     *
     * @param string $reservationId The reservation ID
     * @param string $confirmationNumber The confirmation number
     * @param string $hotelCode The hotel code
     * @param string|null $message Optional success message
     *
     * @return string The XML response
     */
    public function buildSuccessResponse(
        string $reservationId,
        string $confirmationNumber,
        string $hotelCode,
        ?string $message = null
    ): string {
        $otaRoot = $this->getResponseOtaRootElement();
        $attributes = array_merge($this->getOtaMessageAttributes(), [
            'ResResponseType' => 'Successful',
        ]);

        $responseBody = [
            $otaRoot => [
                '_attributes' => $attributes,
                'Success' => []
            ]
        ];

        // Add reservation identifiers
        $responseBody[$otaRoot]['HotelReservations'] = [
            'HotelReservation' => [
                'UniqueID' => [
                    '_attributes' => [
                        'Type' => '14',
                        'ID' => $reservationId
                    ]
                ],
                'ResGlobalInfo' => [
                    'HotelReservationIDs' => [
                        'HotelReservationID' => [
                            '_attributes' => [
                                'ResID_Type' => '10',
                                'ResID_Value' => $confirmationNumber
                            ]
                        ]
                    ]
                ],
                'BasicPropertyInfo' => [
                    '_attributes' => [
                        'HotelCode' => $hotelCode
                    ]
                ]
            ]
        ];

        // Add optional message as a comment
        if (!empty($message)) {
            $responseBody[$otaRoot]['HotelReservations']['HotelReservation']['ResGlobalInfo']['Comments'] = [
                'Comment' => [
                    '_value' => $message
                ]
            ];
        }

        // Build the complete XML
        return $this->build($responseBody);
    }

    /**
     * Builds an error response for a reservation transaction.
     *
     * @param string $messageId The message ID
     * @param string $hotelCode The hotel code
     * @param string $errorMessage The error message
     * @param string $errorCode The error code (default: 450 - Application Error)
     *
     * @return string The XML response
     */
    public function buildErrorResponse(
        string $messageId,
        string $hotelCode,
        string $errorMessage,
        string $errorCode = '450'
    ): string {
        $otaRoot = $this->getResponseOtaRootElement();
        $attributes = array_merge($this->getOtaMessageAttributes(), [
            'ResResponseType' => 'Unsuccessful',
        ]);

        $responseBody = [
            $otaRoot => [
                '_attributes' => $attributes,
                'Errors' => [
                    'Error' => [
                        '_attributes' => [
                            'Type' => 'Application',
                            'Code' => $errorCode,
                            'ShortText' => 'Processing Error',
                        ],
                        '_value' => $errorMessage
                    ]
                ],
                'BasicPropertyInfo' => [
                    '_attributes' => [
                        'HotelCode' => $hotelCode
                    ]
                ]
            ]
        ];

        // Build the complete XML
        return $this->build($responseBody);
    }

    /**
     * Gets the OTA root element name for response messages.
     *
     * @return string The OTA root element name
     */
    protected function getResponseOtaRootElement(): string
    {
        return 'OTA_HotelResRS';
    }
}
