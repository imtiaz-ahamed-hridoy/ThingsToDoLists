{{-- =========================
     Location Details (Production Ready)
========================== --}}

<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-primary fw-semibold">Location Details</h4>
    </div>

    <div class="card-body">

        {{-- City --}}
        <div class="mb-3">
            <label class="form-label">
                City <span class="text-danger">*</span>
            </label>
            <input
                type="text"
                name="location[city]"
                class="form-control"
                value="{{ old('location.city', $location->city ?? '') }}"
                placeholder="Enter city name"
                
            >
            @error('location.city')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        {{-- Country Code --}}
        <div class="mb-3">
            <label class="form-label">
                Country Code <span class="text-danger">*</span>
            </label>
            <input
                type="text"
                name="location[countryCode]"
                class="form-control text-uppercase"
                value="{{ old('location.countryCode', $location->countryCode ?? '') }}"
                placeholder="e.g. US, BD"
                
            >
            @error('location.countryCode')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        {{-- Country --}}
        <div class="mb-3">
            <label class="form-label">
                Country <span class="text-danger">*</span>
            </label>
            <input
                type="text"
                name="location[country]"
                class="form-control"
                value="{{ old('location.country', $location->country ?? '') }}"
                placeholder="Enter country name"
                
            >
            @error('location.country')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

        {{-- Coordinates --}}
        <div class="mb-3">
            <label class="form-label">Coordinates</label>

            @php
                /**
                 * Normalize coordinates into a predictable structure.
                 * This guarantees frontend + backend alignment.
                 */
                $coordsRaw = old('location.coordinates', $location->coordinates ?? null);

                if (is_string($coordsRaw)) {
                    $coords = json_decode($coordsRaw, true) ?: [];
                } elseif (is_array($coordsRaw)) {
                    $coords = $coordsRaw;
                } else {
                    $coords = [];
                }

                // Enforce default keys if empty
                $coords = array_merge(
                    ['latitude' => '', 'longitude' => ''],
                    $coords
                );
            @endphp

            <div id="coordinates_wrapper">
                @foreach($coords as $key => $value)
                    <div class="row g-2 mb-2 coord-row">
                        <div class="col-md-6">
                            <input
                                type="text"
                                class="form-control coord-key"
                                value="{{ $key }}"
                                readonly
                            >
                        </div>

                        <div class="col-md-6">
                            <input
                                type="text"
                                class="form-control coord-value"
                                value="{{ $value }}"
                                placeholder="Enter value"
                            >
                        </div>

                    
                    </div>
                @endforeach
            </div>

            {{-- Single source of truth --}}
            <input
                type="hidden"
                name="location[coordinates]"
                id="coordinates_json"
                value='{{ json_encode($coords) }}'
            >

            @error('location.coordinates')
                <span class="text-danger">{{ $message }}</span>
            @enderror
        </div>

    </div>
</div>

{{-- =========================
     Coordinates Sync Script
     (No Dynamic Key Creation)
========================== --}}
<script>
(() => {
    const wrapper = document.getElementById('coordinates_wrapper');
    const hiddenInput = document.getElementById('coordinates_json');

    /**
     * Sync visible coordinate values into JSON payload
     */
    const updateCoordinatesJson = () => {
        const keys = wrapper.querySelectorAll('.coord-key');
        const values = wrapper.querySelectorAll('.coord-value');
        const data = {};

        keys.forEach((key, index) => {
            const k = key.value.trim();
            const v = values[index].value.trim();
            if (k && v !== '') {
                data[k] = v;
            }
        });

        hiddenInput.value = JSON.stringify(data);
    };

    /**
     * Bind events once. No dynamic DOM injection.
     */
    const bindEvents = () => {
        wrapper.querySelectorAll('.coord-value').forEach(input => {
            input.addEventListener('input', updateCoordinatesJson);
        });

    };

    bindEvents();
    updateCoordinatesJson();
})();
</script>
