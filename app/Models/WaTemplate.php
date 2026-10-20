<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaTemplate extends Model
{
    protected $guarded = [];

    /** @return list<string> the {{placeholders}} used in the body */
    public function variables(): array
    {
        preg_match_all('/{{[^}]+}}/', $this->body, $m);

        return $m[0];
    }
}
