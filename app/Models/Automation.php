<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    protected $guarded = [];

    protected $casts = ['enabled' => 'boolean', 'last_run_at' => 'datetime'];

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class)->latest('id');
    }
}
