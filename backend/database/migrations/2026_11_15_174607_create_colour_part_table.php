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
        Schema::create('colour_part', function (Blueprint $table) {
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->foreignId('colour_id')->constrained();

            $table->primary(['part_id', 'colour_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('colour_part');
    }
};
