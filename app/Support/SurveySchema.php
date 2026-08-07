<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SurveySchema
{
    /**
     * Ensure SurveyRespondent.CapitalMarketProgramID exists (SP + reports depend on it).
     */
    public static function ensureRespondentProgramColumn(): void
    {
        $cacheKey = 'schema.survey_respondent.capital_market_program_id';

        if (Cache::get($cacheKey)) {
            return;
        }

        try {
            if (! Schema::connection('sqlsrv')->hasColumn('SurveyRespondent', 'CapitalMarketProgramID')) {
                DB::connection('sqlsrv')->statement('
                    ALTER TABLE dbo.SurveyRespondent
                    ADD CapitalMarketProgramID INT NULL
                ');
            }

            Cache::forever($cacheKey, true);
        } catch (\Throwable $e) {
            Log::warning('Could not ensure CapitalMarketProgramID column: '.$e->getMessage());
        }
    }

    /**
     * Keep survey question rules in sync with the live form:
     * - Q2+ choice questions: ensure an N/A option exists
     * - Known optional codes (1, 4, 5, 9a, 9b, 10) + Q11+: IsRequired = 0
     * - Multi-select codes (10, 11, 12, 13, 15, 16, 17): QuestionType = checkbox
     */
    public static function ensureOptionalNaFromQ11(): void
    {
        $cacheKey = 'schema.survey_question.rules_optional_multi_na_v3';

        if (Cache::get($cacheKey)) {
            return;
        }

        try {
            $conn = DB::connection('sqlsrv');
            $questions = $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->orderBy('QuestionId')
                ->get(['QuestionId', 'QuestionCode', 'QuestionType']);

            foreach ($questions as $question) {
                $code = (string) $question->QuestionCode;

                if (self::isOptionalQuestion($code)) {
                    $conn->table('SurveyQuestion')
                        ->where('QuestionId', $question->QuestionId)
                        ->update(['IsRequired' => 0]);
                }

                if (self::isMultiSelectQuestion($code)
                    && ! self::isCheckboxType((string) $question->QuestionType)) {
                    $conn->table('SurveyQuestion')
                        ->where('QuestionId', $question->QuestionId)
                        ->update(['QuestionType' => 'checkbox']);
                    $question->QuestionType = 'checkbox';
                }

                if (! self::isFromQ2Onward($code) || self::isTextOnly((string) $question->QuestionType)) {
                    continue;
                }

                $hasNa = $conn->table('SurveyQuestionOption')
                    ->where('QuestionId', $question->QuestionId)
                    ->where('IsActive', 1)
                    ->get(['OptionText'])
                    ->contains(fn ($row) => self::isNaText((string) $row->OptionText));

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

            // Explicit safety for known optional text/multi codes even if regex misses letters.
            foreach (['1', 'Q1', '4', 'Q4', '5', 'Q5', '9a', '9A', '9b', '9B', '10', 'Q10'] as $optionalCode) {
                $conn->table('SurveyQuestion')
                    ->where('IsActive', 1)
                    ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($optionalCode)])
                    ->update(['IsRequired' => 0]);
            }

            foreach (['10', 'Q10', '11', 'Q11', '12', 'Q12', '13', 'Q13', '15', 'Q15', '16', 'Q16', '17', 'Q17'] as $multiCode) {
                $conn->table('SurveyQuestion')
                    ->where('IsActive', 1)
                    ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($multiCode)])
                    ->update(['QuestionType' => 'checkbox', 'IsRequired' => 0]);
            }

            Cache::forever($cacheKey, true);
        } catch (\Throwable $e) {
            Log::warning('Could not ensure survey question rules: '.$e->getMessage());
        }
    }

    /**
     * Force re-apply rules (used after deploys when cache may still hold old flag).
     */
    public static function refreshQuestionRules(): void
    {
        Cache::forget('schema.survey_question.rules_optional_multi_na_v3');
        Cache::forget('schema.survey_question.na_from_q2_optional_from_q11_v2');
        Cache::forget('schema.survey_question.na_from_q2_optional_from_q11');
        self::ensureOptionalNaFromQ11();
    }

    private static function isOptionalQuestion(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        // Explicit optional demographic / awareness questions
        if (in_array($code, ['1', 'Q1', '4', 'Q4', '5', 'Q5', '9A', '9B', '10', 'Q10'], true)) {
            return true;
        }

        if (! preg_match('/^Q?(\d+)/i', $code, $m)) {
            return false;
        }

        return (int) $m[1] >= 11;
    }

    private static function isMultiSelectQuestion(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        if (! preg_match('/^Q?(\d+)([A-Z]?)$/i', $code, $m)) {
            return false;
        }

        // Letter suffixes (9a, 19a) are text follow-ups, not multi-select.
        if (($m[2] ?? '') !== '') {
            return false;
        }

        return in_array((int) $m[1], [10, 11, 12, 13, 15, 16, 17], true);
    }

    private static function isFromQ2Onward(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '' || ! preg_match('/^Q?(\d+)/i', $code, $m)) {
            return false;
        }

        return (int) $m[1] >= 2;
    }

    private static function isTextOnly(string $type): bool
    {
        $type = strtolower(str_replace([' ', '-', '_'], '', $type));

        return in_array($type, ['text', 'textbox', 'shorttext', 'input', 'string', 'textarea', 'longtext', 'paragraph'], true);
    }

    private static function isCheckboxType(string $type): bool
    {
        $type = strtolower(str_replace([' ', '-', '_'], '', $type));

        return str_contains($type, 'check')
            || str_contains($type, 'multi')
            || in_array($type, ['multiselect', 'multiplechoice', 'checkbox', 'checkboxes'], true);
    }

    private static function isNaText(string $text): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($text)));

        return in_array($normalized, ['NA', 'NOTAPPLICABLE'], true);
    }
}
