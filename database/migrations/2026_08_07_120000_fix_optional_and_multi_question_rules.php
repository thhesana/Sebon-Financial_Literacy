<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align live SurveyQuestion rows with form rules:
 * - Q4, Q10 (and other known optional codes) are optional
 * - Q10/Q12 (+ other multi-select codes) stay checkbox so multiple answers work
 * - Q11+ remain optional
 */
return new class extends Migration
{
    public function up(): void
    {
        $conn = DB::connection('sqlsrv');

        $optionalCodes = ['1', 'Q1', '4', 'Q4', '5', 'Q5', '9a', '9A', '9b', '9B', '10', 'Q10'];
        foreach ($optionalCodes as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update(['IsRequired' => 0]);
        }

        // All Q11+ optional
        $questions = $conn->table('SurveyQuestion')
            ->where('IsActive', 1)
            ->get(['QuestionId', 'QuestionCode']);

        foreach ($questions as $question) {
            $code = strtoupper(trim((string) $question->QuestionCode));
            if ($code !== '' && preg_match('/^Q?(\d+)/i', $code, $m) && (int) $m[1] >= 11) {
                $conn->table('SurveyQuestion')
                    ->where('QuestionId', $question->QuestionId)
                    ->update(['IsRequired' => 0]);
            }
        }

        $multiCodes = [
            '10', 'Q10', '11', 'Q11', '12', 'Q12', '13', 'Q13',
            '14', 'Q14', '15', 'Q15', '16', 'Q16', '17', 'Q17',
            '26', 'Q26',
        ];
        foreach ($multiCodes as $code) {
            $conn->table('SurveyQuestion')
                ->where('IsActive', 1)
                ->whereRaw('UPPER(LTRIM(RTRIM(QuestionCode))) = ?', [strtoupper($code)])
                ->update([
                    'QuestionType' => 'MultiChoice',
                    'IsRequired' => 0,
                ]);
        }
    }

    public function down(): void
    {
        // Non-destructive; leave question flags as-is.
    }
};
