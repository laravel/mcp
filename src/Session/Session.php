<?php

namespace Laravel\Mcp\Session;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
    protected $table = 'mcp_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
