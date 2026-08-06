<?php

namespace App\Http\Controllers;

use App\Models\CapitalMarketProgram;
use App\Models\SurveyAnswer;
use App\Models\SurveyAnswerOption;
use App\Models\SurveyQuestion;
use App\Models\SurveyRespondent;
use App\Models\SurveySection;
use App\Support\SecureId;
use App\Support\SurveySchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class QuestionnaireController extends Controller
{
    public function index()
    {
        $respondents = SurveyRespondent::orderByDesc('RespondentId')->get();

        return view('questionnaire.index', compact('respondents'));
    }

    public function create()
    {
        return view('questionnaire.form', [
            'sections' => $this->loadFormStructure(),
            'programs' => $this->loadPrograms(),
            'respondent' => null,
            'answers' => [],
            'mode' => 'create',
        ]);
    }

    public function store(Request $request)
    {
        $this->validateAnswers($request);

        $this->insertViaStoredProcedure(
            $this->resolveParticipantName($request),
            $this->buildAnswersJson($request),
            (int) $request->input('CapitalMarketProgramID')
        );

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey submitted successfully.');
    }

    public function edit(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::with(['answers.answerOptions'])->findOrFail($id);

        return view('questionnaire.form', [
            'sections' => $this->loadFormStructure(),
            'programs' => $this->loadPrograms(),
            'respondent' => $respondent,
            'answers' => $this->mapExistingAnswers($respondent),
            'mode' => 'edit',
            'secureToken' => SecureId::encode($id),
        ]);
    }

    public function show(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::with(['answers.answerOptions.option'])->findOrFail($id);
        $sections = $this->loadFormStructure();
        $answers = $this->mapAnswersForDocument($respondent);
        $program = $this->resolveProgramForRespondent($respondent);

        return view('questionnaire.show', compact('respondent', 'sections', 'answers', 'program'));
    }

    public function update(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::findOrFail($id);
        $this->validateAnswers($request);

        // Insert via SP first (it runs its own transaction), then remove the old response.
        $this->insertViaStoredProcedure(
            $this->resolveParticipantName($request),
            $this->buildAnswersJson($request),
            (int) $request->input('CapitalMarketProgramID')
        );

        DB::connection('sqlsrv')->transaction(function () use ($respondent) {
            $this->deleteAnswers($respondent);
            $respondent->delete();
        });

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey updated successfully.');
    }

    public function destroy(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::findOrFail($id);

        DB::connection('sqlsrv')->transaction(function () use ($respondent) {
            $this->deleteAnswers($respondent);
            $respondent->delete();
        });

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey deleted successfully.');
    }

    /**
     * Call dbo.sp_InsertSurveyResponseFromJson and return the new RespondentId.
     */
    private function insertViaStoredProcedure(?string $participantName, string $answersJson, int $programId): int
    {
        SurveySchema::ensureRespondentProgramColumn();

        $rows = DB::connection('sqlsrv')->select(
            'EXEC dbo.sp_InsertSurveyResponseFromJson @ParticipantName = ?, @AnswersJson = ?, @CapitalMarketProgramID = ?',
            [$participantName, $answersJson, $programId]
        );

        $respondentId = (int) ($rows[0]->RespondentId ?? 0);

        if ($respondentId <= 0) {
            throw new RuntimeException('Survey stored procedure did not return a RespondentId.');
        }

        return $respondentId;
    }

    private function loadPrograms()
    {
        return CapitalMarketProgram::query()
            ->orderByDesc('CapitalMarketProgramID')
            ->get()
            ->filter(function (CapitalMarketProgram $program) {
                $status = strtoupper(trim((string) $program->Status));

                // Include active programs; if Status is blank, still show it.
                return $status === ''
                    || in_array($status, ['1', 'Y', 'YES', 'ACTIVE', 'TRUE'], true)
                    || $program->Status === 1
                    || $program->Status === true;
            })
            ->values();
    }

    /**
     * Resolve program for a respondent from SurveyAnswer.CapitalMarketProgramID,
     * falling back to the current active program.
     */
    private function resolveProgramForRespondent(?SurveyRespondent $respondent = null): ?CapitalMarketProgram
    {
        if ($respondent) {
            $programId = DB::connection('sqlsrv')
                ->table('SurveyAnswer')
                ->where('RespondentId', $respondent->RespondentId)
                ->whereNotNull('CapitalMarketProgramID')
                ->value('CapitalMarketProgramID');

            if ($programId) {
                $program = CapitalMarketProgram::find($programId);
                if ($program) {
                    return $program;
                }
            }
        }

        return $this->loadPrograms()->first();
    }

    /**
     * Build the JSON payload expected by sp_InsertSurveyResponseFromJson:
     * [
     *   { "QuestionCode": "Q1", "AnswerText": "..." },
     *   { "QuestionCode": "Q2", "Options": ["Male"] },
     *   { "QuestionCode": "Q7", "Options": ["Others"], "OtherText": "Law" }
     * ]
     *
     * Important: one entry per QuestionCode (DB may contain duplicate Q11/Q19 rows).
     */
    private function buildAnswersJson(Request $request): string
    {
        $posted = $request->input('answers', []);
        $otherTexts = $request->input('other_text', []);

        $allQuestions = SurveyQuestion::with('options')
            ->where('IsActive', 1)
            ->orderBy('DisplayOrder')
            ->orderBy('QuestionId')
            ->get()
            ->keyBy('QuestionId');

        $payloadByCode = [];

        foreach ($posted as $questionId => $value) {
            $question = $allQuestions->get((int) $questionId);
            if (! $question || ! $question->QuestionCode) {
                continue;
            }

            $code = trim((string) $question->QuestionCode);
            $codeKey = strtoupper($code);

            // Keep the first answer per QuestionCode only.
            if ($codeKey === '' || isset($payloadByCode[$codeKey])) {
                continue;
            }

            $item = ['QuestionCode' => $code];

            if ($question->isMulti() || $question->isSingle()) {
                $optionIds = array_values(array_filter((array) $value, fn ($v) => $v !== null && $v !== ''));
                if ($optionIds === []) {
                    continue;
                }

                $optionTexts = [];
                $otherText = null;

                foreach ($optionIds as $optionId) {
                    $option = $question->options->firstWhere('OptionId', (int) $optionId);

                    if (! $option) {
                        $option = \App\Models\SurveyQuestionOption::query()
                            ->where('OptionId', (int) $optionId)
                            ->first();
                    }

                    if (! $option) {
                        continue;
                    }

                    $text = trim((string) $option->OptionText);
                    if ($text === '' || in_array($text, $optionTexts, true)) {
                        continue;
                    }

                    $optionTexts[] = $text;

                    if ($option->AllowsOtherText) {
                        $candidate = trim((string) ($otherTexts[$question->QuestionId][$option->OptionId] ?? ''));
                        if ($candidate !== '') {
                            $otherText = $candidate;
                        }
                    }
                }

                if ($optionTexts === []) {
                    continue;
                }

                $item['Options'] = $optionTexts;
                if ($otherText !== null) {
                    $item['OtherText'] = $otherText;
                }
            } else {
                $text = is_array($value) ? '' : trim((string) $value);
                if ($text === '') {
                    continue;
                }
                $item['AnswerText'] = $text;
            }

            $payloadByCode[$codeKey] = $item;
        }

        return json_encode(array_values($payloadByCode), JSON_UNESCAPED_UNICODE);
    }

    private function loadFormStructure()
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

                        // Drop duplicate codes (e.g. Q11 appearing twice in DB/seed).
                        if ($code !== '' && isset($seenCodes[$code])) {
                            return false;
                        }

                        if ($code !== '') {
                            $seenCodes[$code] = true;
                        }

                        return true;
                    })
                    ->map(function (SurveyQuestion $question) {
                        $question->setRelation(
                            'options',
                            $question->options->unique(function ($option) {
                                return strtolower(trim((string) $option->OptionText));
                            })->values()
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

    /**
     * Active questions used for form rendering + validation (deduped by QuestionCode).
     */
    private function activeQuestions()
    {
        $seenCodes = [];

        return SurveyQuestion::with('options')
            ->where('IsActive', 1)
            ->orderBy('DisplayOrder')
            ->orderBy('QuestionId')
            ->get()
            ->filter(function (SurveyQuestion $question) use (&$seenCodes) {
                $code = strtoupper(trim((string) $question->QuestionCode));
                if ($code !== '' && isset($seenCodes[$code])) {
                    return false;
                }
                if ($code !== '') {
                    $seenCodes[$code] = true;
                }

                return true;
            })
            ->values();
    }

    private function validateAnswers(Request $request): void
    {
        $questions = $this->activeQuestions();
        $posted = $request->input('answers', []);

        $rules = [
            'CapitalMarketProgramID' => 'required|integer',
        ];
        $messages = [
            'CapitalMarketProgramID.required' => 'Please select a Capital Market Program.',
        ];
        $attributes = [
            'CapitalMarketProgramID' => 'Capital Market Program',
        ];
        $firstMissingId = null;

        foreach ($questions as $question) {
            if (! $question->IsRequired) {
                continue;
            }

            if ($question->ParentQuestionId && $question->ParentTriggerOptionId) {
                $parentValue = $posted[$question->ParentQuestionId] ?? null;
                $selected = array_map('strval', (array) $parentValue);
                if (! in_array((string) $question->ParentTriggerOptionId, $selected, true)) {
                    continue;
                }
            }

            $key = "answers.{$question->QuestionId}";
            $label = trim(($question->QuestionCode ? $question->QuestionCode.'. ' : '').$question->QuestionText);

            $attributes[$key] = $label;
            $messages["{$key}.required"] = "Please answer: {$label}";
            $messages["{$key}.min"] = "Please answer: {$label}";

            if ($question->isMulti()) {
                $rules[$key] = 'required|array|min:1';
            } elseif ($question->isSingle()) {
                $rules[$key] = 'required';
            } else {
                $rules[$key] = 'required|string';
            }

            // Track first incomplete for jump target (before validate fails).
            $value = $posted[$question->QuestionId] ?? null;
            $missing = false;
            if ($question->isMulti()) {
                $missing = ! is_array($value) || count(array_filter($value, fn ($v) => $v !== null && $v !== '')) === 0;
            } elseif ($question->isSingle()) {
                $missing = $value === null || $value === '';
            } else {
                $missing = ! is_string($value) || trim($value) === '';
            }
            if ($missing && $firstMissingId === null) {
                $firstMissingId = $question->QuestionId;
            }
        }

        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            $jumpId = $firstMissingId;
            foreach (array_keys($validator->errors()->toArray()) as $errorKey) {
                if (preg_match('/^answers\.(\d+)/', $errorKey, $m)) {
                    $jumpId = (int) $m[1];
                    break;
                }
            }

            $redirectUrl = url()->previous();
            if ($jumpId) {
                $redirectUrl .= '#question-'.$jumpId;
            }

            throw (new ValidationException($validator))->redirectTo($redirectUrl);
        }
    }

    private function resolveParticipantName(Request $request): ?string
    {
        $nameFromRequest = trim((string) $request->input('participant_name', ''));
        if ($nameFromRequest !== '') {
            return $nameFromRequest;
        }

        $nameQuestion = SurveyQuestion::where('IsActive', 1)
            ->where(function ($q) {
                $q->whereIn('QuestionCode', ['Q1', '1'])
                    ->orWhere('QuestionCode', 'like', 'Q1%')
                    ->orWhere('QuestionText', 'like', '%Name of the participant%');
            })
            ->orderBy('DisplayOrder')
            ->first();

        if ($nameQuestion) {
            $value = $request->input("answers.{$nameQuestion->QuestionId}");
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function deleteAnswers(SurveyRespondent $respondent): void
    {
        $answerIds = SurveyAnswer::where('RespondentId', $respondent->RespondentId)
            ->pluck('AnswerId');

        if ($answerIds->isNotEmpty()) {
            SurveyAnswerOption::whereIn('AnswerId', $answerIds)->delete();
            SurveyAnswer::whereIn('AnswerId', $answerIds)->delete();
        }
    }

    private function mapExistingAnswers(SurveyRespondent $respondent): array
    {
        $mapped = [];

        foreach ($respondent->answers as $answer) {
            $qid = $answer->QuestionId;
            $optionIds = $answer->answerOptions->pluck('OptionId')->all();
            $others = [];

            foreach ($answer->answerOptions as $ao) {
                if ($ao->OtherText !== null && $ao->OtherText !== '') {
                    $others[$ao->OptionId] = $ao->OtherText;
                }
            }

            if (count($optionIds) > 1) {
                $mapped[$qid] = [
                    'type' => 'multi',
                    'values' => $optionIds,
                    'other' => $others,
                ];
            } elseif (count($optionIds) === 1) {
                $mapped[$qid] = [
                    'type' => 'single',
                    'value' => $optionIds[0],
                    'other' => $others,
                ];
            } else {
                $mapped[$qid] = [
                    'type' => 'text',
                    'value' => $answer->AnswerText,
                ];
            }
        }

        return $mapped;
    }

    /**
     * Map answers by QuestionCode for document-style viewing
     * (handles duplicate QuestionId rows that share the same code).
     */
    private function mapAnswersForDocument(SurveyRespondent $respondent): array
    {
        $questionsById = SurveyQuestion::query()->get()->keyBy('QuestionId');
        $mapped = [];

        foreach ($respondent->answers as $answer) {
            $question = $questionsById->get($answer->QuestionId);
            $code = strtoupper(trim((string) ($question->QuestionCode ?? '')));
            if ($code === '') {
                continue;
            }

            $optionIds = [];
            $optionTexts = [];
            $others = [];
            $otherFlat = null;

            foreach ($answer->answerOptions as $ao) {
                $optionIds[] = (int) $ao->OptionId;
                $label = trim((string) ($ao->option->OptionText ?? ''));
                if ($label !== '') {
                    $optionTexts[] = $label;
                }
                if ($ao->OtherText !== null && trim((string) $ao->OtherText) !== '') {
                    $others[(int) $ao->OptionId] = $ao->OtherText;
                    $otherFlat = $ao->OtherText;
                    if ($label !== '') {
                        $others[strtolower($label)] = $ao->OtherText;
                    }
                }
            }

            $entry = [
                'type' => count($optionIds) ? (count($optionIds) > 1 ? 'multi' : 'single') : 'text',
                'values' => $optionIds,
                'texts' => $optionTexts,
                'other' => $others,
                'other_text' => $otherFlat,
                'value' => $answer->AnswerText,
            ];

            // First wins; merge texts if duplicate code rows exist.
            if (! isset($mapped[$code])) {
                $mapped[$code] = $entry;
            } else {
                $mapped[$code]['values'] = array_values(array_unique(array_merge($mapped[$code]['values'], $optionIds)));
                $mapped[$code]['texts'] = array_values(array_unique(array_merge($mapped[$code]['texts'], $optionTexts)));
                $mapped[$code]['other'] = $mapped[$code]['other'] + $others;
                if (($mapped[$code]['value'] ?? null) === null && $answer->AnswerText) {
                    $mapped[$code]['value'] = $answer->AnswerText;
                }
                if (! $mapped[$code]['other_text'] && $otherFlat) {
                    $mapped[$code]['other_text'] = $otherFlat;
                }
            }
        }

        return $mapped;
    }
}
