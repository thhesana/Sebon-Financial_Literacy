@extends('layouts.app')

@section('title', $mode === 'edit' ? 'Edit Survey' : 'New Survey')

@section('content')
<div class="page-header">
    <div>
        <h2 class="page-title">{{ $mode === 'edit' ? 'Edit Survey Response' : 'New Survey Form' }}</h2>
        <p class="page-subtitle mb-0">
            {{ ($programs ?? collect())->first()->CapitalMarketProgramName ?? 'Capital Market Awareness Questionnaire' }}
            — Capital Market Awareness Questionnaire
        </p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('questionnaire') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger" id="validation-alert">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($sections->isEmpty())
    <div class="alert alert-warning">
        No active survey questions found in the database. Please ensure
        <code>SurveySection</code>, <code>SurveyQuestion</code>, and
        <code>SurveyQuestionOption</code> are populated.
    </div>
@else
<form method="POST"
      action="{{ $mode === 'edit' ? route('questionnaire.update') : route('questionnaire.store') }}"
      id="survey-form"
      novalidate>
    @csrf
    @if($mode === 'edit')
        <input type="hidden" name="token" value="{{ $secureToken ?? \App\Support\SecureId::encode($respondent->RespondentId) }}">
    @endif

    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-primary text-white fw-semibold">
            Program
        </div>
        <div class="card-body">
            <label for="CapitalMarketProgramID" class="form-label fw-semibold">
                Capital Market Program <span class="text-danger">*</span>
            </label>
            @php
                $defaultProgramId = old(
                    'CapitalMarketProgramID',
                    $respondent->CapitalMarketProgramID
                        ?? (($programs ?? collect())->first()->CapitalMarketProgramID ?? '')
                );
            @endphp
            <select name="CapitalMarketProgramID"
                    id="CapitalMarketProgramID"
                    class="form-select form-select-lg @error('CapitalMarketProgramID') is-invalid @enderror"
                    required>
                @if(($programs ?? collect())->isEmpty())
                    <option value="">— No active program —</option>
                @endif
                @foreach(($programs ?? []) as $program)
                    <option value="{{ $program->CapitalMarketProgramID }}"
                        {{ (string) $defaultProgramId === (string) $program->CapitalMarketProgramID ? 'selected' : '' }}>
                        {{ $program->displayLabel() }}
                    </option>
                @endforeach
            </select>
            @error('CapitalMarketProgramID')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            @if(($programs ?? collect())->isEmpty())
                <div class="text-danger small mt-2">
                    No active programs found. Add one under <strong>Program Master</strong> first.
                </div>
            @endif
        </div>
    </div>

    @foreach($sections as $section)
        <div class="card mb-4 shadow-sm survey-section" id="section-{{ $section->SurveySectionId }}">
            <div class="card-header bg-primary text-white fw-semibold">
                {{ $section->SurveySectionName }}
            </div>
            <div class="card-body">
                @foreach($section->questions as $question)
                    @php
                        $qid = $question->QuestionId;
                        $codeKey = strtoupper(trim((string) $question->QuestionCode));
                        $existing = $answers[$qid] ?? ($codeKey !== '' ? ($answers[$codeKey] ?? null) : null);
                        $parentAttr = $question->ParentQuestionId ? ' data-parent-question="'.$question->ParentQuestionId.'" data-parent-trigger="'.$question->ParentTriggerOptionId.'"' : '';
                        $oldValue = old("answers.$qid");
                        $hasError = $errors->has("answers.$qid");

                        // Match server-side optional safety net (Q4, Q10, Q11+, etc.)
                        $codeNum = (preg_match('/^Q?(\d+)/i', $codeKey, $codeMatch)) ? (int) $codeMatch[1] : 0;
                        $isKnownOptional = in_array($codeKey, ['1', 'Q1', '4', 'Q4', '5', 'Q5', '9A', '9B', '10', 'Q10'], true)
                            || $codeNum >= 11;
                        $isOptional = (! $question->IsRequired) || $isKnownOptional;
                        $isRequiredUi = $question->IsRequired && ! $isKnownOptional;

                        // Normalize previously saved option IDs for both multi and single.
                        $selectedIds = [];
                        if (is_array($oldValue)) {
                            $selectedIds = array_map('intval', $oldValue);
                        } elseif ($oldValue !== null && $oldValue !== '') {
                            $selectedIds = [(int) $oldValue];
                        } elseif (is_array($existing)) {
                            if (! empty($existing['values']) && is_array($existing['values'])) {
                                $selectedIds = array_map('intval', $existing['values']);
                            } elseif (isset($existing['value']) && is_numeric($existing['value'])) {
                                $selectedIds = [(int) $existing['value']];
                            }
                        }
                    @endphp

                    <div class="mb-4 question-block border-bottom pb-3 {{ $hasError ? 'is-invalid-question' : '' }}"
                         id="question-{{ $qid }}"
                         data-question-id="{{ $qid }}"
                         data-required="{{ $isRequiredUi ? 1 : 0 }}"
                         data-type="{{ $question->isMulti() ? 'multi' : ($question->isSingle() ? 'single' : 'text') }}"
                         {!! $parentAttr !!}>
                        <label class="form-label fw-semibold">
                            {{ $question->QuestionCode ? $question->QuestionCode.'. ' : '' }}{{ $question->QuestionText }}
                            @if($isRequiredUi)
                                <span class="text-danger">*</span>
                            @elseif($isOptional)
                                <span class="text-muted fw-normal small">(optional — may leave blank{{ $question->isMulti() || $question->isSingle() ? ' or choose N/A' : '' }})</span>
                            @endif
                        </label>

                        @if($question->isMulti())
                            <div class="option-grid option-group mt-2">
                                @foreach($question->options as $option)
                                    @php
                                        $checked = in_array((int) $option->OptionId, $selectedIds, true);
                                        $otherOld = old("other_text.$qid.{$option->OptionId}", $existing['other'][$option->OptionId] ?? ($existing['other'][(int) $option->OptionId] ?? ''));
                                        $isNa = (bool) preg_match('/^n\s*\/?\s*a$/i', trim((string) $option->OptionText));
                                    @endphp
                                    <label class="option-card {{ $checked ? 'is-selected' : '' }}" for="opt-{{ $option->OptionId }}">
                                        <input class="answer-option"
                                               type="checkbox"
                                               name="answers[{{ $qid }}][]"
                                               id="opt-{{ $option->OptionId }}"
                                               value="{{ $option->OptionId }}"
                                               data-question="{{ $qid }}"
                                               data-allows-other="{{ $option->AllowsOtherText ? 1 : 0 }}"
                                               data-is-na="{{ $isNa ? 1 : 0 }}"
                                               {{ $checked ? 'checked' : '' }}>
                                        <span class="option-indicator option-check" aria-hidden="true">
                                            <i class="bi bi-check-lg"></i>
                                        </span>
                                        <span class="option-body">
                                            <span class="option-text">{{ $option->OptionText }}</span>
                                            @if($option->AllowsOtherText)
                                                <input type="text"
                                                       name="other_text[{{ $qid }}][{{ $option->OptionId }}]"
                                                       class="form-control other-text-input mt-2"
                                                       placeholder="Please specify"
                                                       value="{{ $otherOld }}"
                                                       onclick="event.stopPropagation()"
                                                       style="{{ $checked ? '' : 'display:none' }}">
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if($isOptional)
                                <button type="button"
                                        class="btn btn-link btn-sm px-0 mt-1 clear-answer-btn"
                                        data-question="{{ $qid }}">
                                    Clear selection (leave blank)
                                </button>
                            @endif
                        @elseif($question->isSingle())
                            <div class="option-grid option-group mt-2">
                                @foreach($question->options as $option)
                                    @php
                                        $checked = in_array((int) $option->OptionId, $selectedIds, true);
                                        $otherOld = old("other_text.$qid.{$option->OptionId}", $existing['other'][$option->OptionId] ?? ($existing['other'][(int) $option->OptionId] ?? ''));
                                        $isNa = (bool) preg_match('/^n\s*\/?\s*a$/i', trim((string) $option->OptionText));
                                    @endphp
                                    <label class="option-card {{ $checked ? 'is-selected' : '' }}" for="opt-{{ $option->OptionId }}">
                                        <input class="answer-option"
                                               type="radio"
                                               name="answers[{{ $qid }}]"
                                               id="opt-{{ $option->OptionId }}"
                                               value="{{ $option->OptionId }}"
                                               data-question="{{ $qid }}"
                                               data-allows-other="{{ $option->AllowsOtherText ? 1 : 0 }}"
                                               data-is-na="{{ $isNa ? 1 : 0 }}"
                                               {{ $checked ? 'checked' : '' }}>
                                        <span class="option-indicator option-radio" aria-hidden="true"></span>
                                        <span class="option-body">
                                            <span class="option-text">{{ $option->OptionText }}</span>
                                            @if($option->AllowsOtherText)
                                                <input type="text"
                                                       name="other_text[{{ $qid }}][{{ $option->OptionId }}]"
                                                       class="form-control other-text-input mt-2"
                                                       placeholder="Please specify"
                                                       value="{{ $otherOld }}"
                                                       onclick="event.stopPropagation()"
                                                       style="{{ $checked ? '' : 'display:none' }}">
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if($isOptional)
                                <button type="button"
                                        class="btn btn-link btn-sm px-0 mt-1 clear-answer-btn"
                                        data-question="{{ $qid }}">
                                    Clear selection (leave blank)
                                </button>
                            @endif
                        @elseif($question->isTextarea())
                            @php
                                $textVal = is_string($oldValue) ? $oldValue : ($existing['value'] ?? '');
                            @endphp
                            <textarea name="answers[{{ $qid }}]"
                                      class="form-control answer-text form-control-lg {{ $hasError ? 'is-invalid' : '' }}"
                                      rows="3">{{ $textVal }}</textarea>
                        @else
                            @php
                                $textVal = is_string($oldValue) ? $oldValue : ($existing['value'] ?? '');
                            @endphp
                            <input type="text"
                                   name="answers[{{ $qid }}]"
                                   class="form-control answer-text form-control-lg {{ $hasError ? 'is-invalid' : '' }}"
                                   value="{{ $textVal }}">
                        @endif

                        @error("answers.$qid")
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="d-flex justify-content-end gap-2 mb-5">
        <a href="{{ route('questionnaire') }}" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-success px-4">
            <i class="bi bi-check2-circle"></i>
            {{ $mode === 'edit' ? 'Update Survey' : 'Submit Survey' }}
        </button>
    </div>
