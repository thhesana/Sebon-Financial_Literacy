<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable multiple answers for Q14 and Q26 (checkbox / Mark all that apply).
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        $updates = [
            '14' => 'Which of the following financial instruments is riskier? (Mark all that apply)',
            'Q14' => 'Which of the following financial instruments is riskier? (Mark all that apply)',
            '26' => 'In your opinion, what is the biggest barrier to youth participation in the capital market? (Mark all that apply)',
            'Q26' => 'In your opinion, what is the biggest barrier to youth participation in the capital market? (Mark all that apply)',
        ];

        foreach ($updates as $code => $text) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update([
                    'QuestionType' => 'MultiChoice',
                    'IsRequired' => 0,
                    'QuestionText' => $text,
                ]);
        }
    }

    public function down(): void
    {
        $conn = DB::connection('sqlsrv');

        foreach (['14', 'Q14'] as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update([
                    'QuestionType' => 'SingleChoice',
                    'QuestionText' => 'Which of the following financial instruments is riskier?',
                ]);
        }

        foreach (['26', 'Q26'] as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update([
                    'QuestionType' => 'SingleChoice',
                    'QuestionText' => 'In your opinion, what is the biggest barrier to youth participation in the capital market? (Choose the most relevant option)',
                ]);
        }
    }
};
