{{-- =========================
     MASTER KEYWORDS - COMPLETE FILE
     File: resources/views/components/master-keywords.blade.php
     Copy and paste this entire file - includes HTML, CSS, and JavaScript
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
                <input type="text" class="form-control mt-2" id="master_keyword_input" 
                       placeholder="Or type new keyword here">
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
                        <th class="text-center" style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody id="master_keywords_table">
                    <tr class="text-center text-muted">
                        <td colspan="2">No keywords added yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="keywords" id="master_keywords_json" 
               value='{{ old("keywords", json_encode($keywords ?? [])) }}'>
    </div>
</div>

{{-- CSS Styles --}}
<style>
.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.card-header h4 {
    margin-bottom: 0;
}
</style>

{{-- JavaScript --}}
<script>
(function() {
    'use strict';

    // ==================== DOM Elements ====================
    const masterSelect = document.getElementById('master_keyword_select');
    const masterInput = document.getElementById('master_keyword_input');
    const masterAddBtn = document.getElementById('add_master_keyword');
    const masterTable = document.getElementById('master_keywords_table');
    const masterHidden = document.getElementById('master_keywords_json');

    // Check if elements exist
    if (!masterTable || !masterSelect || !masterInput || !masterAddBtn || !masterHidden) {
        console.warn('Master Keywords: Required elements not found');
        return;
    }

    // ==================== State Management ====================
    let masterKeywords = [];

    // Initialize from hidden input (for edit mode or validation errors)
    try {
        masterKeywords = JSON.parse(masterHidden.value || '[]');
    } catch (e) {
        console.error('Error parsing master keywords:', e);
        masterKeywords = [];
    }

    // ==================== Functions ====================

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Render master keywords table
     */
    function renderMasterTable() {
        if (masterKeywords.length === 0) {
            masterTable.innerHTML = `
                <tr class="text-center text-muted">
                    <td colspan="2">No keywords added yet</td>
                </tr>
            `;
        } else {
            masterTable.innerHTML = '';
            masterKeywords.forEach((kw, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(kw)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-master-keyword" data-index="${index}">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </td>
                `;
                masterTable.appendChild(tr);
            });

            // Attach remove event listeners
            document.querySelectorAll('.remove-master-keyword').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeMasterKeyword(parseInt(this.dataset.index));
                });
            });
        }
        
        // Update hidden input
        masterHidden.value = JSON.stringify(masterKeywords);
        
        // Trigger custom event for hotel keywords to listen
        window.dispatchEvent(new CustomEvent('masterKeywordsUpdated', { 
            detail: { keywords: masterKeywords } 
        }));
    }

    /**
     * Add master keyword
     */
    function addMasterKeyword() {
        // Check if selecting from dropdown or typing new
        let value = masterSelect.value.trim();
        
        // If no selection, check custom input
        if (!value) {
            value = masterInput.value.trim();
        }
        
        if (!value) {
            alert('Please select an existing keyword or type a new one.');
            return;
        }
        
        if (masterKeywords.includes(value)) {
            alert('This keyword has already been added.');
            return;
        }
        
        masterKeywords.push(value);
        masterSelect.value = '';
        masterInput.value = '';
        
        renderMasterTable();
    }

    /**
     * Remove master keyword
     */
    function removeMasterKeyword(index) {
        const removedKeyword = masterKeywords[index];
        
        // Remove from master keywords
        masterKeywords.splice(index, 1);
        
        renderMasterTable();
        
        // Notify hotel keywords about removal
        window.dispatchEvent(new CustomEvent('masterKeywordRemoved', { 
            detail: { keyword: removedKeyword } 
        }));
    }

    // ==================== Event Listeners ====================

    masterAddBtn.addEventListener('click', addMasterKeyword);

    masterInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addMasterKeyword();
        }
    });

    // ==================== Initialize ====================
    renderMasterTable();

    

})();
</script>