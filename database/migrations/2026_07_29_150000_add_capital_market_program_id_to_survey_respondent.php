<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('sqlsrv')->hasColumn('SurveyRespondent', 'CapitalMarketProgramID')) {
            DB::connection('sqlsrv')->statement('
                ALTER TABLE dbo.SurveyRespondent
                ADD CapitalMarketProgramID INT NULL
            ');
        }
    }

    public function down(): void
    {
        if (Schema::connection('sqlsrv')->hasColumn('SurveyRespondent', 'CapitalMarketProgramID')) {
            DB::connection('sqlsrv')->statement('
                ALTER TABLE dbo.SurveyRespondent
                DROP COLUMN CapitalMarketProgramID
            ');
        }
    }
};
