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
}
