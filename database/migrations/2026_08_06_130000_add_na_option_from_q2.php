<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * From Q2 onward: choice questions get an "N/A" option if missing.
 * Q11+ optional (IsRequired = 0) is handled by the earlier migration / SurveySchema.
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        $questions = $conn->table('SurveyQuestion')
            ->where('IsActive', 1)
            ->orderBy('QuestionId')
            ->get(['QuestionId', 'QuestionCode', 'QuestionType']);

        foreach ($questions as $question) {
            if (! $this->isFromQ2Onward((string) $question->QuestionCode)) {
                continue;
            }

            if ($this->isTextOnly((string) $question->QuestionType)) {
                continue;
            }

            $hasNa = $conn->table('SurveyQuestionOption')
                ->where('QuestionId', $question->QuestionId)
                ->where('IsActive', 1)
                ->get(['OptionText'])
                ->contains(fn ($row) => $this->isNaText((string) $row->OptionText));

            if ($hasNa) {
                continue;
            }

            $maxOrder = (int) $conn->table('SurveyQuestionOption')
                ->where('QuestionId', $question->QuestionId)
                ->max('DisplayOrder');

            $conn->table('SurveyQuestionOption')->insert([
                'QuestionId' => $question->QuestionId,
                'OptionText' => 'N/A',
                'DisplayOrder' => $maxOrder + 1,
                'AllowsOtherText' => 0,
                'IsActive' => 1,
            ]);
        }
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        $questions = $conn->table('SurveyQuestion')
            ->where('IsActive', 1)
            ->orderBy('QuestionId')
            ->get(['QuestionId', 'QuestionCode', 'QuestionType']);

        foreach ($questions as $question) {
            if (! $this->isFromQ2Onward((string) $question->QuestionCode)) {
                continue;
            }

            if ($this->isFromQ11Onward((string) $question->QuestionCode)) {
                continue;
            }

            $conn->table('SurveyQuestionOption')
                ->where('QuestionId', $question->QuestionId)
                ->where('OptionText', 'N/A')
                ->where('IsActive', 1)
                ->update(['IsActive' => 0]);
        }
    }

    private function isFromQ2Onward(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '' || ! preg_match('/^Q?(\d+)/i', $code, $m)) {
            return false;
        }

        return (int) $m[1] >= 2;
    }

    private function isFromQ11Onward(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '' || ! preg_match('/^Q?(\d+)/i', $code, $m)) {
            return false;
        }

        return (int) $m[1] >= 11;
    }

    private function isTextOnly(string $type): bool
    {
        $type = strtolower(str_replace([' ', '-', '_'], '', $type));

        return in_array($type, ['text', 'textbox', 'shorttext', 'input', 'string', 'textarea', 'longtext', 'paragraph'], true);
    }

    private function isNaText(string $text): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($text)));

        return in_array($normalized, ['NA', 'NOTAPPLICABLE'], true);
    }
};
