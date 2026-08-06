<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyAnswer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveyAnswer';
    protected $primaryKey = 'AnswerId';
    public $timestamps = false;

    protected $fillable = [
        'RespondentId',
        'QuestionId',
        'AnswerText',
        'CreatedDate',
    ];

    protected $casts = [
        'CreatedDate' => 'datetime',
    ];

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(SurveyRespondent::class, 'RespondentId', 'RespondentId');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(SurveyQuestion::class, 'QuestionId', 'QuestionId');
    }

    public function answerOptions(): HasMany
    {
        return $this->hasMany(SurveyAnswerOption::class, 'AnswerId', 'AnswerId');
    }
}
