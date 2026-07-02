<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Score extends Model
{
    
    protected $fillable = [
        'player',
        'score',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

}
