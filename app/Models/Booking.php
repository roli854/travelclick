<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

class Booking extends Model
{
  protected $table = 'Booking';
  protected $primaryKey = 'BookingID';
  public $timestamps = false;

  protected $fillable = [
    'Source',
    'BookingReference',
    'BookingType',
    'CustomerDetailID',
    'Status',
    'AccountStatus',
    'BookingSourceID',
    'BookingAuthorityID',
    'PublicBooking',
    'TradeID',
    'TradeContactID',
    'TradeReference',
    'BookingDate',
    'OptionExpiryDateTime',
    'SystemUserID',
    'LeadGuestTitle',
    'LeadGuestFirstName',
    'LeadGuestLastName',
    'DateOfBirth',
    'PassportNumber',
    'LeadGuestAddress1',
    'LeadGuestAddress2',
    'LeadGuestTownCity',
    'LeadGuestCounty',
    'LeadGuestPostcode',
    'LeadGuestBookingCountryID',
    'LeadGuestPhone',
    'LeadGuestFax',
    'LeadGuestEmail',
    'LastModifiedDateTime',
    'TradeConfirmed',
    'Outbooked',
    'XenonID',
    'XenonPackageCode',
    'XenonPackageDesc',
    'PaymentCode',
    'tmpcfix',
    'OutbookedNote',
    'InitialSystemUserID',
    'PaidAtProperty',
    'DepositAmount',
    'DepositAmountValue',
    'DepositDueDate',
    'DepositDueDateValue',
    'DepositBillingType',
    'BalanceDueDate',
    'BalanceDueDateValue',
    'BalanceBillingType',
    'LinkedBookingReference',
    'GuestTypeID',
    'AgentID',
    'CommissionRate',
    'BookingGroupID',
    'OffsetType',
    'OffsetDays',
    'PaymentTermSpecificDate',
    'BalanceOffsetType',
    'BalanceOffsetDays',
    'PaymentTermBalanceSpecificDate',
    'RebookFrom',
    'RebookTo',
    'UKBookingReference',
    'ReinstatedFrom',
    'ManualDeposit',
    'UKBookingID',
    'XenonRateCode',
  ];

  protected $casts = [
    'BookingDate' => 'datetime',
    'OptionExpiryDateTime' => 'datetime',
    'DateOfBirth' => 'datetime',
    'LastModifiedDateTime' => 'datetime',
    'PaymentTermSpecificDate' => 'datetime',
    'PaymentTermBalanceSpecificDate' => 'datetime',
    'PublicBooking' => 'boolean',
    'TradeConfirmed' => 'boolean',
    'Outbooked' => 'boolean',
    'tmpcfix' => 'boolean',
    'PaidAtProperty' => 'boolean',
    'ManualDeposit' => 'boolean',
    'PaymentCode' => 'decimal:4',
    'DepositAmountValue' => 'decimal:2',
    'CommissionRate' => 'float',
  ];

  /**
   * Get the booking source associated with the booking.
   */
  /*public function bookingSource(): BelongsTo
  {
    return $this->belongsTo(BookingSource::class, 'BookingSourceID', 'BookingSourceID');
  }

  /**
   * Get the booking authority associated with the booking.
   */
  /*public function bookingAuthority(): BelongsTo
  {
    return $this->belongsTo(BookingAuthority::class, 'BookingAuthorityID', 'BookingAuthorityID');
  }

  /**
   * Get the trade associated with the booking.
   */
  /*public function trade(): BelongsTo
  {
    return $this->belongsTo(Trade::class, 'TradeID', 'TradeID');
  }

  /**
   * Get the trade contact associated with the booking.
   */
  /*public function tradeContact(): BelongsTo
  {
    return $this->belongsTo(TradeContact::class, 'TradeContactID', 'TradeContactID');
  }

  /**
   * Get the system user who created the booking.
   */
  public function systemUser(): BelongsTo
  {
    return $this->belongsTo(SystemUser::class, 'SystemUserID', 'SystemUserID');
  }

