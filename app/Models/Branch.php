<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $guarded = [];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function technicians(): HasMany
    {
        return $this->hasMany(Technician::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
