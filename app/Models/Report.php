<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = ['user_id', 'scores', 'status', 'result', 'class_name'];
    protected $visible = ['id', 'status', 'result'];
    protected $casts = ['scores' => 'array', 'result' => 'array'];
}
