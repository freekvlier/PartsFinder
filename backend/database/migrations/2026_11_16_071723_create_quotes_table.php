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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workshop_id')->constrained()->cascadeOnDelete();
            $table->string('status')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->nullable();
            $table->unsignedBigInteger('discount_cents')->nullable();
            $table->unsignedBigInteger('vat_cents')->nullable();
            $table->unsignedBigInteger('total_cents')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