</form>
@endif
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    function isVisible(block) {
        return block && block.style.display !== 'none' && block.offsetParent !== null;
    }

    function questionIsAnswered(block) {
        var type = block.getAttribute('data-type');
        if (type === 'multi') {
            return !!block.querySelector('input.answer-option[type="checkbox"]:checked');
        }
        if (type === 'single') {
            return !!block.querySelector('input.answer-option[type="radio"]:checked');
        }
        var input = block.querySelector('.answer-text');
        return !!(input && String(input.value || '').trim() !== '');
    }

    function scrollToQuestion(block) {
        if (!block) return;
        block.classList.add('is-invalid-question');
        block.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var focusEl = block.querySelector('input.answer-option, input.answer-text, textarea');
        if (focusEl) {
            setTimeout(function () { focusEl.focus({ preventScroll: true }); }, 350);
        }
    }

    function syncCardState(input) {
        var card = input.closest('.option-card');
        if (!card) return;

        if (input.type === 'radio') {
            document.querySelectorAll('input[name="' + input.name + '"]').forEach(function (r) {
                var c = r.closest('.option-card');
                if (c) c.classList.toggle('is-selected', r.checked);
            });
        } else {
            card.classList.toggle('is-selected', input.checked);
        }
    }

    function syncConditionalQuestions() {
        document.querySelectorAll('.question-block[data-parent-question]').forEach(function (block) {
            var parentId = block.getAttribute('data-parent-question');
            var triggerId = block.getAttribute('data-parent-trigger');
            var radioChecked = document.querySelector(
                'input.answer-option[type="radio"][data-question="' + parentId + '"][value="' + triggerId + '"]:checked'
            );
            var checkboxChecked = document.querySelector(
                'input.answer-option[type="checkbox"][data-question="' + parentId + '"][value="' + triggerId + '"]:checked'
            );
            var show = !!(radioChecked || checkboxChecked);
            block.style.display = show ? '' : 'none';
            if (!show) {
                block.querySelectorAll('input, textarea, select').forEach(function (el) {
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        el.checked = false;
                        syncCardState(el);
                    } else if (el.classList.contains('other-text-input')) {
                        el.value = '';
                        el.style.display = 'none';
                    } else {
                        el.value = '';
                    }
                });
            }
        });
    }

    function syncOtherInputs(changed) {
        if (changed.getAttribute('data-allows-other') !== '1') return;
        var wrap = changed.closest('.option-card');
        if (!wrap) return;
        var other = wrap.querySelector('.other-text-input');
        if (!other) return;
        other.style.display = changed.checked ? '' : 'none';
        if (!changed.checked) other.value = '';
    }

    function syncNaExclusivity(changed) {
        if (changed.type !== 'checkbox' || !changed.checked) return;
        var block = changed.closest('.question-block');
        if (!block) return;
        var isNa = changed.getAttribute('data-is-na') === '1';
        block.querySelectorAll('input.answer-option[type="checkbox"]').forEach(function (cb) {
            if (cb === changed) return;
            if (isNa || cb.getAttribute('data-is-na') === '1') {
                if (cb.checked) {
                    cb.checked = false;
                    syncCardState(cb);
                    syncOtherInputs(cb);
                }
            }
        });
    }

    function clearQuestionAnswer(questionId) {
        var block = document.getElementById('question-' + questionId);
        if (!block) return;
        block.querySelectorAll('input.answer-option').forEach(function (el) {
            el.checked = false;
            syncCardState(el);
            syncOtherInputs(el);
        });
        block.querySelectorAll('.other-text-input').forEach(function (el) {
            el.value = '';
            el.style.display = 'none';
        });
        block.classList.remove('is-invalid-question');
        syncConditionalQuestions();
    }

    document.querySelectorAll('.answer-option').forEach(function (el) {
        syncCardState(el);

        el.addEventListener('change', function () {
            var block = el.closest('.question-block');
            if (block) block.classList.remove('is-invalid-question');

            syncCardState(el);
            syncNaExclusivity(el);

            if (el.type === 'radio') {
                document.querySelectorAll('input[name="' + el.name + '"]').forEach(function (r) {
                    var wrap = r.closest('.option-card');
                    var other = wrap ? wrap.querySelector('.other-text-input') : null;
                    if (other) {
                        other.style.display = r.checked ? '' : 'none';
                        if (!r.checked) other.value = '';
                    }
                });
            } else {
                syncOtherInputs(el);
            }
            syncConditionalQuestions();
        });
    });

    document.querySelectorAll('.clear-answer-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            clearQuestionAnswer(btn.getAttribute('data-question'));
        });
    });

    document.querySelectorAll('.answer-text').forEach(function (el) {
        el.addEventListener('input', function () {
            var block = el.closest('.question-block');
            if (block) block.classList.remove('is-invalid-question');
        });
    });

    var form = document.getElementById('survey-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            var firstMissing = null;
            document.querySelectorAll('.question-block[data-required="1"]').forEach(function (block) {
                if (!isVisible(block)) return;
                if (!questionIsAnswered(block) && !firstMissing) {
                    firstMissing = block;
                }
                block.classList.toggle('is-invalid-question', !questionIsAnswered(block));
            });

            if (firstMissing) {
                e.preventDefault();
                scrollToQuestion(firstMissing);
            }
        });
    }

    syncConditionalQuestions();
    document.querySelectorAll('.answer-option:checked').forEach(syncOtherInputs);

    if (window.location.hash && window.location.hash.indexOf('#question-') === 0) {
        var target = document.querySelector(window.location.hash);
        if (target) setTimeout(function () { scrollToQuestion(target); }, 100);
    } else {
        var invalid = document.querySelector('.question-block.is-invalid-question');
        if (invalid) setTimeout(function () { scrollToQuestion(invalid); }, 100);
    }
});
</script>
@endsection
