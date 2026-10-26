<?php

namespace FreshCart\Operations\Models;

use FreshCart\Operations\Contracts\Sequence as SequenceContract;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model implements SequenceContract
{
    protected $table = 'fc_sequences';

    protected $fillable = [
        'key',
        'period',
        'next_value',
    ];
}
