<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table maps Centrium properties to TravelClick hotel codes.
     * It's like a phone book that translates between our internal IDs
     * and TravelClick's way of identifying hotels.
     */
    public function up(): void
    {
        Schema::connection('centriumLog')->create('TravelClickPropertyMappings', function (Blueprint $table) {
            $table->id('PropertyMappingID');

            // Property identification
            $table->integer('PropertyID')->unique();     // Centrium PropertyID
            $table->string('HotelCode', 10);            // TravelClick hotel code
            $table->string('PropertyReference', 20)->nullable(); // Centrium Property.Reference

            // TravelClick specific settings
            $table->string('TravelClickUsername', 50)->nullable(); // Property-specific credentials
            $table->string('TravelClickPassword', 100)->nullable(); // Encrypted password
            $table->string('Environment', 10)->default('test');     // 'test' or 'production'

            // Sync configuration for this property
            $table->boolean('InventoryEnabled')->default(true);
            $table->boolean('RatesEnabled')->default(true);
            $table->boolean('ReservationsEnabled')->default(true);
            $table->boolean('RestrictionsEnabled')->default(true);
            $table->boolean('GroupsEnabled')->default(true);

            // Feature flags
            $table->boolean('SupportsOversell')->default(false);
            $table->boolean('SupportsLinkedRates')->default(true);
            $table->boolean('PropertyLevelInventory')->default(false);

            // Timing preferences
            $table->integer('SyncIntervalMinutes')->default(5);  // How often to sync
            $table->time('FullSyncTime')->default('02:00:00');   // When to do full sync
            $table->boolean('BatchProcessing')->default(true);   // Use batch processing

            // Status and health
            $table->boolean('IsActive')->default(true);
            $table->dateTime('LastSync')->nullable();
            $table->dateTime('LastSuccessfulSync')->nullable();
            $table->string('HealthStatus', 20)->default('healthy'); // 'healthy', 'warning', 'error'
            $table->text('HealthNotes')->nullable();

            // Standard audit fields
            $table->integer('SystemUserID')->default(0);
            $table->dateTime('DateCreated')->default(now());
            $table->dateTime('DateModified')->useCurrent()->useCurrentOnUpdate();

            // Indexes for performance
            $table->index('HotelCode');
            $table->index(['Environment', 'IsActive']);
            $table->index('LastSync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('centriumLog')->dropIfExists('TravelClickPropertyMappings');
    }
};
