<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table tracks the synchronization status of different data types
     * between Centrium and TravelClick. It's like a status board showing
     * what's been synced, what's pending, and what failed.
     */
    public function up(): void
    {
        Schema::connection('centriumLog')->create('TravelClickSyncStatus', function (Blueprint $table) {
            $table->id('SyncStatusID');

            // What is being synced
            $table->string('SyncType', 20);              // 'inventory', 'rates', 'reservation', etc.
            $table->integer('PropertyID')->index();      // Which property
            $table->string('EntityType', 50);            // 'room_type', 'rate_plan', 'booking', etc.
            $table->string('EntityID', 50);             // ID of the specific entity being synced
            $table->string('ExternalReference', 50)->nullable(); // TravelClick reference

            // Status tracking
            $table->string('Status', 20);               // Current sync status
            $table->string('LastMessageType', 20)->nullable(); // Last message type sent
            $table->string('LastMessageID', 50)->nullable();   // Last message ID

            // Timing information
            $table->dateTime('LastSyncAttempt')->nullable();    // When last tried
            $table->dateTime('LastSuccessfulSync')->nullable(); // When last succeeded
            $table->dateTime('NextRetryAt')->nullable();        // When to retry

            // Retry logic
            $table->integer('RetryCount')->default(0);      // How many retries
            $table->integer('MaxRetries')->default(3);      // Max allowed retries
            $table->text('LastError')->nullable();          // Last error message

            // Change tracking
            $table->dateTime('CentriumLastModified')->nullable(); // When Centrium data changed
            $table->dateTime('TravelClickLastModified')->nullable(); // When TravelClick confirmed
            $table->json('ChangeLog')->nullable();           // What changed

            // Standard audit fields
            $table->integer('SystemUserID')->default(0);
            $table->dateTime('DateCreated')->default(now());
            $table->dateTime('DateModified')->useCurrent()->useCurrentOnUpdate();

            // Unique constraint - one sync status per entity
            $table->unique(['SyncType', 'PropertyID', 'EntityType', 'EntityID'], 'UQ_SyncStatus_Entity');

            // Indexes for performance
            $table->index(['Status', 'NextRetryAt']);
            $table->index(['PropertyID', 'SyncType']);
            $table->index('LastSuccessfulSync');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('centriumLog')->dropIfExists('TravelClickSyncStatus');
    }
};
