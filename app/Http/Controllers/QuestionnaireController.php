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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class QuestionnaireController extends Controller
{
    public function index()
    {
        $respondents = SurveyRespondent::orderByDesc('RespondentId')->get();

        return view('questionnaire.index', compact('respondents'));
    }

    public function create()
    {
        return $this->renderForm('create');
    }

    public function store(Request $request)
    {
        $this->validateAnswers($request, route('questionnaire.create'));

        try {
            $this->insertViaStoredProcedure(
                $this->resolveParticipantName($request),
                $this->buildAnswersJson($request),
                (int) $request->input('CapitalMarketProgramID')
            );
        } catch (Throwable $e) {
            Log::error('Survey store failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect()
                ->route('questionnaire.create')
                ->withInput()
                ->with('error', 'Could not save the survey. Please try again. If it keeps failing, contact the administrator.');
        }

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey submitted successfully.');
    }

    public function edit(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::with(['answers.answerOptions'])->findOrFail($id);

        return $this->renderForm('edit', $respondent);
    }

    public function show(Request $request)
    {
        SurveySchema::ensureOptionalNaFromQ11();

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
        $respondent = SurveyRespondent::with(['answers.answerOptions'])->findOrFail($id);

        try {
            $this->validateAnswers($request);
        } catch (ValidationException $e) {
            // Edit form is POST-only; re-render instead of redirecting GET to /questionnaire/edit (405).
            $request->flash();

            return $this->renderForm('edit', $respondent)->withErrors($e->validator);
        }

        try {
            // SP commits its own transaction; delete old only after the new insert succeeds.
            $this->insertViaStoredProcedure(
                $this->resolveParticipantName($request),
                $this->buildAnswersJson($request),
                (int) $request->input('CapitalMarketProgramID')
            );

            DB::connection('sqlsrv')->transaction(function () use ($respondent) {
                $this->deleteAnswers($respondent);
                $respondent->delete();
            });
        } catch (Throwable $e) {
            Log::error('Survey update failed: '.$e->getMessage(), [
                'respondent_id' => $id,
                'exception' => $e,
            ]);

            $request->flash();

            return $this->renderForm('edit', $respondent)
                ->with('error', 'Could not update the survey. Please try again. If it keeps failing, contact the administrator.');
        }

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey updated successfully.');
    }

    public function destroy(Request $request)
    {
        $id = SecureId::decode($request->input('token'));
        $respondent = SurveyRespondent::findOrFail($id);

        try {
            DB::connection('sqlsrv')->transaction(function () use ($respondent) {
                $this->deleteAnswers($respondent);
                $respondent->delete();
            });
        } catch (Throwable $e) {
            Log::error('Survey delete failed: '.$e->getMessage(), [
                'respondent_id' => $id,
                'exception' => $e,
            ]);

            return redirect()
                ->route('questionnaire')
                ->with('error', 'Could not delete the survey. Please try again.');
        }

        return redirect()
            ->route('questionnaire')
            ->with('success', 'Survey deleted successfully.');
    }

    /**
     * Render create/edit form. Attaches program from SurveyAnswer only when that column exists.
     */
    private function renderForm(string $mode, ?SurveyRespondent $respondent = null)
    {
        SurveySchema::ensureOptionalNaFromQ11();

        if ($respondent && ! $respondent->CapitalMarketProgramID) {
            $programId = $this->programIdFromAnswers($respondent);
            if ($programId) {
                $respondent->CapitalMarketProgramID = $programId;
            }
        }

        return view('questionnaire.form', [
            'sections' => $this->loadFormStructure(),
            'programs' => $this->loadPrograms(),
            'respondent' => $respondent,
            'answers' => $respondent ? $this->mapExistingAnswers($respondent) : [],
            'mode' => $mode,
            'secureToken' => $respondent
                ? SecureId::encode($respondent->RespondentId)
                : null,
        ]);
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
     * Resolve program for a respondent from SurveyRespondent, then legacy SurveyAnswer column,
     * falling back to the current active program.
     */
    private function resolveProgramForRespondent(?SurveyRespondent $respondent = null): ?CapitalMarketProgram
    {
        if ($respondent) {
            $programId = $respondent->CapitalMarketProgramID
                ? (int) $respondent->CapitalMarketProgramID
                : $this->programIdFromAnswers($respondent);

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
     * Legacy DBs may store program id on SurveyAnswer; newer ones only on SurveyRespondent.
     */
    private function programIdFromAnswers(SurveyRespondent $respondent): ?int
    {
        if (! SurveySchema::answerHasProgramColumn()) {
            return null;
        }

        try {
            $programId = DB::connection('sqlsrv')
                ->table('SurveyAnswer')
                ->where('RespondentId', $respondent->RespondentId)
                ->whereNotNull('CapitalMarketProgramID')
                ->value('CapitalMarketProgramID');

            return $programId ? (int) $programId : null;
        } catch (Throwable $e) {
            Log::warning('Could not read SurveyAnswer.CapitalMarketProgramID: '.$e->getMessage());

            return null;
        }
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
                        $this->normalizeQuestionRuntimeFlags($question);

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
            ->map(function (SurveyQuestion $question) {
                $this->normalizeQuestionRuntimeFlags($question);

                return $question;
            })
            ->values();
    }

    /**
     * Apply known form rules in-memory so create/edit/validate behave correctly
     * even before SurveySchema finishes patching SurveyQuestion rows.
     */
    private function normalizeQuestionRuntimeFlags(SurveyQuestion $question): void
    {
        $code = strtoupper(trim((string) $question->QuestionCode));
        if ($code === '') {
            return;
        }

        if (! $this->isEffectivelyRequired($question)) {
            $question->IsRequired = false;
        }

        if (preg_match('/^Q?(\d+)([A-Z]?)$/i', $code, $m)
            && ($m[2] ?? '') === ''
            && in_array((int) $m[1], [8, 10, 11, 12, 13, 14, 15, 16, 17, 20, 26], true)) {
            $question->QuestionType = SurveySchema::TYPE_MULTI;
            $question->IsRequired = false;
        }
    }

    private function validateAnswers(Request $request, ?string $redirectTo = null): void
    {
        $questions = $this->activeQuestions();
        $posted = $request->input('answers', []);

        $rules = [
            'CapitalMarketProgramID' => [
                'required',
                'integer',
                Rule::exists(CapitalMarketProgram::class, 'CapitalMarketProgramID'),
            ],
        ];
        $messages = [
            'CapitalMarketProgramID.required' => 'Please select a Capital Market Program.',
            'CapitalMarketProgramID.exists' => 'The selected Capital Market Program is invalid.',
        ];
        $attributes = [
            'CapitalMarketProgramID' => 'Capital Market Program',
        ];
        $firstMissingId = null;

        foreach ($questions as $question) {
            if (! $this->isEffectivelyRequired($question)) {
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

            // Prefer an explicit GET-safe URL (create). Avoid previous() for POST-only edit/view.
            $redirectUrl = $redirectTo ?: route('questionnaire.create');
            $previous = url()->previous();
            if (! $redirectTo && $previous && ! $this->isPostOnlyQuestionnaireUrl($previous)) {
                $redirectUrl = $previous;
            }

            if ($jumpId) {
                $redirectUrl = preg_replace('/#.*$/', '', $redirectUrl).'#question-'.$jumpId;
            }

            throw (new ValidationException($validator))->redirectTo($redirectUrl);
        }
    }

    private function isPostOnlyQuestionnaireUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = rtrim($path, '/');

        foreach (['/questionnaire/edit', '/questionnaire/view', '/questionnaire/update', '/questionnaire/delete', '/questionnaire/store'] as $needle) {
            if (str_ends_with($path, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Required flag with known optional codes as a safety net
     * (covers DB rows that have not yet been patched by SurveySchema).
     */
    private function isEffectivelyRequired(SurveyQuestion $question): bool
    {
        if (! $question->IsRequired) {
            return false;
        }

        $code = strtoupper(trim((string) $question->QuestionCode));
        if ($code === '') {
            return true;
        }

        if (in_array($code, ['1', 'Q1', '4', 'Q4', '5', 'Q5', '9A', '9B', '10', 'Q10'], true)) {
            return false;
        }

        if (preg_match('/^Q?(\d+)/i', $code, $m) && (int) $m[1] >= 11) {
            return false;
        }

        return true;
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
        $questionsById = SurveyQuestion::with('options')->get()->keyBy('QuestionId');
        $mapped = [];

        foreach ($respondent->answers as $answer) {
            $question = $questionsById->get($answer->QuestionId);
            $qid = (int) $answer->QuestionId;
            $code = strtoupper(trim((string) ($question->QuestionCode ?? '')));

            $optionIds = array_values(array_map(
                'intval',
                $answer->answerOptions->pluck('OptionId')->all()
            ));
            $others = [];

            foreach ($answer->answerOptions as $ao) {
                if ($ao->OtherText !== null && trim((string) $ao->OtherText) !== '') {
                    $others[(int) $ao->OptionId] = $ao->OtherText;
                }
            }

            // Use the question's real type — never infer from option count.
            // (A single checked checkbox used to be mistagged as "single" and
            // failed to restore on the edit form.)
            if ($question && $question->isMulti()) {
                $entry = [
                    'type' => 'multi',
                    'values' => $optionIds,
                    'other' => $others,
                ];
            } elseif ($question && $question->isSingle()) {
                $entry = [
                    'type' => 'single',
                    'value' => $optionIds[0] ?? null,
                    'values' => $optionIds,
                    'other' => $others,
                ];
            } elseif ($optionIds !== []) {
                $entry = count($optionIds) > 1
                    ? ['type' => 'multi', 'values' => $optionIds, 'other' => $others]
                    : [
                        'type' => 'single',
                        'value' => $optionIds[0],
                        'values' => $optionIds,
                        'other' => $others,
                    ];
            } else {
                $entry = [
                    'type' => 'text',
                    'value' => $answer->AnswerText,
                ];
            }

            $mapped[$qid] = $entry;

            // Also key by QuestionCode so edit survives duplicate QuestionId rows.
            if ($code === '') {
                continue;
            }

            if (! isset($mapped[$code])) {
                $mapped[$code] = $entry;
                continue;
            }

            if (($entry['type'] ?? '') === 'multi' || ($mapped[$code]['type'] ?? '') === 'multi') {
                $merged = array_values(array_unique(array_merge(
                    array_map('intval', $mapped[$code]['values'] ?? []),
                    $optionIds
                )));
                $mapped[$code] = [
                    'type' => 'multi',
                    'values' => $merged,
                    'other' => ($mapped[$code]['other'] ?? []) + $others,
                ];
            } elseif (($mapped[$code]['value'] ?? null) === null && ($entry['value'] ?? null) !== null) {
                $mapped[$code] = $entry;
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
        $questionsById = SurveyQuestion::with('options')->get()->keyBy('QuestionId');
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

            $type = 'text';
            if ($question && $question->isMulti()) {
                $type = 'multi';
            } elseif ($question && $question->isSingle()) {
                $type = 'single';
            } elseif ($optionIds !== []) {
                $type = count($optionIds) > 1 ? 'multi' : 'single';
            }

            $entry = [
                'type' => $type,
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
                if (($mapped[$code]['type'] ?? '') !== 'multi' && count($mapped[$code]['values']) > 1) {
                    $mapped[$code]['type'] = 'multi';
                }
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
