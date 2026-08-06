<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestionOption extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveyQuestionOption';
    protected $primaryKey = 'OptionId';
    public $timestamps = false;

    protected $fillable = [
        'QuestionId',
        'OptionText',
        'DisplayOrder',
        'AllowsOtherText',
        'IsActive',
    ];

    protected $casts = [
        'AllowsOtherText' => 'boolean',
        'IsActive' => 'boolean',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'QuestionId', 'QuestionId');
    }
}
