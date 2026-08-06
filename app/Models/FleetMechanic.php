<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetMechanic extends Model
{
    protected $fillable = [
        'name',
        'specialty',
        'email',
        'phone',
    ];

    public function initial(): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
    }
}
