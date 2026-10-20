<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $guarded = [];

    public static function log(string $subject, string $action): self
    {
        return static::create(['subject' => $subject, 'action' => $action]);
    }
}
