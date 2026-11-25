<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends EloquentModel
{
    use HasFactory;

    protected $table = 'cities';

    protected $fillable = ['county_id', 'name'];

    public $timestamps = false;

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class, 'county_id', 'id');
    }

    public function postcodes(): HasMany
    {
        return $this->hasMany(Postcode::class, 'city_id', 'id')
            ->orderBy('postcode');
    }
}
