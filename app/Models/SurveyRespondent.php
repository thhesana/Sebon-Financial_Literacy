<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyRespondent extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveyRespondent';
    protected $primaryKey = 'RespondentId';
    public $timestamps = false;

    protected $fillable = [
        'ParticipantName',
        'SubmittedDate',
        'IPAddress',
        'IsComplete',
        'CapitalMarketProgramID',
    ];

    protected $casts = [
        'SubmittedDate' => 'datetime',
        'IsComplete' => 'boolean',
    ];

    public function answers(): HasMany
    {
        return $this->hasMany(SurveyAnswer::class, 'RespondentId', 'RespondentId');
    }
}
