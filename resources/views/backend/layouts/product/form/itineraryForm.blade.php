{{-- =========================
     Itinerary Form (Bokun-Compatible)
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="fw-semibold text-primary mb-1">Itinerary</h4>
        <small class="text-muted">Add daily itinerary details (Day 1, Day 2, etc.)</small>
    </div>

    <div class="card-body">
        <div id="itineraryContainer"></div>
        
        <button type="button" class="btn btn-success btn-sm mt-2" onclick="addItineraryDay()">
            <i class="fe fe-plus"></i> Add Day
        </button>

        @php
            $itineraryValue = old('itinerary', isset($product) && $product->itinerary ? json_encode($product->itinerary) : '[]');
            if (is_array($itineraryValue)) {
                $itineraryValue = json_encode($itineraryValue);
            }
        @endphp

        {{-- Hidden input to store the JSON structure --}}
        <input type="hidden" name="itinerary" id="itineraryJson" 
               value="{{ $itineraryValue }}">
        @error('itinerary')<span class="text-danger d-block mt-2">{{ $message }}</span>@enderror
    </div>
</div>

@push('scripts')
<script>
window.itineraryInitialized = window.itineraryInitialized || false;

function escapeItineraryValue(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[char]);
}

function updateItineraryJSON() {
    const container = document.getElementById('itineraryContainer');
    const hiddenInput = document.getElementById('itineraryJson');
    if (!container || !hiddenInput) return;

    const items = [];
    container.querySelectorAll('.itinerary-day').forEach((row, index) => {
        const title = row.querySelector('.day-title').value.trim();
        const description = row.querySelector('.day-description').value.trim();
        const location = row.querySelector('.day-location').value.trim();

        if (title || description || location) {
            items.push({
                title: title || `Day ${index + 1}`,
                description: description,
                location: location
            });
        }
    });
    hiddenInput.value = JSON.stringify(items);
}

function addItineraryDay(data = {}) {
    const container = document.getElementById('itineraryContainer');
    if (!container) return;

    const dayCount = container.querySelectorAll('.itinerary-day').length + 1;
    const title = data.title || `Day ${dayCount}`;
    const description = data.description || '';
    const location = data.location || '';

    const div = document.createElement('div');
    div.classList.add('card', 'mb-3', 'border', 'itinerary-day');
    div.innerHTML = `
        <div class="card-body p-3 bg-light">
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-bold">Step ${dayCount}</span>
                <button type="button" class="btn btn-danger btn-sm py-0" onclick="this.closest('.itinerary-day').remove(); updateItineraryJSON();">Remove</button>
            </div>
            <div class="row g-2">
                <div class="col-md-4">
                    <label class="form-label small">Title</label>
                    <input type="text" class="form-control form-control-sm day-title" 
                           placeholder="e.g., Arrival in Paris" value="${escapeItineraryValue(title)}" oninput="updateItineraryJSON()">
                </div>
                <div class="col-md-4">
                    <label class="form-label small">Location</label>
                    <input type="text" class="form-control form-control-sm day-location" 
                           placeholder="e.g., Eiffel Tower" value="${escapeItineraryValue(location)}" oninput="updateItineraryJSON()">
                </div>
                <div class="col-12">
                    <label class="form-label small">Description</label>
                    <textarea class="form-control form-control-sm day-description" rows="2" 
                              placeholder="Describe activities for this day..." oninput="updateItineraryJSON()">${escapeItineraryValue(description)}</textarea>
                </div>
            </div>
        </div>
    `;
    container.appendChild(div);
    updateItineraryJSON();
}

function initItinerary() {
    if (window.itineraryInitialized) return;
    window.itineraryInitialized = true;

    const hiddenInput = document.getElementById('itineraryJson');
    if (!hiddenInput) return;

    try {
        const existing = JSON.parse(hiddenInput.value);
        if (Array.isArray(existing) && existing.length > 0) {
            existing.forEach(item => addItineraryDay(item));
        } else {
            // Check if we should add a default day (optional)
             // addItineraryDay(); 
        }
    } catch (e) {
        console.error('Error parsing itinerary JSON', e);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initItinerary);
} else {
    initItinerary();
}
</script>
@endpush
