{{-- =========================
     Passenger Fields
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Passenger Information Requirements</h4>
        <small class="text-muted">Define what passenger details are required for booking and departure</small>
    </div>

    <div class="card-body">

        {{-- Add Passenger Field --}}
        <div class="row g-2 align-items-center mb-4">
            <div class="col-12 col-md-5 mb-2 mb-md-0">
                <label class="form-label mb-1">Passenger Field</label>
                <select id="passenger_field_select" class="form-select">
                    <option value="">Select Field</option>
                    <option value="first_name">First Name</option>
                    <option value="last_name">Last Name</option>
                    <option value="full_name">Full Name</option>
                    <option value="date_of_birth">Date of Birth</option>
                    <option value="gender">Gender</option>
                    <option value="nationality">Nationality</option>
                    <option value="passport_number">Passport Number</option>
                    <option value="passport_expiry">Passport Expiry Date</option>
                </select>
            </div>

            <div class="col-12 col-md-5 mb-2 mb-md-0">
                <div class="d-flex flex-wrap gap-3 align-items-center pt-md-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pf_required">
                        <label class="form-check-label fw-semibold">Required</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="pf_required_before">
                        <label class="form-check-label fw-semibold">Before Departure</label>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-2 text-start text-md-end">
                <button type="button" class="btn btn-primary mt-2 mt-md-4 w-100 w-md-auto" id="add_passenger_field">Add</button>
            </div>
        </div>

        {{-- Passenger Fields List --}}
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
                <tbody id="passenger_fields_table">
                    {{-- JS Injected --}}
                </tbody>
            </table>
        </div>

        {{-- Hidden Payload --}}
        <input type="hidden" name="passenger_fields" id="passenger_fields_payload"
       value='{!! old("passenger_fields", json_encode($passenger_fields ?? [])) !!}'>


    </div>
</div>

{{-- Production-Ready JS --}}
<script>
(() => {
    const tableBody = document.getElementById('passenger_fields_table');
    const fieldSelect = document.getElementById('passenger_field_select');
    const requiredCheckbox = document.getElementById('pf_required');
    const requiredBeforeCheckbox = document.getElementById('pf_required_before');
    const addBtn = document.getElementById('add_passenger_field');
    const hiddenInput = document.getElementById('passenger_fields_payload');

    let passengerFields = JSON.parse(hiddenInput.value || '[]');

    // Render Table
    const renderTable = () => {
        tableBody.innerHTML = '';
        passengerFields.forEach((pf, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${pf.field}</td>
                <td class="text-center">${pf.required ? 'Yes' : 'No'}</td>
                <td class="text-center">${pf.requiredBeforeDeparture ? 'Yes' : 'No'}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-danger btn-sm remove-passenger-field">Remove</button>
                </td>
            `;
            tr.querySelector('.remove-passenger-field').onclick = () => {
                passengerFields.splice(index, 1);
                updatePayload();
            };
            tableBody.appendChild(tr);
        });
    };

    // Update Hidden Payload
    const updatePayload = () => {
        hiddenInput.value = JSON.stringify(passengerFields);
        renderTable();
    };

    // Add Field
    addBtn.addEventListener('click', () => {
        const field = fieldSelect.value;
        if(!field) { alert('Please select a passenger field.'); return; }

        // Prevent duplicates
        if(passengerFields.some(f => f.field === field)){
            alert('This field has already been added.');
            return;
        }

        passengerFields.push({
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
