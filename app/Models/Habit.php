<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Habit extends Model
{
    protected $fillable = ['user_id','name','description','icon','frequency','is_active'];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function logs(){
        return $this->hasMany(HabitLog::class);
    }
    public function streak(){
        return $this->hasOne(Streak::class);
    }
}