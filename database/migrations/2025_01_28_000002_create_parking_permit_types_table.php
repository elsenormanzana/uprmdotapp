<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_permit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color'); // blue, white, yellow, orange, green, violet
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_permit_types');
    }
};
