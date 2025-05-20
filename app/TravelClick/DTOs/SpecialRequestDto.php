<?php

declare(strict_types=1);

namespace App\TravelClick\DTOs;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Data Transfer Object for special requests in TravelClick integrations
 *
 * This DTO handles special requests that don't incur additional costs.
 * Examples include accessibility requirements, room preferences, etc.
 */
class SpecialRequestDto
{
  /**
   * Request details
   */
  public readonly string $requestCode;
  public readonly string $requestName;
  public readonly ?string $requestDescription;

  /**
   * Request timing
   */
  public readonly ?Carbon $startDate;
  public readonly ?Carbon $endDate;
  public readonly ?string $timeSpan;

  /**
   * Additional information
   */
  public readonly ?string $comments;
  public readonly bool $confirmed;
  public readonly int $quantity;
  public readonly ?int $roomStayIndex;

  /**
   * Create a new special request DTO instance
   *
   * @param array<string, mixed> $data The special request data
   * @throws InvalidArgumentException If required data is missing
   */
  public function __construct(array $data)
  {
    // Validate required fields
    if (!isset($data['requestCode']) || empty($data['requestCode'])) {
      throw new InvalidArgumentException('Special request code is required');
    }

    if (!isset($data['requestName']) || empty($data['requestName'])) {
      throw new InvalidArgumentException('Special request name is required');
    }

    // Set request details
    $this->requestCode = $data['requestCode'];
    $this->requestName = $data['requestName'];
    $this->requestDescription = $data['requestDescription'] ?? null;

    // Set timing information
    $this->startDate = isset($data['startDate'])
      ? Carbon::parse($data['startDate'])
      : null;

    $this->endDate = isset($data['endDate'])
      ? Carbon::parse($data['endDate'])
      : null;

    $this->timeSpan = $data['timeSpan'] ?? null;

    // Set additional information
    $this->comments = $data['comments'] ?? null;
    $this->confirmed = $data['confirmed'] ?? false;
    $this->quantity = $data['quantity'] ?? 1;
    $this->roomStayIndex = $data['roomStayIndex'] ?? null;

    // Validate date logic if both are provided
    if ($this->startDate && $this->endDate && $this->startDate->greaterThan($this->endDate)) {
      throw new InvalidArgumentException('End date must be after start date for special request');
    }
  }

  /**
   * Get formatted start date (YYYY-MM-DD)
   *
   * @return string|null Formatted date or null if not set
   */
  public function getFormattedStartDate(): ?string
  {
    return $this->startDate?->format('Y-m-d');
  }

  /**
   * Get formatted end date (YYYY-MM-DD)
   *
   * @return string|null Formatted date or null if not set
   */
  public function getFormattedEndDate(): ?string
  {
    return $this->endDate?->format('Y-m-d');
  }

  /**
   * Check if this special request applies to a specific stay
   *
   * @return bool True if this applies to a specific room stay
   */
  public function appliesToSpecificStay(): bool
  {
    return $this->roomStayIndex !== null;
  }

  /**
   * Check if this special request applies to a specific date range
   *
   * @return bool True if this applies to a specific date range
   */
  public function hasDateRange(): bool
  {
    return $this->startDate !== null && $this->endDate !== null;
  }

  /**
   * Convert common Centrium property booking comments to special requests
   *
   * @param mixed $propertyBookingComment The Centrium property booking comment
   * @return self|null A SpecialRequestDto if applicable, or null
   */
  public static function fromCentriumPropertyBookingComment($propertyBookingComment): ?self
  {
    // Convert to array if not already
    $commentData = is_array($propertyBookingComment)
      ? $propertyBookingComment
      : $propertyBookingComment->toArray();

    // Skip if not a special request (no additional cost)
    // This logic would need to be customized for your specific setup
    if (
      isset($commentData['booking_comment_type']['RelevantToPayment']) &&
      $commentData['booking_comment_type']['RelevantToPayment']
    ) {
      return null;
    }

    $commentType = $commentData['booking_comment_type'] ?? [];
    $commentTypeId = $commentData['booking_comment_type_id'] ?? null;

    // Only process comments without costs attached
    return new self([
      'requestCode' => 'SR' . ($commentTypeId ?? '000'),
      'requestName' => $commentType['BookingCommentType'] ?? 'Special Request',
      'requestDescription' => $commentData['BookingComment'] ?? null,
      'comments' => $commentData['BookingComment'] ?? null,
      'confirmed' => true,
      'quantity' => 1,
      'roomStayIndex' => $commentData['property_room_booking_id'] ?? null,
    ]);
  }

  /**
   * Convert to array representation
   *
   * @return array<string, mixed> The special request data as an array
   */
  public function toArray(): array
  {
    return [
      'requestCode' => $this->requestCode,
      'requestName' => $this->requestName,
      'requestDescription' => $this->requestDescription,
      'startDate' => $this->getFormattedStartDate(),
      'endDate' => $this->getFormattedEndDate(),
      'timeSpan' => $this->timeSpan,
      'comments' => $this->comments,
      'confirmed' => $this->confirmed,
      'quantity' => $this->quantity,
      'roomStayIndex' => $this->roomStayIndex,
    ];
  }
}
