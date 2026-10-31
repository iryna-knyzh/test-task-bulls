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
        Schema::create('column_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tab_mapping_id')->constrained()->cascadeOnDelete();
            $table->string('source_column');
            $table->string('target_column', 64);
            $table->string('data_type', 20);
            $table->timestamps();

            $table->unique(['tab_mapping_id', 'target_column']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_mappings');
    }
};
