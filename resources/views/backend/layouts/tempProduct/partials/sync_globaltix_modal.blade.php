{{-- Sync GlobalTix Modal --}}
<div class="modal fade" id="syncGlobalTixModal" tabindex="-1" aria-labelledby="syncGlobalTixModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down" role="document">
        <div class="modal-content modal-content-demo border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white px-4 py-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="avatar avatar-sm bg-info text-white rounded-circle d-flex align-items-center justify-content-center">
                        <i class="bi bi-arrow-repeat fs-16"></i>
                    </div>
                    <h6 class="modal-title fw-bold text-white mb-0" id="syncGlobalTixModalLabel">Sync GlobalTix New Products</h6>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" id="syncGlobalTixHeaderCloseBtn"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-info d-flex align-items-start flex-wrap shadow-sm rounded-3 mb-3">
                    <i class="bi bi-info-circle me-2 mt-1 fs-5 flex-shrink-0 text-info"></i>
                    <div class="small">
                        <strong>Live Provider Sync:</strong> Queries GlobalTix API for new and updated activities published within the selected date range. 
                        Activities will be imported into this temporary review table for admin inspection.
                    </div>
                </div>
                <form id="syncGlobalTixForm" class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="syncStartDate" name="startDate"
                                   value="{{ now()->subDays(7)->toDateString() }}"
                                   max="{{ now()->toDateString() }}" required>
                            <label for="syncStartDate">Start Date <span class="text-danger">*</span></label>
                        </div>
                        <span class="text-danger small error-text startDate_error"></span>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="date" class="form-control" id="syncEndDate" name="endDate"
                                   value="{{ now()->toDateString() }}" required>
                            <label for="syncEndDate">End Date <span class="text-danger">*</span></label>
                        </div>
                        <span class="text-danger small error-text endDate_error"></span>
                    </div>
                </form>

                {{-- Real-time Progress Bar --}}
                <div id="syncGtProgressSection" class="mt-4 p-3 bg-light rounded-3 border d-none">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-semibold small text-dark" id="syncGtProgressStatus">Checking GlobalTix API...</span>
                        <span class="small fw-bold text-primary" id="syncGtProgressPercent">0%</span>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-info" id="syncGtProgressBar" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="mt-2 text-muted small" id="syncGtProgressDetails"></div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex flex-column flex-sm-row justify-content-sm-end gap-2 border-top">
                <button type="button" class="btn btn-info text-white w-100 w-sm-auto rounded-pill px-4" id="syncGlobalTixBtn">
                    <i class="bi bi-arrow-repeat me-1"></i> Sync New Products
                </button>
                <button type="button" class="btn btn-secondary w-100 w-sm-auto rounded-pill px-4" data-bs-dismiss="modal" id="syncGlobalTixCloseBtn">Close</button>
            </div>
        </div>
    </div>
</div>
