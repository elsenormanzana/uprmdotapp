<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_permit_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('polygon'); // [[lat,lng], [lat,lng], ...]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permit_zones');
    }
};
