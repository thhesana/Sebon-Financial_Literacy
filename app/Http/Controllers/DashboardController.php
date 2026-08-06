<?php

namespace App\Http\Controllers;

use App\Models\CapitalMarketProgram;
use App\Support\SecureId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const ANALYTIC_CODES = [
        'Q2' => ['title' => 'Gender Distribution', 'chart' => 'doughnut'],
        'Q3' => ['title' => 'Age Group Profile', 'chart' => 'bar'],
        'Q6' => ['title' => 'Educational Qualification', 'chart' => 'bar'],
        'Q7' => ['title' => 'Education Stream', 'chart' => 'pie'],
        'Q8' => ['title' => 'Professional Status', 'chart' => 'bar'],
    ];

    public function index(Request $request)
    {
        $programs = CapitalMarketProgram::query()
            ->orderByDesc('CapitalMarketProgramID')
            ->get();

        // Drop pre-All sticky selection (old code auto-picked the first program)
        if (! $request->session()->get('dashboard_all_default_v2')) {
            $request->session()->forget('dashboard_program_id');
            $request->session()->put('dashboard_all_default_v2', true);
            $request->session()->put('dashboard_program_id', 0);
        }

        // 0 = All programs (default)
        $selectedId = (int) $request->session()->get('dashboard_program_id', 0);

        if ($selectedId > 0 && ! $programs->contains(fn ($p) => (int) $p->CapitalMarketProgramID === $selectedId)) {
            $selectedId = 0;
            $request->session()->put('dashboard_program_id', 0);
        }

        $selectedProgram = $selectedId > 0
            ? $programs->firstWhere('CapitalMarketProgramID', $selectedId)
            : null;

        $analytics = $this->buildAnalytics($selectedId > 0 ? $selectedId : null);

        return view('dashboard', [
            'programs' => $programs,
            'selectedProgram' => $selectedProgram,
            'selectedProgramId' => $selectedId,
            'analytics' => $analytics,
        ]);
    }

    public function selectProgram(Request $request)
    {
        $token = (string) $request->input('token', '');
        $request->session()->put('dashboard_all_default_v2', true);

        if ($token === '' || $token === 'all') {
            $request->session()->put('dashboard_program_id', 0);

            return redirect()->route('dashboard');
        }

        $programId = SecureId::decode($token);

        $exists = CapitalMarketProgram::where('CapitalMarketProgramID', $programId)->exists();
        if (! $exists) {
            abort(404, 'Program not found.');
        }

        $request->session()->put('dashboard_program_id', $programId);

        return redirect()->route('dashboard');
    }

    private function buildAnalytics(?int $programId): array
    {
        $totalParams = [];
        $totalWhere = ' WHERE 1 = 1 ';
        if ($programId !== null && $programId > 0) {
            $totalWhere .= ' AND a.CapitalMarketProgramID = ? ';
            $totalParams[] = $programId;
        }

        $totalRespondents = (int) DB::connection('sqlsrv')->selectOne('
            SELECT COUNT(DISTINCT a.RespondentId) AS c
            FROM SurveyAnswer a
            '.$totalWhere.'
        ', $totalParams)->c;

        $palette = ['#003366', '#0d6efd', '#198754', '#fd7e14', '#6f42c1', '#20c997', '#dc3545', '#6c757d', '#0dcaf0'];

        $charts = [];
        foreach (self::ANALYTIC_CODES as $code => $meta) {
            $where = ' WHERE UPPER(LTRIM(RTRIM(q.QuestionCode))) = ? ';
            $params = [strtoupper($code)];

            if ($programId !== null && $programId > 0) {
                $where .= ' AND a.CapitalMarketProgramID = ? ';
                $params[] = $programId;
            }

            $rows = DB::connection('sqlsrv')->select('
                SELECT
                    o.OptionText AS label,
                    MIN(o.DisplayOrder) AS sort_order,
                    COUNT(DISTINCT a.RespondentId) AS value
                FROM SurveyAnswer a
                INNER JOIN SurveyAnswerOption ao ON ao.AnswerId = a.AnswerId
                INNER JOIN SurveyQuestionOption o ON o.OptionId = ao.OptionId
                INNER JOIN SurveyQuestion q ON q.QuestionId = a.QuestionId
                '.$where.'
                GROUP BY o.OptionText
                ORDER BY MIN(o.DisplayOrder), o.OptionText
            ', $params);

            $labels = [];
            $values = [];
            $colors = [];
            foreach ($rows as $i => $row) {
                $labels[] = (string) $row->label;
                $values[] = (int) $row->value;
                $colors[] = $palette[$i % count($palette)];
            }

            $charts[$code] = [
                'code' => $code,
                'title' => $meta['title'],
                'type' => $meta['chart'],
                'labels' => $labels,
                'values' => $values,
                'colors' => $colors,
                'total' => array_sum($values),
            ];
        }

        return [
            'total_respondents' => $totalRespondents,
            'charts' => $charts,
        ];
    }
}
