<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveySection extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveySection';
    protected $primaryKey = 'SurveySectionId';
    public $timestamps = false;

    protected $fillable = [
        'SurveySectionName',
        'SurveySectionCreated',
    ];

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class, 'SurveySectionId', 'SurveySectionId')
            ->where('IsActive', 1)
            ->orderBy('DisplayOrder');
    }
}
