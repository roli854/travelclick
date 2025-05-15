<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Property extends Model
{
    protected $table = 'Property';
    protected $primaryKey = 'PropertyID';
    public $timestamps = false;

    protected $fillable = [
        'Reference',
        'Name',
        'ShortName',
        'PropertyGroupID',
        'PropertyTypeID',
        'GeographyLevel3ID',
        'Rating',
        'Logo',
        'Address1',
        'Address2',
        'TownCity',
        'County',
        'PostcodeZip',
        'Country',
        'Telephone',
        'Fax',
        'Email',
        'Website',
        'ContactName',
        'ContactPosition',
        'ContactTelephone',
        'ContactFax',
        'ContactEmail',
        'ContactMobile',
        'MaximumRooms',
        'CurrentProperty',
        'Notes',
        'PayeeID',
        'OptionExpiryUnit',
        'OptionExpiryAmount',
        'PrioritySelling',
        'ExcludeFromInvoices',
        'InvoicePeriod',
        'SyncGUID',
        'SyncStatus',
        'SyncSystemUserID',
        'SyncRequired',
        'EditStatus',
        'ExcludeFromRes',
        'LegalEntityName',
        'PhysicalRooms',
        'PropertyCode',
        'AccpacCode',
        'ChildAgeFrom',
        'ChildAgeTo',
        'YouthAgeFrom',
        'YouthAgeTo',
        'BookingConfirmation',
        'InterPropertyNHCID',
        'TermsTemplate',
        'WebhookSignature',
        'MerchantID',
        'Token',
        'LogoRatio',
    ];

    protected $casts = [
        'CurrentProperty' => 'boolean',
        'PrioritySelling' => 'boolean',
        'ExcludeFromInvoices' => 'boolean',
        'SyncRequired' => 'boolean',
        'ExcludeFromRes' => 'boolean',
        'LogoRatio' => 'float',
        'MaximumRooms' => 'integer',
        'OptionExpiryAmount' => 'integer',
        'InvoicePeriod' => 'integer',
        'PhysicalRooms' => 'integer',
        'PropertyCode' => 'integer',
        'ChildAgeFrom' => 'integer',
        'ChildAgeTo' => 'integer',
        'YouthAgeFrom' => 'integer',
        'YouthAgeTo' => 'integer',
    ];

    /**
     * Get all room types for this property
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(PropertyRoomType::class, 'PropertyID', 'PropertyID');
    }

    /**
     * Get active room types only
     */
    public function activeRoomTypes(): HasMany
    {
        return $this->roomTypes()->where('Current', 1);
    }

    /**
     * Get non-virtual room types only
     */
    public function physicalRoomTypes(): HasMany
    {
        return $this->activeRoomTypes()->where('VirtualRoom', 0);
    }

    /**
     * Get room type by code
     */
    public function getRoomTypeByCode(string $code): ?PropertyRoomType
    {
        return $this->activeRoomTypes()
            ->where('Code', $code)
            ->first();
    }

    /**
     * Check if room type code exists for this property
     */
    public function hasRoomType(string $code): bool
    {
        return $this->activeRoomTypes()
            ->where('Code', $code)
            ->exists();
    }

    /**
     * Get property group relationship
     */
/*    public function propertyGroup(): BelongsTo
    {
        return $this->belongsTo(PropertyGroup::class, 'PropertyGroupID', 'PropertyGroupID');
    }
*/
    /**
     * Get property type relationship
     */
/*    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'PropertyTypeID', 'PropertyTypeID');
    }
*/
    /**
     * Get geography level 3 relationship
     */
/*    public function geographyLevel3(): BelongsTo
    {
        return $this->belongsTo(GeographyLevel3::class, 'GeographyLevel3ID', 'GeographyLevel3ID');
    }
*/
    /**
     * Scope to get current properties only
     */
    public function scopeCurrent($query)
    {
        return $query->where('CurrentProperty', 1);
    }

    /**
     * Scope to exclude properties from reservations
     */
    public function scopeActiveForReservations($query)
    {
        return $query->where('ExcludeFromRes', 0)
            ->where('CurrentProperty', 1);
    }
}
