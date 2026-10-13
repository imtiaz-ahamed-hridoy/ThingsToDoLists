{{-- ========================= 
     Cancellation Policy - Update
========================= --}}
<div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0 text-primary fw-semibold">Cancellation Policies</h4>
    </div>

    <div class="card-body">

        @php
            $policies = old(
                'cancellation_policies',
                $product->cancellationPolicies->map(function($p) {
                    return [
                        'policy' => $p->policy,
                        'policyType' => $p->policyType,
                        'tax' => $p->tax,
                        'details' => json_decode($p->details, true) ?: [
                            ['cutoffHours'=>'','charge'=>'','chargeType'=>'','percentage'=>'']
                        ],
                    ];
                })->toArray() ?: [
                    [
                        'policy' => '',
                        'policyType' => '',
                        'tax' => '',
                        'details' => [
                            ['cutoffHours'=>'','charge'=>'','chargeType'=>'','percentage'=>'']
                        ]
                    ]
                ]
            );
        @endphp

        <div id="policies_container">
            @foreach($policies as $pIndex => $policy)
                <div class="border rounded p-3 mb-4 policy-block">

                    <h5 class="text-secondary">Policy {{ $pIndex + 1 }}</h5>

                    {{-- Policy --}}
                    <textarea
                        class="form-control mb-2"
                        name="cancellation_policies[{{ $pIndex }}][policy]"
                        placeholder="Enter cancellation policy"
                    >{{ $policy['policy'] }}</textarea>

                    {{-- Policy Type --}}
                    <select
                        class="form-control mb-2"
                        name="cancellation_policies[{{ $pIndex }}][policyType]"
                    >
                        <option value="">Select Policy Type</option>
                        <option value="FREE" {{ ($policy['policyType'] ?? '')==='FREE'?'selected':'' }}>Free</option>
                        <option value="PARTIAL" {{ ($policy['policyType'] ?? '')==='PARTIAL'?'selected':'' }}>Partial</option>
                        <option value="NON_REFUNDABLE" {{ ($policy['policyType'] ?? '')==='NON_REFUNDABLE'?'selected':'' }}>Non-Refundable</option>
                    </select>

                    {{-- Tax --}}
                    <input
                        type="text"
                        class="form-control mb-3"
                        name="cancellation_policies[{{ $pIndex }}][tax]"
                        value="{{ $policy['tax'] ?? '' }}"
                        placeholder="Tax / Fee"
                    >

                    {{-- Rules --}}
                    <h6 class="fw-semibold">Rules</h6>
                    <div class="rules_container">
                        @foreach($policy['details'] as $dIndex => $detail)
                            <div class="border p-3 rounded mb-3 rule-block">
                                <div class="mb-2">
                                    <label class="form-label">Cutoff Hours</label>
                                    <input type="number"
                                        class="form-control"
                                        name="cancellation_policies[{{ $pIndex }}][details][{{ $dIndex }}][cutoffHours]"
                                        value="{{ $detail['cutoffHours'] ?? '' }}">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Charge</label>
                                    <input type="number"
                                        class="form-control"
                                        name="cancellation_policies[{{ $pIndex }}][details][{{ $dIndex }}][charge]"
                                        value="{{ $detail['charge'] ?? '' }}">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Charge Type</label>
                                    <select class="form-control"
                                        name="cancellation_policies[{{ $pIndex }}][details][{{ $dIndex }}][chargeType]">
                                        <option value="">Select</option>
                                        <option value="percentage" {{ ($detail['chargeType'] ?? '')==='percentage'?'selected':'' }}>Percentage</option>
                                        <option value="fixed" {{ ($detail['chargeType'] ?? '')==='fixed'?'selected':'' }}>Fixed</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Percentage</label>
                                    <input type="text"
                                        class="form-control"
                                        name="cancellation_policies[{{ $pIndex }}][details][{{ $dIndex }}][percentage]"
                                        value="{{ $detail['percentage'] ?? '' }}">
                                </div>

                                <button type="button" class="btn btn-danger btn-sm remove-rule">
                                    Remove Rule
                                </button>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" class="btn btn-primary btn-sm add-rule">
                        + Add Rule
                    </button>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- JS for dynamic cancellation policy rules --}}
@push('scripts')
<script>
document.addEventListener('click', function (e) {

    // Add Rule
    if (e.target.classList.contains('add-rule')) {
        const policyBlock = e.target.closest('.policy-block');
        const rules = policyBlock.querySelector('.rules_container');
        const pIndex = [...document.querySelectorAll('.policy-block')].indexOf(policyBlock);
        const dIndex = rules.children.length;

        rules.insertAdjacentHTML('beforeend', `
            <div class="border p-3 rounded mb-3 rule-block">
                <div class="mb-2">
                    <label class="form-label">Cutoff Hours</label>
                    <input type="number" class="form-control"
                        name="cancellation_policies[${pIndex}][details][${dIndex}][cutoffHours]"
                        placeholder="Cutoff Hours">
                </div>

                <div class="mb-2">
                    <label class="form-label">Charge</label>
                    <input type="number" class="form-control"
                        name="cancellation_policies[${pIndex}][details][${dIndex}][charge]"
                        placeholder="Charge">
                </div>

                <div class="mb-2">
                    <label class="form-label">Charge Type</label>
                    <select class="form-control"
                        name="cancellation_policies[${pIndex}][details][${dIndex}][chargeType]">
                        <option value="">Select</option>
                        <option value="percentage">Percentage</option>
                        <option value="fixed">Fixed</option>
                    </select>
                </div>

                <div class="mb-2">
                    <label class="form-label">Percentage</label>
                    <input type="text" class="form-control"
                        name="cancellation_policies[${pIndex}][details][${dIndex}][percentage]"
                        placeholder="Percentage">
                </div>

                <button type="button" class="btn btn-danger btn-sm remove-rule">
                    Remove Rule
                </button>
            </div>
        `);
    }

    // Remove Rule
    if (e.target.classList.contains('remove-rule')) {
        e.target.closest('.rule-block').remove();
    }
});
</script>
@endpush
