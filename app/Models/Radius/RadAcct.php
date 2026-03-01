<?php

namespace App\Models\Radius;

use Illuminate\Database\Eloquent\Model;

class RadAcct extends Model
{
    protected $connection = 'radius';
    protected $table = 'radacct';
    protected $primaryKey = 'radacctid';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'acctstarttime' => 'datetime',
            'acctupdatetime' => 'datetime',
            'acctstoptime' => 'datetime',
            'acctinputoctets' => 'integer',
            'acctoutputoctets' => 'integer',
            'acctsessiontime' => 'integer',
        ];
    }

    public function scopeForUser($query, string $username)
    {
        return $query->where('username', $username);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('acctstoptime');
    }
}
