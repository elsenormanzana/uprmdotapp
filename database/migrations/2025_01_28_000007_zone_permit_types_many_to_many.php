<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permit_zone_parking_permit_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_permit_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permit_zone_id', 'parking_permit_type_id']);
        });

        $rows = DB::table('permit_zones')->get(['id', 'parking_permit_type_id']);
        foreach ($rows as $row) {
            if ($row->parking_permit_type_id) {
                DB::table('permit_zone_parking_permit_type')->insert([
                    'permit_zone_id' => $row->id,
                    'parking_permit_type_id' => $row->parking_permit_type_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('permit_zones', function (Blueprint $table) {
            $table->dropForeign(['parking_permit_type_id']);
            $table->dropColumn('parking_permit_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('permit_zones', function (Blueprint $table) {
            $table->foreignId('parking_permit_type_id')->nullable()->after('name')->constrained()->cascadeOnDelete();
        });

        $firstPerZone = DB::table('permit_zone_parking_permit_type')
            ->orderBy('parking_permit_type_id')
            ->get()
            ->unique('permit_zone_id');
        foreach ($firstPerZone as $row) {
            DB::table('permit_zones')->where('id', $row->permit_zone_id)->update([
                'parking_permit_type_id' => $row->parking_permit_type_id,
            ]);
        }

        Schema::dropIfExists('permit_zone_parking_permit_type');
    }
};
