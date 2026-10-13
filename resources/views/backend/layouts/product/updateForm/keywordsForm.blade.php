{{-- =========================
     MASTER KEYWORDS - Component
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Master Keywords</h4>
        <small class="text-muted">Select existing keywords or create new ones.</small>
    </div>

    <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-8">
                <label class="form-label">Keyword</label>
                <select class="form-select" id="master_keyword_select">
                    <option value="">-- Select existing keyword --</option>
                    @foreach($allKeywords as $kw)
                        <option value="{{ $kw->name }}">{{ $kw->name }}</option>
                    @endforeach
                </select>
                <input type="text" class="form-control mt-2" id="master_keyword_input" placeholder="Or type new keyword here">
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" id="add_master_keyword">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Keyword</th>
                        <th class="text-center" style="width:120px;">Action</th>
                    </tr>
                </thead>
                <tbody id="master_keywords_table">
                    <tr class="text-center text-muted">
                        <td colspan="2">No keywords added yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="keywords" id="master_keywords_json" value='@json($masterKeywords )'>

    </div>
</div>

<script>
(function(){
    'use strict';
    const masterSelect = document.getElementById('master_keyword_select');
    const masterInput = document.getElementById('master_keyword_input');
    const masterAddBtn = document.getElementById('add_master_keyword');
    const masterTable = document.getElementById('master_keywords_table');
    const masterHidden = document.getElementById('master_keywords_json');

    let masterKeywords = JSON.parse(masterHidden.value || '[]');

    const escapeHtml = text => text.replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

    const renderMasterTable = () => {
        if(masterKeywords.length===0){
            masterTable.innerHTML=`<tr class="text-center text-muted"><td colspan="2">No keywords added yet</td></tr>`;
        } else {
            masterTable.innerHTML='';
            masterKeywords.forEach((kw,index)=>{
                const tr=document.createElement('tr');
                tr.innerHTML=`<td>${escapeHtml(kw)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-master-keyword" data-index="${index}">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </td>`;
                masterTable.appendChild(tr);
            });
            document.querySelectorAll('.remove-master-keyword').forEach(btn=>{
                btn.addEventListener('click',function(){
                    const idx=parseInt(this.dataset.index);
                    const removed=masterKeywords[idx];
                    masterKeywords.splice(idx,1);
                    masterHidden.value=JSON.stringify(masterKeywords);
                    renderMasterTable();
                    window.dispatchEvent(new CustomEvent('masterKeywordRemoved',{detail:{keyword:removed}}));
                });
            });
        }
        masterHidden.value=JSON.stringify(masterKeywords);
        window.dispatchEvent(new CustomEvent('masterKeywordsUpdated',{detail:{keywords:masterKeywords}}));
    };

    const addMasterKeyword = () => {
        const value=masterSelect.value.trim()||masterInput.value.trim();
        if(!value){ alert('Please select or type a keyword.'); return; }
        if(masterKeywords.includes(value)){ alert('This keyword has already been added.'); return; }
        masterKeywords.push(value);
        masterSelect.value='';
        masterInput.value='';
        renderMasterTable();
    };

    masterAddBtn.addEventListener('click',addMasterKeyword);
    masterInput.addEventListener('keypress',e=>{if(e.key==='Enter'){ e.preventDefault(); addMasterKeyword(); }});

    renderMasterTable();
})();
</script>
