<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UidUsage extends Model
{
    use HasFactory;
    public $table = 'uid_usage';

    public $fillable = [
        'uid',
        'usage_type',
        'credits_count',
        'credits_usage',
        'is_iran',
        'is_webservice',
        'weekly_usage',
        'is_uni',
        'each_user_limit'
    ];
    public $weekly_free = [
        'iran' => 5,
        'other' => 10
    ];

    public function remainFreeCredit()
    {
        $value = auth()->user()->is_iran ? $this->weekly_free['iran'] - auth()->user()->weekly_usage : $this->weekly_free['other'] - auth()->user()->weekly_usage;
        return $value < 0 ? 0 : $value;
    }

    public function remainPurchedCredit()
    {
        return ($this->is_uni ? $this->each_user_limit : $this->credits_count) - auth()->user()->credits_usage;
    }
    
    public function getTypeAttribute()
    {
        return $this->usage_type;
    }

    public function setTypeAttribute($value)
    {
        $this->usage_type = $value;
    }
}
