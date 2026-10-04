<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ProDocument extends Model
{
    /** @var list<string> */
    protected $fillable = ['type', 'status', 'verified_at', 'expires_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['verified_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
