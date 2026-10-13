{{-- =========================
     HOTEL KEYWORDS - COMPLETE FILE
     File: resources/views/components/hotel-keywords.blade.php
     Copy and paste this entire file - includes HTML, CSS, and JavaScript
========================== --}}

<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Hotel Keywords</h4>
        <small class="text-muted">Select keywords for this hotel (includes newly created master keywords).</small>
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

        <div class="alert alert-info" id="hotel_empty_alert" style="display: none;">
            <i class="fas fa-info-circle me-2"></i>
            Please add master keywords first to select them for this hotel.
        </div>

        <div class="table-responsive" id="hotel_table_wrapper">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Keyword</th>
                        <th class="text-center" style="width: 120px;">Action</th>
                    </tr>
                </thead>
                <tbody id="hotel_keywords_table">
                    <tr class="text-center text-muted">
                        <td colspan="2">No keywords selected yet</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <input type="hidden" name="hotel_keywords" id="hotel_keywords_json" 
               value='{{ old("hotel_keywords", json_encode($hotel_keywords ?? [])) }}'>
    </div>
</div>

{{-- CSS Styles --}}
<style>
.alert {
    margin-bottom: 1rem;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

.btn-sm {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}
</style>

{{-- JavaScript --}}
<script>
(function() {
    'use strict';

    // ==================== DOM Elements ====================
    const hotelSelect = document.getElementById('hotel_keyword_select');
    const hotelAddBtn = document.getElementById('add_hotel_keyword');
    const hotelTable = document.getElementById('hotel_keywords_table');
    const hotelHidden = document.getElementById('hotel_keywords_json');
    const hotelEmptyAlert = document.getElementById('hotel_empty_alert');
    const hotelTableWrapper = document.getElementById('hotel_table_wrapper');

    // Check if elements exist
    if (!hotelTable || !hotelSelect || !hotelAddBtn || !hotelHidden) {
        console.warn('Hotel Keywords: Required elements not found');
        return;
    }

    // ==================== State Management ====================
    let hotelKeywords = [];
    let masterKeywords = [];
    
    // Store initial keywords from blade (existing keywords from database)
    let existingKeywords = [];
    const initialOptions = hotelSelect.querySelectorAll('option:not([value=""])');
    initialOptions.forEach(option => {
        existingKeywords.push(option.value);
    });

    // Initialize from hidden input (for edit mode or validation errors)
    try {
        hotelKeywords = JSON.parse(hotelHidden.value || '[]');
    } catch (e) {
        console.error('Error parsing hotel keywords:', e);
        hotelKeywords = [];
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
     * Get all available keywords (existing + master)
     */
    function getAllAvailableKeywords() {
        // Combine existing keywords and newly added master keywords
        const allKeywords = [...existingKeywords];
        
        // Add master keywords that aren't already in the list
        masterKeywords.forEach(kw => {
            if (!allKeywords.includes(kw)) {
                allKeywords.push(kw);
            }
        });
        
        return allKeywords;
    }

    /**
     * Update hotel keywords select dropdown
     */
    function updateHotelSelect() {
        // Store current selection
        const currentValue = hotelSelect.value;
        
        // Clear and reset
        hotelSelect.innerHTML = '<option value="">-- Select keyword --</option>';
        
        // Get all available keywords
        const allAvailableKeywords = getAllAvailableKeywords();
        
        if (allAvailableKeywords.length === 0) {
            if (hotelEmptyAlert) hotelEmptyAlert.style.display = 'block';
            if (hotelTableWrapper) hotelTableWrapper.style.display = 'none';
            hotelSelect.disabled = true;
        } else {
            if (hotelEmptyAlert) hotelEmptyAlert.style.display = 'none';
            if (hotelTableWrapper) hotelTableWrapper.style.display = 'block';
            hotelSelect.disabled = false;
            
            // Populate select with all available keywords
            allAvailableKeywords.forEach(kw => {
                const option = document.createElement('option');
                option.value = kw;
                option.textContent = kw;
                hotelSelect.appendChild(option);
            });

            // Restore previous selection if still available
            if (currentValue && allAvailableKeywords.includes(currentValue)) {
                hotelSelect.value = currentValue;
            }
        }
    }

    /**
     * Render hotel keywords table
     */
    function renderHotelTable() {
        if (hotelKeywords.length === 0) {
            hotelTable.innerHTML = `
                <tr class="text-center text-muted">
                    <td colspan="2">No keywords selected yet</td>
                </tr>
            `;
        } else {
            hotelTable.innerHTML = '';
            hotelKeywords.forEach((kw, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(kw)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm remove-hotel-keyword" data-index="${index}">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </td>
                `;
                hotelTable.appendChild(tr);
            });

            // Attach remove event listeners
            document.querySelectorAll('.remove-hotel-keyword').forEach(btn => {
                btn.addEventListener('click', function() {
                    removeHotelKeyword(parseInt(this.dataset.index));
                });
            });
        }
        
        // Update hidden input
        hotelHidden.value = JSON.stringify(hotelKeywords);
    }

    /**
     * Add hotel keyword
     */
    function addHotelKeyword() {
        const value = hotelSelect.value.trim();
        
        if (!value) {
            alert('Please select a keyword.');
            return;
        }

        // Check if the keyword is available (either existing or from master)
        const allAvailableKeywords = getAllAvailableKeywords();
        if (!allAvailableKeywords.includes(value)) {
            alert('This keyword must be added to Master Keywords first.');
            return;
        }
        
        if (hotelKeywords.includes(value)) {
            alert('This keyword has already been added to hotel keywords.');
            return;
        }
        
        hotelKeywords.push(value);
        hotelSelect.value = '';
        
        renderHotelTable();
    }

    /**
     * Remove hotel keyword
     */
    function removeHotelKeyword(index) {
        hotelKeywords.splice(index, 1);
        renderHotelTable();
    }

    // ==================== Event Listeners ====================

    hotelAddBtn.addEventListener('click', addHotelKeyword);

    // Listen for master keywords updates
    window.addEventListener('masterKeywordsUpdated', function(e) {
        masterKeywords = e.detail.keywords;
        updateHotelSelect();
    });

    // Listen for master keyword removal
    window.addEventListener('masterKeywordRemoved', function(e) {
        const removedKeyword = e.detail.keyword;
        
        // Remove from hotel keywords if present
        const index = hotelKeywords.indexOf(removedKeyword);
        if (index > -1) {
            hotelKeywords.splice(index, 1);
            renderHotelTable();
        }
        
        // Update select dropdown
        updateHotelSelect();
    });

    // ==================== Initialize ====================
    
    // Try to get initial master keywords from master component
    setTimeout(function() {
        const masterHiddenInput = document.getElementById('master_keywords_json');
        if (masterHiddenInput) {
            try {
                masterKeywords = JSON.parse(masterHiddenInput.value || '[]');
            } catch (e) {
                console.error('Error parsing initial master keywords:', e);
            }
        }
        
        updateHotelSelect();
        renderHotelTable();
        
    
    }, 100);

})();
</script>