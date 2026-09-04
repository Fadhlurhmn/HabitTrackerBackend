<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HabitLog extends Model
{
    protected $fillable = ['habit_id','user_id','log_date','completed','points_earned'];

    protected $casts = [
        'log_date' => 'date',
        'completed' => 'boolean'
    ];

    public function habit(){
        return $this->belongsTo(Habit::class);
    }
    public function user(){
        return $this->belongsTo(User::class);
    }
}