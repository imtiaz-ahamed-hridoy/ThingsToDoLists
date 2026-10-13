{{-- =========================
     Passenger Fields - Edit Form (Production Ready)
========================== --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="mb-1 text-primary fw-semibold">Passenger Information Requirements</h4>
        <small class="text-muted">Define what passenger details are required for booking and before departure</small>
    </div>

    <div class="card-body">

        {{-- Add Passenger Field --}}
        <div class="row g-2 align-items-end mb-4">
            <div class="col-md-5">
                <label class="form-label">Passenger Field</label>
                <select id="passenger_field_select" class="form-control">
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

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="pf_required">
                    <label class="form-check-label fw-semibold">Required</label>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="pf_required_before">
                    <label class="form-check-label fw-semibold">Required Before Departure</label>
                </div>
            </div>

            <div class="col-md-1">
                <button type="button" class="btn btn-primary mt-4" id="add_passenger_field">Add</button>
            </div>
        </div>

        {{-- Passenger Fields Table --}}
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
            value='{{ old("passenger_fields", json_encode($product->passengerFields ?? [])) }}'>
    </div>
</div>

{{-- =========================
     Production-Ready JS for Edit Form
========================== --}}
<script>
(() => {
    const tableBody = document.getElementById('passenger_fields_table');
    const fieldSelect = document.getElementById('passenger_field_select');
    const requiredCheckbox = document.getElementById('pf_required');
    const requiredBeforeCheckbox = document.getElementById('pf_required_before');
    const addBtn = document.getElementById('add_passenger_field');
    const hiddenInput = document.getElementById('passenger_fields_payload');

    // Load existing fields (from old input or DB)
    let passengerFields = JSON.parse(hiddenInput.value || '[]');

    const renderTable = () => {
        tableBody.innerHTML = '';
        if(passengerFields.length === 0){
            tableBody.innerHTML = `<tr class="text-center text-muted"><td colspan="4">No passenger fields added yet</td></tr>`;
            return;
        }
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

    const updatePayload = () => {
        hiddenInput.value = JSON.stringify(passengerFields);
        renderTable();
    };

    addBtn.addEventListener('click', () => {
        const field = fieldSelect.value;
        if(!field){ alert('Please select a passenger field.'); return; }

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

    // Initialize table on load
    renderTable();
})();
</script>
