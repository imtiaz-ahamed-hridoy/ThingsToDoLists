{{-- =========================
     HOTEL KEYWORDS - Edit Form (FINAL)
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Hotel Keywords</h4>
        <small class="text-muted">Keywords assigned to this product.</small>
    </div>

    <div class="card-body">
        <div class="row g-2 align-items-end mb-3">
            <div class="col-md-8">
                <label class="form-label">Keyword</label>
                <select class="form-select" id="hotel_keyword_select">
                    <option value="">-- Select keyword --</option>
                    @foreach($allKeywords as $kw)
                        <option value="{{ $kw->name }}">{{ $kw->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <button type="button" class="btn btn-primary w-100" id="add_hotel_keyword">
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
                <tbody id="hotel_keywords_table"></tbody>
            </table>
        </div>

        <input type="hidden"
               name="hotel_keywords"
               id="hotel_keywords_json"
               value='@json($hotelKeywords)'>
    </div>
</div>

<script>
(function () {
    'use strict';

    const hotelSelect = document.getElementById('hotel_keyword_select');
    const addBtn = document.getElementById('add_hotel_keyword');
    const table = document.getElementById('hotel_keywords_table');
    const hidden = document.getElementById('hotel_keywords_json');

    let hotelKeywords = JSON.parse(hidden.value || '[]');
    let masterKeywords = [];

    const escapeHtml = t =>
        t.replace(/[&<>"']/g, m =>
            ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])
        );

    const syncHidden = () => {
        hidden.value = JSON.stringify(hotelKeywords);
    };

    const render = () => {
        table.innerHTML = '';

        if (!hotelKeywords.length) {
            table.innerHTML =
                `<tr class="text-center text-muted">
                    <td colspan="2">No keywords added yet</td>
                 </tr>`;
            syncHidden();
            return;
        }

        hotelKeywords.forEach((kw, i) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${escapeHtml(kw)}</td>
                <td class="text-center">
                    <button type="button"
                            class="btn btn-danger btn-sm"
                            data-index="${i}">
                        Remove
                    </button>
                </td>
            `;
            tr.querySelector('button').addEventListener('click', () => {
                hotelKeywords.splice(i, 1);
                render();
            });
            table.appendChild(tr);
        });

        syncHidden();
    };

    const addKeyword = () => {
        const value = hotelSelect.value.trim();

        if (!value) {
            alert('Please select a keyword.');
            return;
        }

        if (hotelKeywords.includes(value)) {
            alert('Keyword already added.');
            return;
        }

        hotelKeywords.push(value);
        hotelSelect.value = '';
        render();
    };

    addBtn.addEventListener('click', addKeyword);

    // 🔁 Sync with Master Keywords Component
    window.addEventListener('masterKeywordsUpdated', e => {
        masterKeywords = e.detail.keywords;

        // Remove orphaned hotel keywords
        hotelKeywords = hotelKeywords.filter(k => masterKeywords.includes(k));
        render();
    });

    window.addEventListener('masterKeywordRemoved', e => {
        hotelKeywords = hotelKeywords.filter(k => k !== e.detail.keyword);
        render();
    });

    // Initial render (EDIT MODE)
    render();
})();
</script>
