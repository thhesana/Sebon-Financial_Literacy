<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SurveyQuestion extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SurveyQuestion';
    protected $primaryKey = 'QuestionId';
    public $timestamps = false;

    protected $fillable = [
        'SurveySectionId',
        'QuestionCode',
        'QuestionText',
        'QuestionType',
        'IsRequired',
        'DisplayOrder',
        'ParentQuestionId',
        'ParentTriggerOptionId',
        'IsActive',
        'CreatedDate',
    ];

    protected $casts = [
        'IsRequired' => 'boolean',
        'IsActive' => 'boolean',
        'CreatedDate' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(SurveySection::class, 'SurveySectionId', 'SurveySectionId');
    }

    public function options(): HasMany
    {
        return $this->hasMany(SurveyQuestionOption::class, 'QuestionId', 'QuestionId')
            ->where('IsActive', 1)
            ->orderBy('DisplayOrder');
    }

    public function isMulti(): bool
    {
        $type = $this->normalizedType();

        // DB types: MultiChoice (+ common aliases)
        return str_contains($type, 'multi')
            || str_contains($type, 'check')
            || in_array($type, ['multiselect', 'multiplechoice', 'checkbox', 'checkboxes'], true);
    }

    public function isSingle(): bool
    {
        if ($this->isMulti() || $this->isPlainText()) {
            return false;
        }

        $type = $this->normalizedType();

        // DB types: SingleChoice (+ common aliases)
        if (str_contains($type, 'radio')
            || str_contains($type, 'single')
            || in_array($type, ['select', 'dropdown', 'option', 'mcq', 'singlechoice'], true)) {
            return true;
        }

        return $this->relationLoaded('options')
            ? $this->options->isNotEmpty() && ! $this->isTextarea()
            : false;
    }

    public function isText(): bool
    {
        return $this->isPlainText() || (! $this->isMulti() && ! $this->isSingle() && ! $this->isTextarea());
    }

    public function isTextarea(): bool
    {
        $type = $this->normalizedType();

        return str_contains($type, 'textarea')
            || str_contains($type, 'longtext')
            || str_contains($type, 'paragraph');
    }

    private function isPlainText(): bool
    {
        $type = $this->normalizedType();

        return $type === 'text'
            || $type === 'textbox'
            || $type === 'shorttext'
            || $type === 'input'
            || $type === 'string';
    }

    private function normalizedType(): string
    {
        return strtolower(str_replace([' ', '-', '_'], '', (string) $this->QuestionType));
    }
}