  /**
   * Get the initial system user.
   */
  public function initialSystemUser(): BelongsTo
  {
    return $this->belongsTo(SystemUser::class, 'InitialSystemUserID', 'SystemUserID');
  }

  /**
   * Get the country of the lead guest.
   */
  /*public function country(): BelongsTo
  {
    return $this->belongsTo(BookingCountry::class, 'LeadGuestBookingCountryID', 'BookingCountryID');
  }

  /**
   * Get the guest type for this booking.
   */
  /*public function guestType(): BelongsTo
  {
    return $this->belongsTo(GuestType::class, 'GuestTypeID', 'GuestTypeID');
  }

  /**
   * Get the agent associated with this booking.
   */
  /*public function agent(): BelongsTo
  {
    return $this->belongsTo(Agent::class, 'AgentID', 'AgentID');
  }

  /**
   * Get the booking group associated with this booking.
   */
  /*public function bookingGroup(): BelongsTo
  {
    return $this->belongsTo(BookingGroup::class, 'BookingGroupID', 'BookingGroupID');
  }

  /**
   * Get the property bookings associated with this booking.
   */
  /*public function propertyBookings(): HasMany
  {
    return $this->hasMany(PropertyBooking::class, 'BookingID', 'BookingID');
  }

  /**
   * Get the booking comments.
   */
  /*public function comments(): HasMany
  {
    return $this->hasMany(BookingComment::class, 'BookingID', 'BookingID');
  }

  /**
   * Get the booking payment schedule.
   */
  /*public function paymentSchedule(): HasMany
  {
    return $this->hasMany(BookingPaymentSchedule::class, 'BookingID', 'BookingID');
  }

  /**
   * Get the booking due date.
   */
  /*public function dueDate(): HasOne
  {
    return $this->hasOne(BookingDueDate::class, 'BookingID', 'BookingID');
  }

  /**
   * Get the rebooking source booking.
   */
  public function rebookSource(): BelongsTo
  {
    return $this->belongsTo(self::class, 'RebookFrom', 'BookingID');
  }

  /**
   * Get the rebooking target booking.
   */
  public function rebookTarget(): BelongsTo
  {
    return $this->belongsTo(self::class, 'RebookTo', 'BookingID');
  }

  /**
   * Get the reinstated from booking.
   */
  public function reinstatedFrom(): BelongsTo
  {
    return $this->belongsTo(self::class, 'ReinstatedFrom', 'BookingID');
  }

  /**
   * Get the full name of the lead guest.
   */
  public function getLeadGuestFullNameAttribute(): string
  {
    return trim($this->LeadGuestTitle . ' ' . $this->LeadGuestFirstName . ' ' . $this->LeadGuestLastName);
  }

  /**
   * Determine if the booking is active.
   */
  public function isActive(): bool
  {
    return !in_array($this->Status, ['Cancelled', 'No Show']);
  }

  /**
   * Determine if the booking is cancelled.
   */
  public function isCancelled(): bool
  {
    return $this->Status === 'Cancelled';
  }

  /**
   * Get the arrival date of the booking from the first property booking.
   */
  public function getArrivalDateAttribute(): ?Carbon
  {
    $propertyBooking = $this->propertyBookings()->first();
    return $propertyBooking ? $propertyBooking->ArrivalDate : null;
  }

  /**
   * Get the departure date of the booking from the first property booking.
   */
  public function getDepartureDateAttribute(): ?Carbon
  {
    $propertyBooking = $this->propertyBookings()->first();
    return $propertyBooking ? $propertyBooking->DepartureDate : null;
  }

  /**
   * Get the duration of the stay in nights.
   */
  public function getDurationNightsAttribute(): ?int
  {
    if (!$this->arrivalDate || !$this->departureDate) {
      return null;
    }

    return $this->arrivalDate->diffInDays($this->departureDate);
  }

  /**
   * Scope a query to only include active bookings.
   */
  public function scopeActive($query)
  {
    return $query->whereNotIn('Status', ['Cancelled', 'No Show']);
  }

