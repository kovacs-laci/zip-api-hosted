<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * @property int|null $created_by
 * @property int|null $updated_by
 */
class County extends EloquentModel
{
    use HasFactory;

    protected $table = 'counties';

    protected $fillable = ['name'];


    public function cities()
    {
        return $this->hasMany(City::class, 'county_id', 'id');
    }

}
