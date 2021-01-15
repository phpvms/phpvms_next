<?php

namespace App\Models;

use App\Contracts\Model;

/**
 * @property int     code
 * @property string  name
 * @property float   cost
 * @property float   price
 * @property int     capacity
 * @property int     count
 */
class PirepFare extends Model
{
    public $table = 'pirep_fares';
    public $timestamps = false;

    protected $fillable = [
        'pirep_id',
        'code',
        'name',
        'count',
        'price',
        'cost',
        'capacity',
    ];

    protected $casts = [
        'count'    => 'integer',
        'price'    => 'float',
        'cost'     => 'float',
        'capacity' => 'integer',
    ];

    public static $rules = [
        'count' => 'required',
    ];

    public function pirep()
    {
        return $this->belongsTo(Pirep::class, 'pirep_id');
    }
}
