<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyRoomType extends Model
{
  protected $table = 'PropertyRoomType';
  protected $primaryKey = 'PropertyRoomTypeID';
  public $timestamps = false;

  protected $fillable = [
    'PropertyID',
    'RoomTypeID',
    'RoomViewID',
    'Reference',
    'MealBasisID',
    'StandardAllocation',
    'MinOcc',
    'StdOcc',
    'MaxOcc',
    'MinAdults',
    'MaxAdults',
    'MaxChildren',
    'Infants',
    'InfantsOccupancy',
    'ChildAgeFrom',
    'ChildAgeTo',
    'YouthAgeFrom',
    'YouthAgeTo',
    'AdjoiningRooms',
    'DisabledFacilities',
    'SmokingRooms',
    'ExtraBedTypeID',
    'Sequence',
    'SyncGUID',
    'Code',
    'VirtualRoom',
    'CloseoutDate',
    'Current',
    'Description',
  ];

  protected $casts = [
    'StandardAllocation' => 'integer',
    'MinOcc' => 'integer',
    'StdOcc' => 'integer',
    'MaxOcc' => 'integer',
    'MinAdults' => 'integer',
    'MaxAdults' => 'integer',
    'MaxChildren' => 'integer',
    'Infants' => 'boolean',
    'InfantsOccupancy' => 'boolean',
    'ChildAgeFrom' => 'integer',
    'ChildAgeTo' => 'integer',
    'YouthAgeFrom' => 'integer',
    'YouthAgeTo' => 'integer',
    'AdjoiningRooms' => 'boolean',
    'DisabledFacilities' => 'boolean',
    'SmokingRooms' => 'boolean',
    'Sequence' => 'integer',
    'VirtualRoom' => 'boolean',
    'Current' => 'boolean',
    'CloseoutDate' => 'datetime',
  ];

  /**
   * Get the property that owns this room type
   */
  public function property(): BelongsTo
  {
    return $this->belongsTo(Property::class, 'PropertyID', 'PropertyID');
  }

  /**
   * Get the base room type
   */
/*  public function roomType(): BelongsTo
  {
    return $this->belongsTo(RoomType::class, 'RoomTypeID', 'RoomTypeID');
  }
*/
  /**
   * Get the room view
   */
/*  public function roomView(): BelongsTo
  {
    return $this->belongsTo(RoomView::class, 'RoomViewID', 'RoomViewID');
  }
*/
  /**
   * Get the meal basis
   */
/*  public function mealBasis(): BelongsTo
  {
    return $this->belongsTo(MealBasis::class, 'MealBasisID', 'MealBasisID');
  }
*/
  /**
   * Get the extra bed type
   */
/*  public function extraBedType(): BelongsTo
  {
    return $this->belongsTo(ExtraBedType::class, 'ExtraBedTypeID', 'ExtraBedTypeID');
  }
*/
  /**
   * Get bed types for this room type
   */
/*  public function bedTypes(): HasMany
  {
    return $this->hasMany(PropertyRoomTypeBedType::class, 'PropertyRoomTypeID', 'PropertyRoomTypeID');
  }
*/
  /**
   * Get room bookings for this room type
   */
/*  public function roomBookings(): HasMany
  {
    return $this->hasMany(PropertyRoomBooking::class, 'PropertyRoomTypeID', 'PropertyRoomTypeID');
  }
*/
  /**
   * Scope for current/active room types
   */
  public function scopeCurrent($query)
  {
    return $query->where('Current', 1);
  }

  /**
   * Scope for physical (non-virtual) room types
   */
  public function scopePhysical($query)
  {
    return $query->where('VirtualRoom', 0);
  }

  /**
   * Scope for virtual room types
   */
  public function scopeVirtual($query)
  {
    return $query->where('VirtualRoom', 1);
  }

  /**
   * Scope for room types with available inventory
   */
  public function scopeAvailable($query)
  {
    return $query->current()
      ->where('StandardAllocation', '>', 0);
  }

  /**
   * Check if room type is available for booking
   */
  public function isAvailable(): bool
  {
    return $this->Current
      && !$this->VirtualRoom
      && $this->StandardAllocation > 0
      && (!$this->CloseoutDate || $this->CloseoutDate > now());
  }

  /**
   * Get display name for room type
   */
  public function getDisplayName(): string
  {
    $name = $this->roomType?->RoomType ?? 'Unknown Room Type';
    if ($this->roomView?->RoomView && $this->roomView->RoomView !== 'Standard') {
      $name .= ' - ' . $this->roomView->RoomView;
    }
    return $name;
  }
}
