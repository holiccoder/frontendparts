<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibrarySyncRun extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'scanned',
        'upserted',
        'errors',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned' => 'integer',
            'upserted' => 'integer',
            'errors' => 'array',
        ];
    }
}
