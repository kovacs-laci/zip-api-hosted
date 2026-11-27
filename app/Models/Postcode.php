<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class Postcode extends EloquentModel
{
    use HasFactory;

    protected $table = 'postcodes';

    protected $fillable = ['city_id', 'postcode'];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id', 'id');
    }
}
