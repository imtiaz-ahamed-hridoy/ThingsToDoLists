{{-- GlobalTix Live Raw API & Timeslots Modal --}}
<div class="modal fade" id="globaltixRawModal" tabindex="-1" aria-labelledby="globaltixRawModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down" role="document">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white px-4 py-3 border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar avatar-md bg-info text-white rounded-circle d-flex align-items-center justify-content-center shadow-sm">
                        <i class="fe fe-activity fs-18"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="gtRawModalTitle">Live GlobalTix Activity Data</h5>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="badge bg-primary-transparent text-primary" id="gtRawPlatformBadge">GlobalTix</span>
                            <span class="badge bg-warning text-dark fw-bold" id="gtRawDbIdBadge">DB ID: -</span>
                            <span class="badge bg-info-transparent text-info" id="gtRawApiIdBadge">API ID: -</span>
                            <span class="badge bg-secondary-transparent text-secondary" id="gtRawCityBadge">City: -</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-light rounded-pill" id="gtRawRefreshBtn" onclick="refreshCurrentLiveRawData()">
                        <i class="fe fe-refresh-cw me-1"></i> Refresh Live
                    </button>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            </div>

            {{-- Modal Navigation Tabs --}}
            <div class="bg-light border-bottom px-4 pt-2">
                <ul class="nav nav-pills gap-2 pb-2" id="gtRawTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active rounded-pill px-3 py-1 fw-semibold" id="gt-options-tab" data-bs-toggle="pill" data-bs-target="#gt-options-pane" type="button" role="tab" aria-selected="true">
                            <i class="fe fe-tag me-1"></i> Ticket Options & Prices
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-semibold" id="gt-timeslots-tab" data-bs-toggle="pill" data-bs-target="#gt-timeslots-pane" type="button" role="tab" aria-selected="false">
                            <i class="fe fe-clock me-1"></i> Live Timeslots & Availability
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-semibold" id="gt-info-tab" data-bs-toggle="pill" data-bs-target="#gt-info-pane" type="button" role="tab" aria-selected="false">
                            <i class="fe fe-info me-1"></i> Product Info & Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link rounded-pill px-3 py-1 fw-semibold" id="gt-rawjson-tab" data-bs-toggle="pill" data-bs-target="#gt-rawjson-pane" type="button" role="tab" aria-selected="false">
                            <i class="fe fe-code me-1"></i> Raw API JSON
                        </button>
                    </li>
                </ul>
            </div>

            <div class="modal-body p-4">
                {{-- Loading Spinner --}}
                <div id="gtRawLoading" class="text-center py-5">
                    <div class="spinner-border text-info" style="width: 3.5rem; height: 3.5rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted fw-semibold">Fetching real-time activity data from GlobalTix live API...</p>
                </div>

                {{-- Error Message --}}
                <div id="gtRawError" class="alert alert-danger d-none shadow-sm rounded-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fe fe-alert-triangle fs-20"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">Live API Request Failed</h6>
                            <p class="mb-0 small" id="gtRawErrorMsg">Unable to retrieve live data from GlobalTix.</p>
                        </div>
                    </div>
                </div>

                {{-- Tab Content --}}
                <div class="tab-content d-none" id="gtRawTabContent">
                    {{-- 1. Ticket Options & Prices Pane --}}
                    <div class="tab-pane fade show active" id="gt-options-pane" role="tabpanel" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fe fe-list text-primary me-1"></i> Bookable Options & Ticket Rates
                            </h6>
                            <span class="badge bg-primary" id="gtOptionsCountBadge">0 Options</span>
                        </div>
                        <div id="gtOptionsContainer" class="d-flex flex-column gap-3">
                            {{-- Dynamically populated --}}
                        </div>
                    </div>

                    {{-- 2. Timeslots & Availability Pane --}}
                    <div class="tab-pane fade" id="gt-timeslots-pane" role="tabpanel" tabindex="0">
                        <div class="card border border-light-subtle rounded-3 shadow-sm mb-4">
                            <div class="card-body p-3 bg-light rounded-3">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold small text-secondary">Select Ticket Type:</label>
                                        <select class="form-select form-select-sm" id="gtTimeslotTicketSelect">
                                            <option value="">Loading ticket types...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">Start Date:</label>
                                        <input type="date" class="form-control form-control-sm" id="gtTimeslotStartDate">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold small text-secondary">End Date:</label>
                                        <input type="date" class="form-control form-control-sm" id="gtTimeslotEndDate">
                                    </div>
                                    <div class="col-md-2">
                                        <button class="btn btn-sm btn-primary w-100 fw-semibold" id="gtTimeslotCheckBtn" onclick="fetchLiveTimeslotsForSelectedTicket()">
                                            <i class="fe fe-search me-1"></i> Check Slots
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Timeslot Results Table --}}
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold mb-0 text-dark">
                                <i class="fe fe-clock text-info me-1"></i> Live Timeslots & Real-Time Capacity
                            </h6>
                            <span class="badge bg-info text-white" id="gtTimeslotCountBadge">0 Slots</span>
                        </div>

                        <div id="gtTimeslotTableLoading" class="text-center py-4 d-none">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="ms-2 text-muted small">Checking live event availability...</span>
                        </div>

                        <div class="table-responsive border rounded-3 bg-white shadow-sm" id="gtTimeslotsTableWrapper">
                            <table class="table table-hover table-striped align-middle mb-0" id="gtTimeslotsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="py-2">Date</th>
                                        <th class="py-2">Timeslot / Session</th>
                                        <th class="py-2">Status</th>
                                        <th class="py-2">Available Capacity</th>
                                        <th class="py-2">Selling Price</th>
                                        <th class="py-2">Nett Price</th>
                                        <th class="py-2">Currency</th>
                                    </tr>
                                </thead>
                                <tbody id="gtTimeslotsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">Select a ticket type and click "Check Slots" to load real-time availability.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- 3. Product Info Pane --}}
                    <div class="tab-pane fade" id="gt-info-pane" role="tabpanel" tabindex="0">
                        <div class="row g-4" id="gtInfoContainer">
                            {{-- Dynamically populated --}}
                        </div>
                    </div>

                    {{-- 4. Raw JSON Pane --}}
                    <div class="tab-pane fade" id="gt-rawjson-pane" role="tabpanel" tabindex="0">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small">100% untouched response directly from GlobalTix endpoints</span>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="copyGtRawJson()">
                                <i class="fe fe-copy me-1"></i> Copy JSON
                            </button>
                        </div>
                        <pre class="bg-dark text-light p-3 rounded-3 shadow-inner" style="max-height: 500px; overflow-y: auto; font-size: 13px;"><code id="gtRawJsonCode">// Raw JSON will load here</code></pre>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light px-4 py-3 border-top">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
