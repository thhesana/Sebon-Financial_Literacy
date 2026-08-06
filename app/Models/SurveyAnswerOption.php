<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyAnswerOption extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveyAnswerOption';
    protected $primaryKey = 'AnswerOptionId';
    public $timestamps = false;

    protected $fillable = [
        'AnswerId',
        'OptionId',
        'OtherText',
    ];

    public function answer(): BelongsTo
    {
        return $this->belongsTo(SurveyAnswer::class, 'AnswerId', 'AnswerId');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestionOption::class, 'OptionId', 'OptionId');
    }
}
