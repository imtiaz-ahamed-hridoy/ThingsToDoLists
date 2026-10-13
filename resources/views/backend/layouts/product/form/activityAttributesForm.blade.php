{{-- =========================
     Activity Attributes Form
========================== --}}
@php
    $activityListFields = [
        'activityAttributes' => [
            'label' => 'Activity Attributes',
            'help' => 'Add tags and attributes for this activity (e.g., Family Friendly, Outdoor, Adventure)',
            'placeholder' => 'e.g., Family Friendly, Outdoor',
        ],
        'highlights' => [
            'label' => 'Highlights',
            'help' => 'Add the main selling points shown on the product page.',
            'placeholder' => 'Enter highlight',
        ],
        'inclusions' => [
            'label' => 'Inclusions',
            'help' => 'Add what is included with this activity.',
            'placeholder' => 'Enter inclusion',
        ],
        'exclusions' => [
            'label' => 'Exclusions',
            'help' => 'Add what is not included with this activity.',
            'placeholder' => 'Enter exclusion',
        ],
    ];

    $activityProduct = $product ?? null;

    $normalizeActivityList = function (string $field) use ($activityProduct) {
        $items = old($field, $activityProduct ? ($activityProduct->{$field} ?? []) : []);

        if (is_string($items)) {
            $items = json_decode($items, true) ?: [];
        }

        if (! is_array($items)) {
            $items = [];
        }

        return array_values(array_filter($items, function ($item) {
            return is_scalar($item) && trim((string) $item) !== '';
        }));
    };
@endphp

<div class="card mb-4">
    <div class="card-header d-flex flex-column align-items-start">
        <h4 class="fw-semibold text-primary mb-1">Activity Attributes</h4>
        <small class="text-muted">Add activity tags, highlights, inclusions, and exclusions under this card.</small>
    </div>

    <div class="card-body">
        @foreach($activityListFields as $field => $config)
            @php
                $items = $normalizeActivityList($field);
                if ($items === []) {
                    $items = [''];
                }
                $containerId = 'activity-content-' . $field . '-container';
            @endphp

            <div class="mb-4 activity-list-section" data-field="{{ $field }}">
                <label class="form-label">{{ $config['label'] }}</label>
                <small class="text-muted d-block mb-2">{{ $config['help'] }}</small>

                <div id="{{ $containerId }}">
                    @foreach($items as $value)
                        <div class="input-group mb-2 activity-list-row">
                            <input type="text"
                                   name="{{ $field }}[]"
                                   class="form-control"
                                   placeholder="{{ $config['placeholder'] }}"
                                   value="{{ $value }}">
                            <button type="button" class="btn btn-danger remove-activity-list-row">Remove</button>
                        </div>
                    @endforeach
                </div>

                <button type="button"
                        class="btn btn-success btn-sm add-activity-list-row"
                        data-container="{{ $containerId }}"
                        data-field="{{ $field }}"
                        data-placeholder="{{ $config['placeholder'] }}">
                    Add Item
                </button>

                @error($field)<span class="text-danger d-block mt-1">{{ $message }}</span>@enderror
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
(() => {
    if (window.activityContentListsInitialized) {
        return;
    }

    window.activityContentListsInitialized = true;

    document.addEventListener('click', (event) => {
        const addButton = event.target.closest('.add-activity-list-row');
        if (addButton) {
            const container = document.getElementById(addButton.dataset.container);
            if (!container) {
                return;
            }

            const row = document.createElement('div');
            row.className = 'input-group mb-2 activity-list-row';

            const input = document.createElement('input');
            input.type = 'text';
            input.name = `${addButton.dataset.field}[]`;
            input.className = 'form-control';
            input.placeholder = addButton.dataset.placeholder || 'Enter item';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn btn-danger remove-activity-list-row';
            removeButton.textContent = 'Remove';

            row.append(input, removeButton);
            container.appendChild(row);
            input.focus();
            return;
        }

        const removeButton = event.target.closest('.remove-activity-list-row');
        if (!removeButton) {
            return;
        }

        const section = removeButton.closest('.activity-list-section');
        const row = removeButton.closest('.activity-list-row');
        if (!section || !row) {
            return;
        }

        const rows = section.querySelectorAll('.activity-list-row');
        if (rows.length <= 1) {
            const input = row.querySelector('input');
            if (input) {
                input.value = '';
            }
            return;
        }

        row.remove();
    });
})();
</script>
@endpush
