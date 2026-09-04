<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Streak extends Model
{
    protected $fillable = ['habit_id','current_streak','longest_streak','last_completed_date'];

    protected $casts = [
        'last_completed_date' => 'date'
    ];

    public function habit(){
        return $this->belongsTo(Habit::class);
    }
}
