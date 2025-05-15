<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table provides detailed error tracking and analysis.
     * It's like having a specialized incident report system for every
     * problem that occurs during TravelClick integration.
     */
    public function up(): void
    {
        Schema::connection('centriumLog')->create('TravelClickErrorLogs', function (Blueprint $table) {
            $table->id('ErrorLogID');

            // Link to main log entry
            $table->string('MessageID', 50)->index();    // Links to TravelClickLogs
            $table->string('JobID', 50)->nullable();     // Horizon job that failed

            // Error classification
            $table->string('ErrorType', 30);             // Using ErrorType enum
            $table->string('ErrorCode', 20)->nullable(); // Specific error code
            $table->string('Severity', 10);              // 'critical', 'high', 'medium', 'low'

            // Error details
            $table->string('ErrorTitle', 200);           // Brief error description
            $table->text('ErrorMessage');                // Full error message
            $table->text('StackTrace')->nullable();      // Full stack trace
            $table->json('Context')->nullable();         // Additional context data

            // Source information
            $table->string('SourceClass', 100)->nullable();   // Which class threw the error
            $table->string('SourceMethod', 100)->nullable();  // Which method
            $table->integer('SourceLine')->nullable();        // Line number

            // Recovery information
            $table->boolean('CanRetry')->default(false);      // Whether this error can be retried
            $table->integer('RecommendedRetryDelay')->nullable(); // Delay in seconds
            $table->text('RecoveryNotes')->nullable();         // How to fix this error
            $table->boolean('RequiresManualIntervention')->default(false);

            // Resolution tracking
            $table->dateTime('ResolvedAt')->nullable();      // When error was resolved
            $table->integer('ResolvedByUserID')->nullable(); // Who resolved it
            $table->text('ResolutionNotes')->nullable();     // How it was resolved

            // Standard audit fields
            $table->integer('PropertyID');
            $table->integer('SystemUserID')->default(0);
            $table->dateTime('DateCreated')->default(now());

            // Indexes for error analysis
            $table->index(['ErrorType', 'Severity', 'DateCreated']);
            $table->index(['PropertyID', 'DateCreated']);
            $table->index(['CanRetry', 'RequiresManualIntervention']);
            $table->index('ResolvedAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('centriumLog')->dropIfExists('TravelClickErrorLogs');
    }
};
