<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SurveySchema
{
    /**
     * Values allowed by dbo.CK_SurveyQuestion_Type on the live SQL Server DB.
     * Do NOT write 'checkbox' / 'radio' — those violate the CHECK constraint.
     */
    public const TYPE_MULTI = 'MultiChoice';

    public const TYPE_SINGLE = 'SingleChoice';

    public const TYPE_TEXT = 'Text';

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
     * Whether SurveyAnswer still carries CapitalMarketProgramID (legacy layout).
     */
    public static function answerHasProgramColumn(): bool
    {
        $cacheKey = 'schema.survey_answer.has_capital_market_program_id';

        if (Cache::has($cacheKey)) {
            return (bool) Cache::get($cacheKey);
        }

        try {
            $exists = Schema::connection('sqlsrv')->hasColumn('SurveyAnswer', 'CapitalMarketProgramID');
            Cache::forever($cacheKey, $exists);

            return $exists;
        } catch (\Throwable $e) {
            Log::warning('Could not probe SurveyAnswer.CapitalMarketProgramID: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Keep survey question rules in sync with the live form:
     * - Q2+ choice questions: ensure an N/A option exists
     * - Known optional codes (1, 4, 5, 9a, 9b, 10) + Q11+: IsRequired = 0
     * - Multi-select codes: QuestionType = MultiChoice (CHECK-constraint safe)
     */
    public static function ensureOptionalNaFromQ11(): void
    {
        $cacheKey = 'schema.survey_question.rules_optional_multi_na_v6';

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
                    try {
                        $conn->table('SurveyQuestion')
                            ->where('QuestionId', $question->QuestionId)
                            ->update(['QuestionType' => self::TYPE_MULTI]);
                        $question->QuestionType = self::TYPE_MULTI;
                    } catch (\Throwable $e) {
                        // Keep going — runtime flags in the controller still treat these as multi.
                        Log::warning(
                            'Could not set MultiChoice for QuestionId '.$question->QuestionId.': '.$e->getMessage()
                        );
                    }
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

            foreach ([
                '8', 'Q8', '10', 'Q10', '11', 'Q11', '12', 'Q12', '13', 'Q13',
                '14', 'Q14', '15', 'Q15', '16', 'Q16', '17', 'Q17',
                '20', 'Q20', '26', 'Q26',
            ] as $multiCode) {
                try {
                    $conn->table('SurveyQuestion')
                        ->where('IsActive', 1)
                        ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($multiCode)])
                        ->update(['QuestionType' => self::TYPE_MULTI, 'IsRequired' => 0]);
                } catch (\Throwable $e) {
                    Log::warning('Could not set MultiChoice for code '.$multiCode.': '.$e->getMessage());
                    $conn->table('SurveyQuestion')
                        ->where('IsActive', 1)
                        ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($multiCode)])
                        ->update(['IsRequired' => 0]);
                }
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
        Cache::forget('schema.survey_question.rules_optional_multi_na_v6');
        Cache::forget('schema.survey_question.rules_optional_multi_na_v5');
        Cache::forget('schema.survey_question.rules_optional_multi_na_v4');
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

        return in_array((int) $m[1], [8, 10, 11, 12, 13, 14, 15, 16, 17, 20, 26], true);
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
            || in_array($type, ['multiselect', 'multiplechoice', 'checkbox', 'checkboxes', 'multichoice'], true);
    }

    private static function isNaText(string $text): bool
    {
        $normalized = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', trim($text)));

        return in_array($normalized, ['NA', 'NOTAPPLICABLE'], true);
    }
}
