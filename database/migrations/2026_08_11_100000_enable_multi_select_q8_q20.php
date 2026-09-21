<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach (['8', 'Q8', '20', 'Q20'] as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update(['QuestionType' => 'MultiChoice', 'IsRequired' => 0]);
        }

        // Bust the SurveySchema cache so rules re-apply on next request.
        \Illuminate\Support\Facades\Cache::forget('schema.survey_question.rules_optional_multi_na_v6');
        \Illuminate\Support\Facades\Cache::forget('schema.survey_question.rules_optional_multi_na_v5');
        \Illuminate\Support\Facades\Cache::forget('schema.survey_question.rules_optional_multi_na_v4');
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach (['8', 'Q8'] as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update(['QuestionType' => 'SingleChoice', 'IsRequired' => 1]);
        }

        foreach (['20', 'Q20'] as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update(['QuestionType' => 'SingleChoice', 'IsRequired' => 0]);
        }
    }
};
