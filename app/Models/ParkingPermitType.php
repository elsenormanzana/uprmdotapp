<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParkingPermitType extends Model
{
    use HasFactory;

    public const COLORS = ['blue', 'white', 'yellow', 'orange', 'green', 'violet'];

    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    public function permitZones(): BelongsToMany
    {
        return $this->belongsToMany(PermitZone::class, 'permit_zone_parking_permit_type')
            ->withTimestamps();
    }

    public function permitAssignments(): HasMany
    {
        return $this->hasMany(PermitAssignment::class);
    }
}
