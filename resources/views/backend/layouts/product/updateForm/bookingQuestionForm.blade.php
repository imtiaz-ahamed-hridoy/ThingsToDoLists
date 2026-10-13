{{-- ========================= 
     Booking Questions Edit Form
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-0 text-primary fw-semibold">Booking Question Configuration</h4>
        <small class="text-muted">Customer input, validation & pricing triggers</small>
    </div>

    <div class="card-body">
        <div id="questions_container">
         
            @foreach($bookingQuestions as $qIndex => $q)
                <div class="border rounded p-3 mb-4 question-block">
                    <h5 class="text-secondary">Question {{ (int)$qIndex + 1 }}</h5>


                    {{-- Personal Data --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="booking_questions[{{ $qIndex }}][personalData]" value="0">
                        <input class="form-check-input" type="checkbox" value="1"
                               name="booking_questions[{{ $qIndex }}][personalData]"
                               {{ ($q['personalData'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold">Personal Data</label>
                    </div>

                    {{-- Required --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="booking_questions[{{ $qIndex }}][required]" value="0">
                        <input class="form-check-input" type="checkbox" value="1"
                               name="booking_questions[{{ $qIndex }}][required]"
                               {{ ($q['required'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold">Required</label>
                    </div>

                    {{-- Question Code --}}
                    <div class="mb-2">
                        <label class="form-label">Question Code</label>
                        <input type="text" class="form-control"
                               name="booking_questions[{{ $qIndex }}][questionCode]"
                               value="{{ $q['questionCode'] ?? '' }}">
                    </div>

                    {{-- Label --}}
                    <div class="mb-2">
                        <label class="form-label">Question Label</label>
                        <textarea class="form-control" rows="2"
                                  name="booking_questions[{{ $qIndex }}][label]">{{ $q['label'] ?? '' }}</textarea>
                    </div>

                    {{-- Help --}}
                    <div class="mb-2">
                        <label class="form-label">Help Text</label>
                        <textarea class="form-control" rows="2"
                                  name="booking_questions[{{ $qIndex }}][help]">{{ $q['help'] ?? '' }}</textarea>
                    </div>

                    {{-- Placeholder --}}
                    <div class="mb-2">
                        <label class="form-label">Placeholder</label>
                        <input type="text" class="form-control"
                               name="booking_questions[{{ $qIndex }}][placeholder]"
                               value="{{ $q['placeholder'] ?? '' }}">
                    </div>

                    {{-- Data Type --}}
                    <div class="mb-2">
                        <label class="form-label">Data Type</label>
                        @php $dataTypeOld = $q['dataType'] ?? ''; @endphp
                        <select class="form-control" name="booking_questions[{{ $qIndex }}][dataType]">
                            <option value="">Select</option>
                            <option value="string" {{ $dataTypeOld==='string'?'selected':'' }}>Text</option>
                            <option value="number" {{ $dataTypeOld==='number'?'selected':'' }}>Number</option>
                            <option value="date" {{ $dataTypeOld==='date'?'selected':'' }}>Date</option>
                            <option value="email" {{ $dataTypeOld==='email'?'selected':'' }}>Email</option>
                            <option value="phone" {{ $dataTypeOld==='phone'?'selected':'' }}>Phone</option>
                            <option value="boolean" {{ $dataTypeOld==='boolean'?'selected':'' }}>Yes / No</option>
                        </select>
                    </div>

                    {{-- Select From Options --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="booking_questions[{{ $qIndex }}][selectFromOptions]" value="0">
                        <input class="form-check-input select-from-options" type="checkbox" value="1"
                               name="booking_questions[{{ $qIndex }}][selectFromOptions]"
                               {{ ($q['selectFromOptions'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold">Select from Options</label>
                    </div>

                    {{-- Allow Multiple --}}
                    <div class="form-check mb-2">
                        <input type="hidden" name="booking_questions[{{ $qIndex }}][selectMultiple]" value="0">
                        <input class="form-check-input" type="checkbox" value="1"
                               name="booking_questions[{{ $qIndex }}][selectMultiple]"
                               {{ ($q['selectMultiple'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold">Allow Multiple Selection</label>
                    </div>

                    {{-- Options JSON --}}
                    <input type="hidden" name="booking_questions[{{ $qIndex }}][options]"
                           class="options-json"
                           value='{{ json_encode($q['options'] ?? new stdClass()) }}'>

                    {{-- Flags JSON --}}
                    <input type="hidden" name="booking_questions[{{ $qIndex }}][flags]"
                           class="flags-json"
                           value='{{ json_encode($q['flags'] ?? new stdClass()) }}'>

                    {{-- Required Before Departure --}}
                    <div class="form-check mt-2">
                        <input type="hidden" name="booking_questions[{{ $qIndex }}][requiredBeforeDeparture]" value="0">
                        <input class="form-check-input" type="checkbox" value="1"
                               name="booking_questions[{{ $qIndex }}][requiredBeforeDeparture]"
                               {{ ($q['requiredBeforeDeparture'] ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold">Required Before Departure</label>
                    </div>

                    <button type="button" class="btn btn-danger btn-sm mt-2 remove-question">Remove Question</button>
                </div>
            @endforeach
        </div>

        <button type="button" class="btn btn-primary" id="add_question">+ Add Question</button>
    </div>
</div>

{{-- ========================= JS ========================= --}}
<script>
document.addEventListener('click', function(e){
    if(e.target.classList.contains('remove-question')){
        e.target.closest('.question-block').remove();
        updateQuestionIndexes();
    }
});

document.getElementById('add_question').addEventListener('click', function(e){
    e.preventDefault();
    const container = document.getElementById('questions_container');
    const qIndex = container.children.length;

    const questionHtml = `
        <div class="border rounded p-3 mb-4 question-block">
            <h5 class="text-secondary">Question ${qIndex+1}</h5>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${qIndex}][personalData]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${qIndex}][personalData]">
                <label class="form-check-label fw-semibold">Personal Data</label>
            </div>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${qIndex}][required]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${qIndex}][required]">
                <label class="form-check-label fw-semibold">Required</label>
            </div>

            <div class="mb-2">
                <label class="form-label">Question Code</label>
                <input type="text" class="form-control" name="booking_questions[${qIndex}][questionCode]">
            </div>

            <div class="mb-2">
                <label class="form-label">Question Label</label>
                <textarea class="form-control" rows="2" name="booking_questions[${qIndex}][label]"></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Help Text</label>
                <textarea class="form-control" rows="2" name="booking_questions[${qIndex}][help]"></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Placeholder</label>
                <input type="text" class="form-control" name="booking_questions[${qIndex}][placeholder]">
            </div>

            <div class="mb-2">
                <label class="form-label">Data Type</label>
                <select class="form-control" name="booking_questions[${qIndex}][dataType]">
                    <option value="">Select</option>
                    <option value="string">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="email">Email</option>
                    <option value="phone">Phone</option>
                    <option value="boolean">Yes / No</option>
                </select>
            </div>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${qIndex}][selectFromOptions]" value="0">
                <input class="form-check-input select-from-options" type="checkbox" value="1"
                       name="booking_questions[${qIndex}][selectFromOptions]">
                <label class="form-check-label fw-semibold">Select from Options</label>
            </div>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${qIndex}][selectMultiple]" value="0">
                <input class="form-check-input" type="checkbox" value="1"
                       name="booking_questions[${qIndex}][selectMultiple]">
                <label class="form-check-label fw-semibold">Allow Multiple Selection</label>
            </div>

            <input type="hidden" name="booking_questions[${qIndex}][options]" class="options-json" value='{}'>
            <input type="hidden" name="booking_questions[${qIndex}][flags]" class="flags-json" value='{}'>

            <div class="form-check mt-2">
                <input type="hidden" name="booking_questions[${qIndex}][requiredBeforeDeparture]" value="0">
                <input class="form-check-input" type="checkbox" value="1"
                       name="booking_questions[${qIndex}][requiredBeforeDeparture]">
                <label class="form-check-label fw-semibold">Required Before Departure</label>
            </div>

            <button type="button" class="btn btn-danger btn-sm mt-2 remove-question">Remove Question</button>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', questionHtml);
    updateQuestionIndexes();
});

// Re-index all questions
function updateQuestionIndexes(){
    const questions = document.querySelectorAll('#questions_container .question-block');
    questions.forEach((q, index) => {
        q.querySelector('h5').innerText = `Question ${index+1}`;
        const inputs = q.querySelectorAll('input, textarea, select');
        inputs.forEach(input => {
            const name = input.getAttribute('name');
            if(name){
                const newName = name.replace(/booking_questions\[\d+\]/, `booking_questions[${index}]`);
                input.setAttribute('name', newName);
            }
        });
    });
}
</script>
