<?php

namespace App\Http\Controllers;

use App\Models\CapitalMarketProgram;
use App\Models\FiscalYearMaster;
use App\Models\SurveyQuestion;
use App\Models\SurveyRespondent;
use App\Models\SurveySection;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Question codes shown as summary result columns */
    private const KEY_CODES = ['Q2', 'Q3', 'Q3A', 'Q6', 'Q8'];

    public function index(Request $request)
    {
        $programs = CapitalMarketProgram::orderByDesc('CapitalMarketProgramID')->get();
        $fiscalYears = FiscalYearMaster::query()
            ->orderByDesc('fy_startdate')
            ->orderByDesc('fiscal_year_master_id')
            ->get();
        $filterSections = $this->loadFilterQuestions();

        $searched = (bool) $request->session()->get('report_searched', false);
        $filters = $request->session()->get('report_filters', [
            'fy_id' => null,
            'program_id' => null,
            'participant_name' => null,
            'answers' => [],
        ]);

        $results = collect();
        $summaryRows = collect();

        if ($searched) {
            $fakeRequest = Request::create('/reports/search', 'POST', $filters);
            $results = $this->searchRespondents($fakeRequest);
            $summaryRows = $this->buildSummaryRows($results);
        }

        return view('reports', [
            'programs' => $programs,
            'fiscalYears' => $fiscalYears,
            'filterSections' => $filterSections,
            'searched' => $searched,
            'results' => $results,
            'summaryRows' => $summaryRows,
            'filters' => $filters,
        ]);
    }

    public function search(Request $request)
    {
        $request->session()->put('report_searched', true);
        $request->session()->put('report_filters', [
            'fy_id' => $request->input('fy_id'),
            'program_id' => $request->input('program_id'),
            'participant_name' => $request->input('participant_name'),
            'answers' => $request->input('answers', []),
        ]);

        return redirect()->route('reports');
    }

    public function clear(Request $request)
    {
        $request->session()->forget(['report_searched', 'report_filters']);

        return redirect()->route('reports');
    }

    private function loadFilterQuestions(): Collection
    {
        $seenCodes = [];

        return SurveySection::with(['questions.options'])
            ->orderBy('SurveySectionId')
            ->get()
            ->map(function (SurveySection $section) use (&$seenCodes) {
                $questions = $section->questions
                    ->unique('QuestionId')
                    ->filter(function (SurveyQuestion $question) use (&$seenCodes) {
                        $code = strtoupper(trim((string) $question->QuestionCode));
                        if ($code !== '' && isset($seenCodes[$code])) {
                            return false;
                        }
                        if ($code !== '') {
                            $seenCodes[$code] = true;
                        }

                        // Only choice questions make meaningful dropdown filters.
                        return $question->isMulti() || $question->isSingle();
                    })
                    ->map(function (SurveyQuestion $question) {
                        $question->setRelation(
                            'options',
                            $question->options->unique(fn ($o) => strtolower(trim((string) $o->OptionText)))->values()
                        );

                        return $question;
                    })
                    ->values();

                $section->setRelation('questions', $questions);

                return $section;
            })
            ->filter(fn (SurveySection $section) => $section->questions->isNotEmpty())
            ->values();
    }

    private function searchRespondents(Request $request): Collection
    {
        $query = SurveyRespondent::query()
            ->orderByDesc('RespondentId');

        // FY filter: programs whose date ranges overlap the FY, or undated programs
        // with submissions inside the FY window.
        if ($request->filled('fy_id')) {
            $fy = FiscalYearMaster::find((int) $request->input('fy_id'));
            if (! $fy || ! $fy->fy_startdate || ! $fy->fy_enddate) {
                return collect();
            }

            $fyStart = $fy->fy_startdate->format('Y-m-d');
            $fyEnd = $fy->fy_enddate->format('Y-m-d');
            [$datedProgramIds, $undatedProgramIds] = $this->programIdsForFiscalYear($fyStart, $fyEnd);

            if ($datedProgramIds === [] && $undatedProgramIds === []) {
                return collect();
            }

            $query->where(function ($outer) use ($datedProgramIds, $undatedProgramIds, $fyStart, $fyEnd) {
                if ($datedProgramIds !== []) {
                    $outer->whereExists(function ($q) use ($datedProgramIds) {
                        $q->select(DB::raw('1'))
                            ->from('SurveyAnswer as a')
                            ->whereColumn('a.RespondentId', 'SurveyRespondent.RespondentId')
                            ->whereIn('a.CapitalMarketProgramID', $datedProgramIds);
                    });
                }

                if ($undatedProgramIds !== []) {
                    $outer->orWhere(function ($q) use ($undatedProgramIds, $fyStart, $fyEnd) {
                        $q->whereDate('SubmittedDate', '>=', $fyStart)
                            ->whereDate('SubmittedDate', '<=', $fyEnd)
                            ->whereExists(function ($sub) use ($undatedProgramIds) {
                                $sub->select(DB::raw('1'))
                                    ->from('SurveyAnswer as a')
                                    ->whereColumn('a.RespondentId', 'SurveyRespondent.RespondentId')
                                    ->whereIn('a.CapitalMarketProgramID', $undatedProgramIds);
                            });
                    });
                }
            });
        }

        // Program is stored on SurveyAnswer.CapitalMarketProgramID (not SurveyRespondent).
        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->whereExists(function ($q) use ($programId) {
                $q->select(DB::raw('1'))
                    ->from('SurveyAnswer as a')
                    ->whereColumn('a.RespondentId', 'SurveyRespondent.RespondentId')
                    ->where('a.CapitalMarketProgramID', $programId);
            });
        }

        if ($request->filled('participant_name')) {
            $name = trim((string) $request->input('participant_name'));
            $query->where('ParticipantName', 'like', '%'.$name.'%');
        }

        $answerFilters = $request->input('answers', []);
        if (is_array($answerFilters)) {
            foreach ($answerFilters as $questionId => $optionIds) {
                $optionIds = array_values(array_filter((array) $optionIds, fn ($v) => $v !== null && $v !== ''));
                if ($optionIds === []) {
                    continue;
                }

                $questionId = (int) $questionId;
                $optionIds = array_map('intval', $optionIds);

                $optionTexts = DB::connection('sqlsrv')
                    ->table('SurveyQuestionOption')
                    ->whereIn('OptionId', $optionIds)
                    ->pluck('OptionText')
                    ->map(fn ($t) => trim((string) $t))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $query->whereExists(function ($q) use ($questionId, $optionIds, $optionTexts) {
                    $q->select(DB::raw('1'))
                        ->from('SurveyAnswer as a')
                        ->join('SurveyAnswerOption as ao', 'ao.AnswerId', '=', 'a.AnswerId')
                        ->join('SurveyQuestionOption as o', 'o.OptionId', '=', 'ao.OptionId')
                        ->whereColumn('a.RespondentId', 'SurveyRespondent.RespondentId')
                        ->where(function ($inner) use ($questionId, $optionIds, $optionTexts) {
                            $inner->where(function ($q2) use ($questionId, $optionIds) {
                                $q2->where('a.QuestionId', $questionId)
                                    ->whereIn('ao.OptionId', $optionIds);
                            });

                            if ($optionTexts !== []) {
                                $inner->orWhere(function ($q3) use ($questionId, $optionTexts) {
                                    $q3->whereIn('o.OptionText', $optionTexts)
                                        ->whereIn('a.QuestionId', function ($sub) use ($questionId) {
                                            $sub->select('sq2.QuestionId')
                                                ->from('SurveyQuestion as sq1')
                                                ->join('SurveyQuestion as sq2', 'sq2.QuestionCode', '=', 'sq1.QuestionCode')
                                                ->where('sq1.QuestionId', $questionId);
                                        });
                                });
                            }
                        });
                });
            }
        }

        return $query->get();
    }

    private function buildSummaryRows(Collection $respondents): Collection
    {
        if ($respondents->isEmpty()) {
            return collect();
        }

        $programs = CapitalMarketProgram::query()
            ->get()
            ->keyBy('CapitalMarketProgramID');

        $fiscalYears = FiscalYearMaster::query()
            ->orderByDesc('fy_startdate')
            ->get();

        $respondentIds = $respondents->pluck('RespondentId')->all();

        // Resolve program from SurveyAnswer (source of truth).
        $programByRespondent = DB::connection('sqlsrv')
            ->table('SurveyAnswer')
            ->whereIn('RespondentId', $respondentIds)
            ->whereNotNull('CapitalMarketProgramID')
            ->select('RespondentId', 'CapitalMarketProgramID')
            ->distinct()
            ->get()
            ->groupBy('RespondentId')
            ->map(fn ($rows) => (int) $rows->first()->CapitalMarketProgramID);

        $keyQuestions = SurveyQuestion::with('options')
            ->where('IsActive', 1)
            ->get()
            ->filter(fn (SurveyQuestion $q) => in_array(strtoupper(trim((string) $q->QuestionCode)), self::KEY_CODES, true))
            ->unique(fn (SurveyQuestion $q) => strtoupper(trim((string) $q->QuestionCode)))
            ->keyBy(fn (SurveyQuestion $q) => strtoupper(trim((string) $q->QuestionCode)));

        $questionIds = SurveyQuestion::where('IsActive', 1)
            ->get()
            ->filter(fn (SurveyQuestion $q) => in_array(strtoupper(trim((string) $q->QuestionCode)), self::KEY_CODES, true))
            ->pluck('QuestionId')
            ->all();

        $answerRows = DB::connection('sqlsrv')
            ->table('SurveyAnswer as a')
            ->leftJoin('SurveyAnswerOption as ao', 'ao.AnswerId', '=', 'a.AnswerId')
            ->leftJoin('SurveyQuestionOption as o', 'o.OptionId', '=', 'ao.OptionId')
            ->leftJoin('SurveyQuestion as q', 'q.QuestionId', '=', 'a.QuestionId')
            ->whereIn('a.RespondentId', $respondentIds)
            ->whereIn('a.QuestionId', $questionIds ?: [0])
            ->select([
                'a.RespondentId',
                'q.QuestionCode',
                'a.AnswerText',
                'o.OptionText',
            ])
            ->get();

        $answersByRespondent = [];
        foreach ($answerRows as $row) {
            $code = strtoupper(trim((string) $row->QuestionCode));
            if ($code === '') {
                continue;
            }
            $rid = (int) $row->RespondentId;
            $value = trim((string) ($row->OptionText ?: $row->AnswerText));
            if ($value === '') {
                continue;
            }
            $answersByRespondent[$rid][$code][] = $value;
        }

        return $respondents->values()->map(function (SurveyRespondent $r, int $index) use ($programs, $fiscalYears, $answersByRespondent, $keyQuestions, $programByRespondent) {
            $rid = (int) $r->RespondentId;
            $programId = $programByRespondent->get($rid) ?: $r->CapitalMarketProgramID;
            $program = $programs->get($programId);
            $answers = $answersByRespondent[$rid] ?? [];

            $fyLabels = [];
            if ($program) {
                $fyLabels = $program->matchingFiscalYears($fiscalYears);
                // Undated programs: resolve FY from submitted date.
                if ($fyLabels === [] && $r->SubmittedDate) {
                    $submitted = $r->SubmittedDate->format('Y-m-d');
                    foreach ($fiscalYears as $fy) {
                        if (! $fy->fy_startdate || ! $fy->fy_enddate) {
                            continue;
                        }
                        $start = $fy->fy_startdate->format('Y-m-d');
                        $end = $fy->fy_enddate->format('Y-m-d');
                        if ($submitted >= $start && $submitted <= $end) {
                            $fyLabels[] = (string) $fy->fy;
                        }
                    }
                    $fyLabels = array_values(array_unique($fyLabels));
                }
            }

            $keyAnswers = [];
            foreach ($keyQuestions as $code => $question) {
                $vals = array_values(array_unique($answers[$code] ?? []));
                $keyAnswers[$code] = [
                    'label' => trim(($question->QuestionCode ? $question->QuestionCode.'. ' : '').$this->shortLabel($question->QuestionText)),
                    'value' => $vals ? implode(', ', $vals) : '—',
                ];
            }

            return [
                'sn' => $index + 1,
                'respondent_id' => $rid,
                'participant' => $r->ParticipantName ?: '—',
                'fy' => $fyLabels,
                'program' => $program->CapitalMarketProgramName ?? '—',
                'submitted' => $r->SubmittedDate ? $r->SubmittedDate->format('Y-m-d H:i') : '—',
                'status' => $r->IsComplete ? 'Complete' : 'Incomplete',
                'key_answers' => $keyAnswers,
            ];
        });
    }

    private function shortLabel(string $text): string
    {
        $text = trim($text);
        if (mb_strlen($text) <= 42) {
            return $text;
        }

        return mb_substr($text, 0, 40).'…';
    }

    /**
     * Split programs for an FY window:
     * - dated: CapitalMarketProgramDate ranges that overlap the FY
     * - undated: no program dates (matched later via SubmittedDate in FY)
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    private function programIdsForFiscalYear(string $fyStart, string $fyEnd): array
    {
        $dated = [];
        $undated = [];

        foreach (CapitalMarketProgram::query()->get() as $program) {
            $id = (int) $program->CapitalMarketProgramID;
            $ranges = $program->dateRanges();

            if ($ranges === []) {
                $undated[] = $id;
                continue;
            }

            foreach ($ranges as $range) {
                $start = $range['StartDate'] ?? '';
                $end = $range['EndDate'] ?? '';
                if ($start === '' || $end === '') {
                    continue;
                }
                // Overlap: programStart <= fyEnd AND programEnd >= fyStart
                if ($start <= $fyEnd && $end >= $fyStart) {
                    $dated[] = $id;
                    break;
                }
            }
        }

        return [array_values(array_unique($dated)), array_values(array_unique($undated))];
    }
}
