<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('travel_click_property_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('property_id')->unique();
            $table->json('config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraint (assuming you have a properties table)
            $table->foreign('property_id')->references('PropertyID')->on('Property');

            // Indexes for better performance
            $table->index(['property_id', 'is_active']);
            $table->index('last_sync_at');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_click_property_configs');
    }
};
