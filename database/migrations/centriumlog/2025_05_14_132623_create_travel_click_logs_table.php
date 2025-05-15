<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table logs all TravelClick operations for audit purposes.
     * Following Centrium conventions with PascalCase column names.
     */
    public function up(): void
    {
        Schema::connection('centriumLog')->create('TravelClickLogs', function (Blueprint $table) {
            $table->id('TravelClickLogID');

            // Message identification
            $table->string('MessageID', 50)->unique();  // UUID for each message
            $table->string('Direction', 10);            // 'outbound' or 'inbound'
            $table->string('MessageType', 20);          // 'inventory', 'rates', 'reservation', etc.

            // Hotel/Property information
            $table->integer('PropertyID')->index();     // Links to Centrium.Property
            $table->string('HotelCode', 10)->nullable(); // TravelClick hotel code

            // Message content (following Centrium pattern of using text for large content)
            $table->text('RequestXML')->nullable();      // Outbound XML sent to TravelClick
            $table->text('ResponseXML')->nullable();     // Response received from TravelClick

            // Status and error tracking
            $table->string('Status', 20);               // Using SyncStatus enum values
            $table->string('ErrorType', 30)->nullable(); // Using ErrorType enum values
            $table->text('ErrorMessage')->nullable();    // Detailed error description
            $table->integer('RetryCount')->default(0);   // How many times retried

            // Timing information
            $table->dateTime('StartedAt');               // When operation started
            $table->dateTime('CompletedAt')->nullable(); // When operation completed
            $table->integer('DurationMs')->nullable();   // Duration in milliseconds

            // Standard Centrium audit fields
            $table->integer('SystemUserID')->default(0); // Who initiated the operation
            $table->dateTime('DateCreated')->default(now());
            $table->dateTime('DateModified')->useCurrent()->useCurrentOnUpdate();

            // Additional metadata
            $table->json('Metadata')->nullable();        // Additional context data
            $table->string('JobID', 50)->nullable();     // Horizon job ID for tracking

            // Indexes for performance
            $table->index(['PropertyID', 'MessageType', 'DateCreated']);
            $table->index(['Status', 'DateCreated']);
            $table->index(['Direction', 'MessageType']);
            $table->index('HotelCode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('centriumLog')->dropIfExists('TravelClickLogs');
    }
};
