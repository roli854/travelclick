<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table maintains a detailed history of all messages exchanged
     * with TravelClick. It's like keeping copies of all business letters
     * in a filing cabinet for future reference.
     */
    public function up(): void
    {
        Schema::connection('centriumLog')->create('TravelClickMessageHistory', function (Blueprint $table) {
            $table->id('MessageHistoryID');

            // Message identification
            $table->string('MessageID', 50)->index();    // Links to TravelClickLogs
            $table->string('ParentMessageID', 50)->nullable(); // For response messages
            $table->string('BatchID', 50)->nullable();    // For batch operations

            // Message details
            $table->string('MessageType', 20);
            $table->string('Direction', 10);
            $table->integer('PropertyID');
            $table->string('HotelCode', 10)->nullable();

            // XML content (compressed for large messages)
            $table->longText('MessageXML');              // Full XML content
            $table->string('XmlHash', 64);               // SHA256 hash for deduplication
            $table->integer('MessageSize')->nullable();   // Size in bytes

            // Processing information
            $table->string('ProcessingStatus', 20);      // 'pending', 'processed', 'failed'
            $table->json('ExtractedData')->nullable();   // Key data extracted from XML
            $table->text('ProcessingNotes')->nullable(); // Any processing notes

            // Timing
            $table->dateTime('SentAt')->nullable();      // When sent to TravelClick
            $table->dateTime('ReceivedAt')->nullable();  // When received from TravelClick
            $table->dateTime('ProcessedAt')->nullable(); // When we processed it

            // Standard audit fields
            $table->integer('SystemUserID')->default(0);
            $table->dateTime('DateCreated')->default(now());

            // Indexes for efficient queries
            $table->index(['MessageType', 'Direction', 'DateCreated']);
            $table->index(['PropertyID', 'DateCreated']);
            $table->index('XmlHash');                    // For detecting duplicates
            $table->index('BatchID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('centriumLog')->dropIfExists('TravelClickMessageHistory');
    }
};
