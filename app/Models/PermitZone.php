<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PermitZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'polygon',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'polygon' => 'array',
        ];
    }

    public function parkingPermitTypes(): BelongsToMany
    {
        return $this->belongsToMany(ParkingPermitType::class, 'permit_zone_parking_permit_type')
            ->withTimestamps();
    }
}
