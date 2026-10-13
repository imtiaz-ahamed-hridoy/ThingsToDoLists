{{-- =========================
     Booking Questions Create Form
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-0 text-primary fw-semibold">Booking Question Configuration</h4>
        <small class="text-muted">Customer input, validation & pricing triggers</small>
    </div>

    <div class="card-body">
        <div id="questions_container_create"></div>
        <button type="button" class="btn btn-primary mt-2" id="add_question_create">+ Add Question</button>
    </div>
</div>

<script>
(() => {
    const container = document.getElementById('questions_container_create');

    const createQuestionBlock = (index, data = {}) => {
        const block = document.createElement('div');
        block.className = ' p-3 mb-4 question-block';
        block.dataset.index = index;

        const html = `
            <h5 class="text-secondary">Question ${index+1}</h5>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${index}][personalData]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${index}][personalData]" ${data.personalData?'checked':''}>
                <label class="form-check-label fw-semibold">Personal Data</label>
            </div>

            <div class="form-check mb-2">
                <input type="hidden" name="booking_questions[${index}][required]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${index}][required]" ${data.required?'checked':''}>
                <label class="form-check-label fw-semibold">Required</label>
            </div>

            <div class="mb-2">
                <label class="form-label">Question Code</label>
                <input type="text" class="form-control" name="booking_questions[${index}][questionCode]" value="${data.questionCode||''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Question Label</label>
                <textarea class="form-control" rows="2" name="booking_questions[${index}][label]">${data.label||''}</textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Help Text</label>
                <textarea class="form-control" rows="2" name="booking_questions[${index}][help]">${data.help||''}</textarea>
            </div>

            <div class="mb-2">
                <label class="form-label">Placeholder</label>
                <input type="text" class="form-control" name="booking_questions[${index}][placeholder]" value="${data.placeholder||''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Data Type</label>
                <select class="form-control" name="booking_questions[${index}][dataType]">
                    <option value="">Select</option>
                    <option value="string" ${data.dataType==='string'?'selected':''}>Text</option>
                    <option value="number" ${data.dataType==='number'?'selected':''}>Number</option>
                    <option value="date" ${data.dataType==='date'?'selected':''}>Date</option>
                    <option value="email" ${data.dataType==='email'?'selected':''}>Email</option>
                    <option value="phone" ${data.dataType==='phone'?'selected':''}>Phone</option>
                    <option value="boolean" ${data.dataType==='boolean'?'selected':''}>Yes / No</option>
                </select>
            </div>

            <div class="row g-3 mb-2">
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="hidden" name="booking_questions[${index}][selectFromOptions]" value="0">
                        <input class="form-check-input select-from-options" type="checkbox" value="1" name="booking_questions[${index}][selectFromOptions]" ${data.selectFromOptions?'checked':''}>
                        <label class="form-check-label fw-semibold">Select from Options</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-check">
                        <input type="hidden" name="booking_questions[${index}][selectMultiple]" value="0">
                        <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${index}][selectMultiple]" ${data.selectMultiple?'checked':''}>
                        <label class="form-check-label fw-semibold">Allow Multiple Selection</label>
                    </div>
                </div>
            </div>

            <div class="options-section mb-2" style="display:${data.selectFromOptions?'block':'none'}">
                <h6 class="fw-semibold text-primary mb-1">Options (Key:Value)</h6>
                <div class="row g-2 mb-1">
                    <div class="col-md-5"><input type="text" class="form-control option-key" placeholder="Key"></div>
                    <div class="col-md-5"><input type="text" class="form-control option-value" placeholder="Value"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100 add-option">Add</button></div>
                </div>
                <ul class="list-group mb-2 options-list"></ul>
                <input type="hidden" class="options-json" name="booking_questions[${index}][options]" value='${JSON.stringify(data.options||{})}'>
            </div>

            <h6 class="fw-semibold text-primary mt-2 mb-1">Flags (Key:Value)</h6>
            <div class="row g-2 mb-1">
                <div class="col-md-5"><input type="text" class="form-control flag-key" placeholder="Key"></div>
                <div class="col-md-5"><input type="text" class="form-control flag-value" placeholder="Value"></div>
                <div class="col-md-2"><button type="button" class="btn btn-outline-primary w-100 add-flag">Add</button></div>
            </div>
            <ul class="list-group mb-2 flags-list"></ul>
            <input type="hidden" class="flags-json" name="booking_questions[${index}][flags]" value='${JSON.stringify(data.flags||{})}'>

            <div class="mb-2">
                <label class="form-label">Context</label>
                <input type="text" class="form-control" name="booking_questions[${index}][context]" value="${data.context||''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Pricing Category Trigger</label>
                <input type="text" class="form-control" name="booking_questions[${index}][pricingCategoryTriggerSelection]" value="${data.pricingCategoryTriggerSelection||''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Rate Trigger</label>
                <input type="text" class="form-control" name="booking_questions[${index}][rateTriggerSelection]" value="${data.rateTriggerSelection||''}">
            </div>

            <div class="mb-2">
                <label class="form-label">Extra Trigger</label>
                <input type="text" class="form-control" name="booking_questions[${index}][extraTriggerSelection]" value="${data.extraTriggerSelection||''}">
            </div>

            <div class="form-check mt-2">
                <input type="hidden" name="booking_questions[${index}][requiredBeforeDeparture]" value="0">
                <input class="form-check-input" type="checkbox" value="1" name="booking_questions[${index}][requiredBeforeDeparture]" ${data.requiredBeforeDeparture?'checked':''}>
                <label class="form-check-label fw-semibold">Required Before Departure</label>
            </div>

            <button type="button" class="btn btn-danger btn-sm mt-2 remove-question">Remove Question</button>
        `;

        block.innerHTML = html;

        // Add dynamic logic
        const optionsCheckbox = block.querySelector('.select-from-options');
        const optionsSection = block.querySelector('.options-section');
        optionsCheckbox.addEventListener('change', ()=> optionsSection.style.display = optionsCheckbox.checked?'block':'none');

        let options = data.options||{};
        const optionsJson = block.querySelector('.options-json');
        const optionsList = block.querySelector('.options-list');
        const renderOptions = ()=>{
            optionsList.innerHTML='';
            Object.entries(options).forEach(([k,v])=>{
                const li = document.createElement('li'); li.className='list-group-item d-flex align-items-center';
                const ks=document.createElement('span'); ks.contentEditable=true; ks.innerText=k; ks.style.width='40%';
                const vs=document.createElement('span'); vs.contentEditable=true; vs.innerText=v; vs.className='ms-2 flex-grow-1';
                ks.onblur=()=>{ const nk=ks.innerText.trim(); if(nk&&nk!==k){ options[nk]=options[k]; delete options[k]; renderOptions(); }};
                vs.onblur=()=>{ options[ks.innerText.trim()]=vs.innerText.trim(); renderOptions(); };
                const rm=document.createElement('button'); rm.className='btn btn-danger btn-sm ms-2'; rm.innerText='Remove'; rm.onclick=()=>{ delete options[k]; renderOptions(); };
                li.append(ks,vs,rm); optionsList.appendChild(li);
            });
            optionsJson.value = JSON.stringify(options);
        };
        renderOptions();
        block.querySelector('.add-option').onclick=e=>{
            e.preventDefault();
            const k=block.querySelector('.option-key').value.trim();
            const v=block.querySelector('.option-value').value.trim();
            if(k&&v){ options[k]=v; block.querySelector('.option-key').value=''; block.querySelector('.option-value').value=''; renderOptions();}
        };

        // Flags
        let flags = data.flags||{};
        const flagsJson = block.querySelector('.flags-json');
        const flagsList = block.querySelector('.flags-list');
        const renderFlags = ()=>{
            flagsList.innerHTML='';
            Object.entries(flags).forEach(([k,v])=>{
                const li=document.createElement('li'); li.className='list-group-item d-flex align-items-center';
                const ks=document.createElement('span'); ks.contentEditable=true; ks.innerText=k; ks.style.width='40%';
                const vs=document.createElement('span'); vs.contentEditable=true; vs.innerText=v; vs.className='ms-2 flex-grow-1';
                ks.onblur=()=>{ const nk=ks.innerText.trim(); if(nk&&nk!==k){ flags[nk]=flags[k]; delete flags[k]; renderFlags(); }};
                vs.onblur=()=>{ flags[ks.innerText.trim()]=vs.innerText.trim(); renderFlags(); };
                const rm=document.createElement('button'); rm.className='btn btn-danger btn-sm ms-2'; rm.innerText='Remove'; rm.onclick=()=>{ delete flags[k]; renderFlags(); };
                li.append(ks,vs,rm); flagsList.appendChild(li);
            });
            flagsJson.value = JSON.stringify(flags);
        };
        renderFlags();
        block.querySelector('.add-flag').onclick=e=>{
            e.preventDefault();
            const k=block.querySelector('.flag-key').value.trim();
            const v=block.querySelector('.flag-value').value.trim();
            if(k&&v){ flags[k]=v; block.querySelector('.flag-key').value=''; block.querySelector('.flag-value').value=''; renderFlags();}
        };

        // Remove button
        block.querySelector('.remove-question').onclick = e=>{
            block.remove();
            updateIndexes();
        };

        return block;
    };

    const updateIndexes = ()=>{
        const blocks = container.querySelectorAll('.question-block');
        blocks.forEach((b,i)=>{
            b.dataset.index=i;
            const inputs = b.querySelectorAll('[name]');
            inputs.forEach(input=>{
                input.name = input.name.replace(/\d+/, i);
            });
            b.querySelector('h5').innerText = `Question ${i+1}`;
        });
    };

    // Add new question
    document.getElementById('add_question_create').onclick = e=>{
        e.preventDefault();
        const index = container.querySelectorAll('.question-block').length;
        container.appendChild(createQuestionBlock(index));
    };
})();
</script>
