<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * SystemUser Model
 *
 * This model represents system users in the Centrium database.
 * These are internal users who can perform operations and manage the system.
 *
 * @property int $SystemUserID
 * @property string $UserName
 * @property string $Email
 * @property string $Password
 * @property int $UserGroupID
 * @property bool|null $CurrentSystemUser
 * @property Carbon|null $PasswordExpires
 * @property bool $ContractAdministrator
 * @property string|null $XenonSystemUserCode
 * @property string|null $Phone
 * @property string|null $PaymentReminderNotificationSignature
 */
class SystemUser extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     * Using default Centrium connection.
     */
    protected $connection = 'centrium';

    /**
     * The table associated with the model.
     * Following Centrium naming convention.
     */
    protected $table = 'SystemUser';

    /**
     * The primary key for the model.
     * Following Centrium convention of [TableName]ID
     */
    protected $primaryKey = 'SystemUserID';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'UserName',
        'Email',
        'Password',
        'UserGroupID',
        'CurrentSystemUser',
        'PasswordExpires',
        'ContractAdministrator',
        'XenonSystemUserCode',
        'Phone',
        'PaymentReminderNotificationSignature'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'Password',
    ];

    /**
     * The model's default values for attributes.
     */
    protected $attributes = [
        'ContractAdministrator' => false,
        'CurrentSystemUser' => true
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'CurrentSystemUser' => 'boolean',
        'ContractAdministrator' => 'boolean',
        'PasswordExpires' => 'datetime',
        'SystemUserID' => 'integer',
        'UserGroupID' => 'integer'
    ];

    /**
     * Laravel timestamp configuration.
     * This table doesn't use Laravel's standard timestamps.
     */
    public $timestamps = false;

    /**
     * Get the user group that this user belongs to.
     * Note: You would need to create a UserGroup model as well.
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'UserGroupID', 'UserGroupID');
    }

    /**
     * Get all properties this user has access to.
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(
            Property::class,
            'SystemUserProperty',
            'SystemUserID',
            'PropertyID',
            'SystemUserID',
            'PropertyID'
        );
    }

    /**
     * Get all TravelClick logs created by this user.
     */
    public function travelClickLogs(): HasMany
    {
        return $this->hasMany(
            \App\TravelClick\Models\TravelClickLog::class,
            'SystemUserID',
            'SystemUserID'
        );
    }

    /**
     * Get all TravelClick errors resolved by this user.
     */
    public function resolvedTravelClickErrors(): HasMany
    {
        return $this->hasMany(
            \App\TravelClick\Models\TravelClickErrorLog::class,
            'ResolvedByUserID',
            'SystemUserID'
        );
    }

    /**
     * Get all TravelClick errors created by this user.
     */
    public function createdTravelClickErrors(): HasMany
    {
        return $this->hasMany(
            \App\TravelClick\Models\TravelClickErrorLog::class,
            'SystemUserID',
            'SystemUserID'
        );
    }

    /**
     * Get all bookings created by this user.
     */
/*
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'SystemUserID', 'SystemUserID');
    }
*/
    /**
     * Get all contracts created by this user.
     */