  /**
   * Scope a query to only include cancelled bookings.
   */
  public function scopeCancelled($query)
  {
    return $query->where('Status', 'Cancelled');
  }

  /**
   * Scope a query to only include outbooked bookings.
   */
  public function scopeOutbooked($query)
  {
    return $query->where('Outbooked', true);
  }

  /**
   * Scope a query to only include bookings with specific booking source.
   */
  public function scopeBySource($query, $sourceId)
  {
    return $query->where('BookingSourceID', $sourceId);
  }

  /**
   * Scope a query to only include bookings with specific booking type.
   */
  public function scopeByType($query, $type)
  {
    return $query->where('BookingType', $type);
  }

  /**
   * Scope a query to only include bookings for a specific trade.
   */
  public function scopeByTrade($query, $tradeId)
  {
    return $query->where('TradeID', $tradeId);
  }

  /**
   * Scope a query to only include bookings with a specific booking reference.
   */
  public function scopeByReference($query, $reference)
  {
    return $query->where('BookingReference', $reference);
  }

  /**
   * Scope a query to only include bookings with specific arrival date (using propertyBookings).
   */
  public function scopeByArrivalDate($query, $date)
  {
    return $query->whereHas('propertyBookings', function ($query) use ($date) {
      return $query->whereDate('ArrivalDate', $date);
    });
  }

  /**
   * Scope a query to only include bookings with specific departure date (using propertyBookings).
   */
  public function scopeByDepartureDate($query, $date)
  {
    return $query->whereHas('propertyBookings', function ($query) use ($date) {
      return $query->whereDate('DepartureDate', $date);
    });
  }

  /**
   * Get all bookings for a specific property.
   */
  public function scopeForProperty($query, $propertyId)
  {
    return $query->whereHas('propertyBookings', function ($query) use ($propertyId) {
      return $query->where('PropertyID', $propertyId);
    });
  }

  /**
   * Check if the booking has any comments.
   */
  public function hasComments(): bool
  {
    return $this->comments()->count() > 0;
  }

  /**
   * Check if the booking has any special requests (comments with specific types).
   */
  public function hasSpecialRequests(): bool
  {
    return $this->comments()
      ->whereHas('bookingCommentType', function ($query) {
        return $query->where('HotelFlag', true);
      })
      ->count() > 0;
  }

  /**
   * Check if the booking is from TravelClick.
   */
  public function isTravelClickBooking(): bool
  {
    return $this->Source === 'XML_TRVC';
  }

  /**
   * Format for TravelClick synchronization.
   */
  public function toTravelClickFormat(): array
  {
    return [
      'booking_id' => $this->BookingID,
      'reference' => $this->BookingReference,
      'status' => $this->Status,
      'type' => $this->BookingType,
      'guest' => [
        'title' => $this->LeadGuestTitle,
        'first_name' => $this->LeadGuestFirstName,
        'last_name' => $this->LeadGuestLastName,
        'email' => $this->LeadGuestEmail,
        'phone' => $this->LeadGuestPhone,
        'address' => [
          'line1' => $this->LeadGuestAddress1,
          'line2' => $this->LeadGuestAddress2,
          'city' => $this->LeadGuestTownCity,
          'county' => $this->LeadGuestCounty,
          'postcode' => $this->LeadGuestPostcode,
          'country_id' => $this->LeadGuestBookingCountryID
        ]
      ],
      'booking_date' => $this->BookingDate ? $this->BookingDate->format('Y-m-d\TH:i:s') : null,
      'last_modified' => $this->LastModifiedDateTime ? $this->LastModifiedDateTime->format('Y-m-d\TH:i:s') : null,
      'source' => [
        'id' => $this->BookingSourceID,
        'name' => $this->bookingSource ? $this->bookingSource->BookingSource : null
      ],
      'commission_rate' => $this->CommissionRate,
      'xenon_data' => [
        'id' => $this->XenonID,
        'package_code' => $this->XenonPackageCode,
        'rate_code' => $this->XenonRateCode
      ]
    ];
  }
}
