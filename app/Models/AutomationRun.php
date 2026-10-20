<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    protected $guarded = [];

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