/*
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'SystemUserID', 'SystemUserID');
    }
*/
    // Scopes for common queries

    /**
     * Scope to filter active users.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('CurrentSystemUser', true);
    }

    /**
     * Scope to filter contract administrators.
     */
    public function scopeContractAdministrators(Builder $query): Builder
    {
        return $query->where('ContractAdministrator', true);
    }

    /**
     * Scope to filter users with expired passwords.
     */
    public function scopeExpiredPasswords(Builder $query): Builder
    {
        return $query->where('PasswordExpires', '<', now())
            ->whereNotNull('PasswordExpires');
    }

    /**
     * Scope to filter users by user group.
     */
    public function scopeInGroup(Builder $query, int $userGroupId): Builder
    {
        return $query->where('UserGroupID', $userGroupId);
    }

    /**
     * Scope to search users by name or email.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('UserName', 'like', "%{$search}%")
                ->orWhere('Email', 'like', "%{$search}%");
        });
    }

    // Accessor methods

    /**
     * Get the user's full display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->UserName;
    }

    /**
     * Check if the user's password has expired.
     */
    public function getPasswordExpiredAttribute(): bool
    {
        return $this->PasswordExpires && $this->PasswordExpires < now();
    }

    /**
     * Get the user's initials for display.
     */
    public function getInitialsAttribute(): string
    {
        $nameParts = explode(' ', $this->UserName);
        $initials = '';

        foreach ($nameParts as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        return substr($initials, 0, 2);
    }

    /**
     * Check if the user is currently logged in or active.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->CurrentSystemUser;
    }

    // Helper methods

    /**
     * Check if user has access to a specific property.
     */
    public function hasAccessToProperty(int $propertyId): bool
    {
        return $this->properties()->where('PropertyID', $propertyId)->exists();
    }

    /**
     * Check if user can resolve TravelClick errors.
     * This could be based on user group or specific permissions.
     */
    public function canResolveTravelClickErrors(): bool
    {
        // You might want to implement more sophisticated permission logic here
        return $this->ContractAdministrator || $this->userGroup->name === 'TravelClick Admins';
    }

    /**
     * Get user's recent activity summary.
     */
    public function getRecentActivity(int $days = 7): array
    {
        $since = now()->subDays($days);

        return [
            'total_travel_click_logs' => $this->travelClickLogs()
                ->where('DateCreated', '>=', $since)
                ->count(),

            'errors_resolved' => $this->resolvedTravelClickErrors()
                ->where('ResolvedAt', '>=', $since)
                ->count(),

            'bookings_created' => $this->bookings()
                ->where('BookingDate', '>=', $since)
                ->count(),

            'contracts_created' => $this->contracts()
                ->where('ContractDate', '>=', $since)
                ->count()
        ];
    }

    /**
     * Set a user as inactive/current.
     */
    public function setActive(bool $active = true): bool
    {
        $this->CurrentSystemUser = $active;
        return $this->save();
    }

    /**
     * Update user's password expiration date.
     */
    public function extendPasswordExpiry(int $days = 90): bool
    {
        $this->PasswordExpires = now()->addDays($days);
        return $this->save();
    }

    /**
     * Get all properties this user can access with their names.
     */
    public function getAccessiblePropertiesWithNames(): array
    {
        return $this->properties()
            ->select('PropertyID', 'Name', 'Reference')
            ->get()
            ->mapWithKeys(function ($property) {
                return [$property->PropertyID => $property->Name];
            })
            ->toArray();
    }

    /**
     * Create a formatted string for user selection dropdowns.
     */
    public function getSelectOptionLabelAttribute(): string
    {
        return "{$this->UserName} ({$this->Email})";
    }

    /**
     * Get user's permissions summary for TravelClick operations.
     */
    public function getTravelClickPermissions(): array
    {
        // This is a placeholder - you'd implement actual permission logic
        // based on your business rules

        $isAdmin = $this->ContractAdministrator;
        $canManageProperties = $this->properties()->count() > 0;

        return [
            'can_view_logs' => true,
            'can_create_sync_jobs' => $canManageProperties,
            'can_resolve_errors' => $this->canResolveTravelClickErrors(),
            'can_manage_configurations' => $isAdmin,
            'can_access_all_properties' => $isAdmin,
            'accessible_property_count' => $this->properties()->count()
        ];
    }

    /**
     * Search users suitable for assigning TravelClick error resolution.
     */
    public static function getTravelClickErrorResolvers(): Builder
    {
        return self::active()
            ->where(function ($query) {
                $query->where('ContractAdministrator', true)
                    ->orWhereHas('userGroup', function ($q) {
                        $q->where('UserGroup', 'like', '%TravelClick%')
                            ->orWhere('UserGroup', 'like', '%Admin%');
                    });
            });
    }

    /**
     * Get performance metrics for TravelClick operations.
     */
    public function getTravelClickPerformanceMetrics(int $days = 30): array
    {
        $since = now()->subDays($days);

        $totalLogs = $this->travelClickLogs()
            ->where('DateCreated', '>=', $since)
            ->count();

        $successfulLogs = $this->travelClickLogs()
            ->where('DateCreated', '>=', $since)
            ->where('Status', 'completed')
            ->count();

        $errorsResolved = $this->resolvedTravelClickErrors()
            ->where('ResolvedAt', '>=', $since)
            ->count();

        $avgResolutionTime = $this->resolvedTravelClickErrors()
            ->where('ResolvedAt', '>=', $since)
            ->selectRaw('AVG(DATEDIFF(SECOND, DateCreated, ResolvedAt)) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total_operations' => $totalLogs,
            'success_rate' => $totalLogs > 0 ? round(($successfulLogs / $totalLogs) * 100, 2) : 0,
            'errors_resolved' => $errorsResolved,
            'avg_resolution_time_minutes' => $avgResolutionTime ? round($avgResolutionTime / 60, 2) : null,
            'period_days' => $days
        ];
    }
}
