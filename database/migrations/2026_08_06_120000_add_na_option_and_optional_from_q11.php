<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * From Q11 onward:
 * - Questions may be left blank (IsRequired = 0)
 * - Choice questions get an "N/A" option if missing
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
            if (! $this->isFromQ11Onward((string) $question->QuestionCode)) {
                continue;
            }

            $conn->table('SurveyQuestion')
                ->where('QuestionId', $question->QuestionId)
                ->update(['IsRequired' => 0]);

            if ($this->isTextOnly((string) $question->QuestionType)) {
                continue;
            }

            $hasNa = $conn->table('SurveyQuestionOption')
                ->where('QuestionId', $question->QuestionId)
                ->where('IsActive', 1)
                ->get(['OptionText'])
                ->contains(function ($row) {
                    return $this->isNaText((string) $row->OptionText);
                });

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
            if (! $this->isFromQ11Onward((string) $question->QuestionCode)) {
                continue;
            }

            // Restore required for choice questions (text follow-ups like 19a stay optional).
            if (! $this->isTextOnly((string) $question->QuestionType)) {
                $conn->table('SurveyQuestion')
                    ->where('QuestionId', $question->QuestionId)
                    ->update(['IsRequired' => 1]);
            }

            $conn->table('SurveyQuestionOption')
                ->where('QuestionId', $question->QuestionId)
                ->where('OptionText', 'N/A')
                ->where('IsActive', 1)
                ->update(['IsActive' => 0]);
        }
    }

    private function isFromQ11Onward(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        // Accept "11", "Q11", "19A", "Q19a", etc.
        if (! preg_match('/^Q?(\d+)/i', $code, $m)) {
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
