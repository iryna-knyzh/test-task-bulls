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
        Schema::create('sheet_tabs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spreadsheet_id')->constrained()->cascadeOnDelete();
            $table->string('tab_name');
            $table->unsignedInteger('header_row')->default(1);
            $table->timestamps();

            $table->unique(['spreadsheet_id', 'tab_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sheet_tabs');
    }
};
