@extends('layouts.app')

@section('title', 'View Survey — '.($respondent->ParticipantName ?: 'Response #'.$respondent->RespondentId))

@section('content')
<div class="page-header no-print">
    <div>
        <h2 class="page-title">Survey Preview</h2>
        <p class="page-subtitle mb-0">Document-style view — {{ $program->CapitalMarketProgramName ?? 'Capital Market Awareness Questionnaire' }}</p>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
            <i class="bi bi-printer"></i> Print
        </button>
        <a href="{{ route('questionnaire') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<article class="survey-doc shadow-sm">
    <header class="survey-doc-header">
        <div class="doc-org">Securities Board of Nepal (SEBON)</div>
        <h1>Capital Market Awareness Questionnaire</h1>
        <div class="doc-sub">{{ $program->CapitalMarketProgramName ?? '—' }}</div>
        <p class="doc-instructions"><em>Instructions: Please tick for each question.</em></p>
    </header>

    @foreach($sections as $section)
        <section class="doc-section">
            <h2 class="doc-section-title">{{ $section->SurveySectionName }}</h2>

            @foreach($section->questions as $question)
                @php
                    $code = strtoupper(trim((string) $question->QuestionCode));
                    $answer = $answers[$code] ?? null;
                    $selectedIds = array_map('intval', $answer['values'] ?? (isset($answer['value']) && is_numeric($answer['value']) ? [(int) $answer['value']] : []));
                    $selectedTexts = array_map('strtolower', $answer['texts'] ?? []);
                    $textValue = trim((string) ($answer['value'] ?? ''));
                    $isChoice = $question->isMulti() || $question->isSingle();
                @endphp

                <div class="doc-question">
                    <div class="doc-q-title">
                        <span class="doc-q-code">{{ $question->QuestionCode }}.</span>
                        {{ $question->QuestionText }}
                        @if($question->isMulti())
                            <span class="doc-hint">(Mark all that apply)</span>
                        @endif
                    </div>

                    @if($isChoice)
                        <div class="doc-options">
                            @foreach($question->options as $option)
                                @php
                                    $checked = in_array((int) $option->OptionId, $selectedIds, true)
                                        || in_array(strtolower(trim((string) $option->OptionText)), $selectedTexts, true);
                                    $otherText = $answer['other'][$option->OptionId]
                                        ?? $answer['other'][strtolower(trim((string) $option->OptionText))]
                                        ?? (($checked && !empty($answer['other_text'])) ? $answer['other_text'] : null);
                                @endphp
                                <span class="doc-option {{ $checked ? 'is-checked' : '' }}">
                                    <span class="doc-mark" aria-hidden="true">{{ $checked ? '☑' : '☐' }}</span>
                                    <span class="doc-option-label">{{ $option->OptionText }}</span>
                                    @if($option->AllowsOtherText && $checked && $otherText)
                                        <span class="doc-other">: <u>{{ $otherText }}</u></span>
                                    @endif
                                </span>
                            @endforeach
                            @php
                                $anyChecked = collect($question->options)->contains(function ($option) use ($selectedIds, $selectedTexts) {
                                    return in_array((int) $option->OptionId, $selectedIds, true)
                                        || in_array(strtolower(trim((string) $option->OptionText)), $selectedTexts, true);
                                });
                            @endphp
                            @if(! $anyChecked)
                                <span class="doc-option doc-blank-answer">
                                    <span class="doc-mark" aria-hidden="true">☐</span>
                                    <span class="doc-option-label text-muted"><em>Left blank</em></span>
                                </span>
                            @endif
                        </div>
                    @else
                        <div class="doc-blank">
                            @if($textValue !== '')
                                <span class="doc-filled">{{ $textValue }}</span>
                            @else
                                <span class="doc-underline text-muted"><em>Left blank</em></span>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach

    <footer class="survey-doc-footer">
        Thank you for your participation! &nbsp;|&nbsp; {{ $program->CapitalMarketProgramName ?? 'SEBON Survey' }}
    </footer>
</article>
@endsection
