{{-- =========================
     Hotel Contact Fields
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Hotel Contact Information Requirements</h4>
        <small class="text-muted">Specify which hotel contact details are mandatory for booking and before departure</small>
    </div>

    <div class="card-body">

        {{-- Add Field --}}
        <div class="row g-2 align-items-end mb-4">
            <div class="col-md-5">
                <label class="form-label fw-semibold">Hotel Contact Field</label>
                <select id="hotel_field_select" class="form-control">
                    <option value="">Select Field</option>
                    <option value="hotel_name">Hotel Name</option>
                    <option value="hotel_address">Hotel Address</option>
                    <option value="hotel_city">Hotel City</option>
                    <option value="hotel_country">Hotel Country</option>
                    <option value="contact_person_name">Contact Person Name</option>
                    <option value="contact_phone">Contact Phone</option>
                    <option value="contact_email">Contact Email</option>
                    <option value="room_number">Room Number</option>
                </select>
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="hotel_required">
                    <label class="form-check-label fw-semibold">Required</label>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="hotel_required_before">
                    <label class="form-check-label fw-semibold">Required Before Departure</label>
                </div>
            </div>

            <div class="col-md-1">
                <button type="button" class="btn btn-primary mt-4" id="add_hotel_field">Add</button>
            </div>
        </div>

        {{-- Fields Table --}}
        <div class="table-responsive">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Field</th>
                        <th class="text-center">Required</th>
                        <th class="text-center">Required Before Departure</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody id="hotel_fields_table">
                    {{-- JS Injected --}}
                </tbody>
            </table>
        </div>

        {{-- Hidden Payload --}}
        <input type="hidden" name="hotel_contact_fields" id="hotel_fields_payload" value='{{ old("hotel_contact_fields", json_encode($hotel_contact_fields ?? [])) }}'>

    </div>
</div>

{{-- Production-Ready JS --}}
<script>
(() => {
    const tableBody = document.getElementById('hotel_fields_table');
    const fieldSelect = document.getElementById('hotel_field_select');
    const requiredCheckbox = document.getElementById('hotel_required');
    const requiredBeforeCheckbox = document.getElementById('hotel_required_before');
    const addBtn = document.getElementById('add_hotel_field');
    const hiddenInput = document.getElementById('hotel_fields_payload');

    let hotelFields = JSON.parse(hiddenInput.value || '[]');

    // Render Table
    const renderTable = () => {
        tableBody.innerHTML = '';
        hotelFields.forEach((hf, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${hf.field}</td>
                <td class="text-center">${hf.required ? 'Yes' : 'No'}</td>
                <td class="text-center">${hf.requiredBeforeDeparture ? 'Yes' : 'No'}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-hotel-field">Remove</button>
                </td>
            `;
            tr.querySelector('.remove-hotel-field').onclick = () => {
                hotelFields.splice(index, 1);
                updatePayload();
            };
            tableBody.appendChild(tr);
        });
    };

    // Update Hidden Payload
    const updatePayload = () => {
        hiddenInput.value = JSON.stringify(hotelFields);
        renderTable();
    };

    // Add Field
    addBtn.addEventListener('click', () => {
        const field = fieldSelect.value;
        if(!field) { alert('Please select a hotel contact field.'); return; }

        // Prevent duplicates
        if(hotelFields.some(f => f.field === field)){
            alert('This field has already been added.');
            return;
        }

        hotelFields.push({
            field: field,
            required: requiredCheckbox.checked,
            requiredBeforeDeparture: requiredBeforeCheckbox.checked
        });

        // Reset inputs
        fieldSelect.value = '';
        requiredCheckbox.checked = false;
        requiredBeforeCheckbox.checked = false;

        updatePayload();
    });

    // Initial render
    renderTable();
})();
</script>
