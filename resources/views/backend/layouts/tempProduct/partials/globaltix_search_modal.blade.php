{{-- GlobalTix Search Modal --}}
<div class="modal fade" id="globaltixSearchModal" tabindex="-1" aria-labelledby="globaltixSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content modal-content-demo">
            <div class="modal-header">
                <h6 class="modal-title" id="globaltixSearchModalLabel"><i class="bi bi-globe me-2"></i>Import from GlobalTix</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted mb-4">Search activities from GlobalTix. Select a country, optionally filter by city, category, or keyword.</p>

                <form id="globaltixSearchForm" class="row g-3">
                    @csrf
                    <input type="hidden" name="api_platform" value="globaltix">

                    {{-- Direct API ID Import --}}
                    <div class="col-12">
                        <div class="alert alert-info mb-0 d-flex align-items-center flex-wrap">
                            <i class="bi bi-info-circle-fill me-2 fs-5 flex-shrink-0"></i>
                            <div>
                                <strong>Direct Import by Product API ID:</strong> Enter a GlobalTix product ID to import/update one product immediately.
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="gtProductApiId" name="product_api_id" placeholder="e.g. 38997">
                            <label for="gtProductApiId"><i class="bi bi-hash me-1"></i> Product API ID (Optional)</label>
                        </div>
                        <span class="text-danger small error-text product_api_id_error"></span>
                    </div>

                    <div id="gtSearchCriteriaSection" class="row g-3">
                        {{-- Search Section --}}
                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-info mb-3 border-bottom pb-2"><i class="bi bi-search me-2"></i>Search Criteria</h6>
                        </div>

                        {{-- Country Code (Required) --}}
                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="gtCountryCode" name="countryCode" required>
                                    <option value="" selected disabled>Select a country...</option>
                                </select>
                                <label for="gtCountryCode"><i class="bi bi-flag me-1"></i> Country Code <span class="text-danger">*</span></label>
                            </div>
                            <span class="text-danger small error-text countryCode_error"></span>
                        </div>

                        {{-- Category (Real data from GlobalTix API) --}}
                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="gtCategory" name="categoryIds">
                                    <option value="">All Categories</option>
                                </select>
                                <label for="gtCategory"><i class="bi bi-tags me-1"></i> Category</label>
                            </div>
                            <span class="text-danger small error-text categoryIds_error"></span>
                        </div>

                        {{-- City --}}
                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <select class="form-select" id="gtCity" name="city">
                                    <option value="" selected>Select City (Optional)</option>
                                </select>
                                <label for="gtCity"><i class="bi bi-geo-alt me-1"></i> City</label>
                            </div>
                            <span class="text-danger small error-text city_error"></span>
                        </div>

                        {{-- Keyword / Text Search --}}
                        <div class="col-12 col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="gtTextSearch" name="textSearch" placeholder="Activity Name">
                                <label for="gtTextSearch"><i class="bi bi-type-text me-1"></i> Keyword / Activity Name</label>
                            </div>
                            <span class="text-danger small error-text textSearch_error"></span>
                        </div>

                        {{-- Settings Section --}}
                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-gear me-2"></i>Settings</h6>
                        </div>

                        {{-- Page Number --}}
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="form-floating">
                                <input type="number" inputmode="decimal" required class="form-control" id="gtPageNumber" name="page" value="1" placeholder="Page">
                                <label for="gtPageNumber">Page # <span class="text-danger">*</span></label>
                            </div>
                            <span class="text-danger small error-text page_error"></span>
                        </div>

                        {{-- Page Size --}}
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="form-floating">
                                <input type="number" required class="form-control" id="gtPageSize" name="size" value="10" placeholder="Size">
                                <label for="gtPageSize">Page Size <span class="text-danger">*</span></label>
                            </div>
                            <span class="text-danger small error-text size_error"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
                <button type="button" class="btn btn-info text-white w-100 w-sm-auto" id="globaltixSearchBtn"><i class="bi bi-search me-1"></i>Search & Import</button>
                <button type="button" class="btn btn-light w-100 w-sm-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
