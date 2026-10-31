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
        Schema::create('tab_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sheet_tab_id')->constrained()->cascadeOnDelete();
            $table->string('target_table', 64);
            $table->timestamps();

            $table->unique(['sheet_tab_id', 'target_table']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tab_mappings');
    }
};
