{{-- Bokun Search Modal --}}
<div class="modal fade" id="bokunSearchModal" tabindex="-1" aria-labelledby="bokunSearchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content modal-content-demo">
            <div class="modal-header">
                <h6 class="modal-title" id="bokunSearchModalLabel"><i class="bi bi-cloud-arrow-down me-2"></i>Import from Bokun</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <p class="text-muted mb-4">Search activities from Bokun using the full search API. Results will appear in the temp products table below.</p>

                <form id="bokunSearchForm" class="row g-3">
                    @csrf
                    <input type="hidden" name="api_platform" value="bokun">

                    {{-- Direct API ID Import --}}
                    <div class="col-12">
                        <div class="alert alert-info mb-0 d-flex align-items-center flex-wrap">
                            <i class="bi bi-info-circle-fill me-2 fs-5 flex-shrink-0"></i>
                            <div>
                                <strong>Direct Import by Product API ID:</strong> Enter a Bokun activity ID to import/update one product immediately.
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="bokunProductApiId" name="product_api_id" placeholder="e.g. 53137">
                            <label for="bokunProductApiId"><i class="bi bi-hash me-1"></i> Product API ID (Optional)</label>
                        </div>
                        <span class="text-danger small error-text product_api_id_error"></span>
                    </div>

                    <div id="bokunSearchCriteriaSection" class="row g-3">
                        {{-- Search Section --}}
                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-search me-2"></i>Search Criteria</h6>
                        </div>

                        {{-- Keyword / Text Search --}}
                        <div class="col-12 col-md-6">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="bokunTextSearch" name="textSearch" placeholder="Keyword, Activity, City, or Country">
                                <label for="bokunTextSearch"><i class="bi bi-type-text me-1"></i> Keyword / Activity / City / Country</label>
                            </div>
                            <span class="text-danger small error-text textSearch_error"></span>
                        </div>


                        {{-- Date Filters --}}
                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="bokunStartDate" name="startDateFilter">
                                <label for="bokunStartDate"><i class="bi bi-calendar me-1"></i> Start Date (Optional)</label>
                            </div>
                            <span class="text-danger small error-text startDateFilter_error"></span>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <input type="date" class="form-control" id="bokunEndDate" name="endDateFilter">
                                <label for="bokunEndDate"><i class="bi bi-calendar me-1"></i> End Date (Optional)</label>
                            </div>
                            <span class="text-danger small error-text endDateFilter_error"></span>
                        </div>

                        {{-- Vendor ID --}}
                        <div class="col-12 col-md-4">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="bokunVendorId" name="vendorId" placeholder="e.g. 123" min="1">
                                <label for="bokunVendorId"><i class="bi bi-building me-1"></i> Vendor ID (Optional)</label>
                            </div>
                            <span class="text-danger small error-text vendorId_error"></span>
                        </div>

                        {{-- Advanced Search Options (collapsible) --}}
                        <div class="col-12 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="bokunAdvancedToggle" data-bs-toggle="collapse" data-bs-target="#bokunAdvancedSection" aria-expanded="false">
                                <i class="bi bi-sliders me-1"></i> Advanced Options
                            </button>
                        </div>

                        <div class="collapse col-12" id="bokunAdvancedSection">
                            <div class="card card-body bg-light border mt-2 p-3">
                                <div class="row g-3">
                                    {{-- Text Operator --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="bokunTextOperator" name="textOperator">
                                                <option value="AND">AND (all terms)</option>
                                                <option value="OR">OR (any term)</option>
                                            </select>
                                            <label for="bokunTextOperator"><i class="bi bi-diagram-3 me-1"></i> Text Operator</label>
                                        </div>
                                    </div>

                                    {{-- Wildcard Search --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-check form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" id="bokunTextWildcard" name="textWildcard" value="1">
                                            <label class="form-check-label" for="bokunTextWildcard"><i class="bi bi-asterisk me-1"></i> Wildcard Search</label>
                                        </div>
                                    </div>

                                    {{-- Language --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="bokunLanguage" name="language">
                                                <option value="EN">English</option>
                                                <option value="IS">Icelandic</option>
                                                <option value="DE">German</option>
                                                <option value="FR">French</option>
                                                <option value="ES">Spanish</option>
                                                <option value="IT">Italian</option>
                                                <option value="JA">Japanese</option>
                                                <option value="KO">Korean</option>
                                                <option value="ZH">Chinese</option>
                                                <option value="RU">Russian</option>
                                            </select>
                                            <label for="bokunLanguage"><i class="bi bi-globe me-1"></i> Language</label>
                                        </div>
                                    </div>

                                    {{-- Sort Field --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="bokunSortField" name="sortField">
                                                <option value="">Default</option>
                                                <option value="BEST_SELLING_GLOBAL">Best Selling (Global)</option>
                                                <option value="BEST_SELLING_LOCAL">Best Selling (Local)</option>
                                                <option value="PRICE">Price</option>
                                                <option value="TITLE">Title (A-Z)</option>
                                                <option value="DATE">Date</option>
                                                <option value="POPULARITY">Popularity</option>
                                                <option value="REVIEW_SCORE">Review Score</option>
                                                <option value="DURATION">Duration</option>
                                            </select>
                                            <label for="bokunSortField"><i class="bi bi-sort-alpha-down me-1"></i> Sort Field</label>
                                        </div>
                                    </div>

                                    {{-- Sort Order --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="bokunSortOrder" name="sortOrder">
                                                <option value="ASC">Ascending</option>
                                                <option value="DESC" selected>Descending</option>
                                            </select>
                                            <label for="bokunSortOrder"><i class="bi bi-arrow-up-down me-1"></i> Sort Order</label>
                                        </div>
                                    </div>

                                    {{-- Currency --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <select class="form-select" id="bokunCurrency" name="currency">
                                                <option value="USD">USD - US Dollar</option>
                                                <option value="EUR">EUR - Euro</option>
                                                <option value="GBP">GBP - British Pound</option>
                                                <option value="ISK">ISK - Icelandic Krona</option>
                                                <option value="JPY">JPY - Japanese Yen</option>
                                                <option value="SGD">SGD - Singapore Dollar</option>
                                                <option value="AUD">AUD - Australian Dollar</option>
                                                <option value="CAD">CAD - Canadian Dollar</option>
                                                <option value="CHF">CHF - Swiss Franc</option>
                                                <option value="NOK">NOK - Norwegian Krone</option>
                                                <option value="SEK">SEK - Swedish Krona</option>
                                                <option value="DKK">DKK - Danish Krone</option>
                                                <option value="THB">THB - Thai Baht</option>
                                                <option value="MYR">MYR - Malaysian Ringgit</option>
                                                <option value="CNY">CNY - Chinese Yuan</option>
                                                <option value="KRW">KRW - South Korean Won</option>
                                            </select>
                                            <label for="bokunCurrency"><i class="bi bi-currency-dollar me-1"></i> Currency</label>
                                        </div>
                                    </div>

                                    {{-- Participants --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" id="bokunParticipants" name="participants" placeholder="e.g. 2" min="1">
                                            <label for="bokunParticipants"><i class="bi bi-people me-1"></i> Participants</label>
                                        </div>
                                    </div>

                                    {{-- Participants by Age --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="bokunParticipantsByAge" name="participantsByAge_raw" placeholder="e.g. 25, 30">
                                            <label for="bokunParticipantsByAge"><i class="bi bi-person-lines-fill me-1"></i> Ages (comma separated)</label>
                                        </div>
                                    </div>

                                    {{-- Published Since --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <input type="date" class="form-control" id="bokunPublishedSince" name="publishedSince">
                                            <label for="bokunPublishedSince"><i class="bi bi-calendar-check me-1"></i> Published Since</label>
                                        </div>
                                    </div>

                                    {{-- External ID --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="bokunExternalId" name="externalId" placeholder="External ID">
                                            <label for="bokunExternalId"><i class="bi bi-upc-scan me-1"></i> External ID</label>
                                        </div>
                                    </div>

                                    {{-- Distance (for City geo fallback) --}}
                                    <div class="col-12 col-md-4">
                                        <div class="form-floating">
                                            <input type="text" class="form-control" id="bokunDistance" name="distance" placeholder="e.g. 30km" value="30km">
                                            <label for="bokunDistance"><i class="bi bi-geo me-1"></i> Radius Distance</label>
                                        </div>
                                    </div>

                                    <div class="col-12 mt-4">
                                        <button type="button" class="btn btn-sm btn-outline-danger w-100 text-start" data-bs-toggle="collapse" data-bs-target="#bokunDeveloperSection" aria-expanded="false">
                                            <i class="bi bi-code-slash me-2"></i> Developer / Technical Filters (Advanced JSON Fields)
                                        </button>
                                    </div>
                                    
                                    <div class="collapse col-12" id="bokunDeveloperSection">
                                        <div class="card card-body border-danger bg-white mt-2 p-3">
                                            <p class="text-danger small mb-3"><i class="bi bi-exclamation-triangle me-1"></i> These fields require technical knowledge. Leave blank if unsure.</p>
                                            
                                            <label class="fw-semibold text-secondary mb-2"><i class="bi bi-bounding-box-circles me-1"></i> Geo Bounding Box Filter</label>
                                            <div class="row g-2 mb-3">
                                                <div class="col-6 col-md-3">
                                                    <input type="number" step="any" class="form-control form-control-sm" name="geoBox[topLeft][lat]" placeholder="Top Left Lat">
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <input type="number" step="any" class="form-control form-control-sm" name="geoBox[topLeft][lng]" placeholder="Top Left Lng">
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <input type="number" step="any" class="form-control form-control-sm" name="geoBox[bottomRight][lat]" placeholder="Bottom Right Lat">
                                                </div>
                                                <div class="col-6 col-md-3">
                                                    <input type="number" step="any" class="form-control form-control-sm" name="geoBox[bottomRight][lng]" placeholder="Bottom Right Lng">
                                                </div>
                                            </div>

                                            <label class="fw-semibold text-secondary mb-2"><i class="bi bi-hexagon-fill me-1"></i> Geo Polygon Filter</label>
                                            <div class="mb-3">
                                                <textarea class="form-control form-control-sm" name="geoPolygon_raw" rows="2" placeholder='[{"lat": 42.4, "lng": 18.7}, {"lat": 42.5, "lng": 18.8}]'></textarea>
                                                <small class="text-muted">Enter valid JSON array of lat/lng objects</small>
                                            </div>

                                            <label class="fw-semibold text-secondary mb-2"><i class="bi bi-funnel-fill me-1"></i> Custom Facet Filters</label>
                                            <div class="row g-2">
                                                <div class="col-4">
                                                    <input type="text" class="form-control form-control-sm" name="facets[0][name]" placeholder="Facet Name (e.g. CATEGORY)">
                                                </div>
                                                <div class="col-8">
                                                    <input type="text" class="form-control form-control-sm" name="facets[0][values_raw]" placeholder="Values (comma separated, e.g. Sightseeing, Walking)">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Boolean Filters --}}
                                    <div class="col-12">
                                        <hr class="my-2">
                                        <label class="fw-semibold text-secondary mb-2"><i class="bi bi-toggle-on me-1"></i> Activity Filters</label>
                                        <div class="d-flex flex-column flex-sm-row gap-3">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="bokunExcludeBoxes" name="excludeBoxes" value="1">
                                                <label class="form-check-label" for="bokunExcludeBoxes">Exclude Boxes (bundled products)</label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="bokunComboActivity" name="comboActivity" value="1">
                                                <label class="form-check-label" for="bokunComboActivity">Combo Activities only</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Settings Section --}}
                        <div class="col-12 mt-3">
                            <h6 class="fw-bold text-secondary mb-3 border-bottom pb-2"><i class="bi bi-gear me-2"></i>Settings</h6>
                        </div>

                        {{-- Page Number --}}
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="form-floating">
                                <input type="number" inputmode="decimal" required class="form-control" id="bokunPageNumber" name="page" value="1" placeholder="Page">
                                <label for="bokunPageNumber">Page # <span class="text-danger">*</span></label>
                            </div>
                            <span class="text-danger small error-text page_error"></span>
                        </div>

                        {{-- Page Size --}}
                        <div class="col-12 col-sm-6 col-md-3">
                            <div class="form-floating">
                                <input type="number" required class="form-control" id="bokunPageSize" name="size" value="10" placeholder="Size">
                                <label for="bokunPageSize">Page Size <span class="text-danger">*</span></label>
                            </div>
                            <span class="text-danger small error-text size_error"></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex flex-column flex-sm-row justify-content-sm-end gap-2">
                <button type="button" class="btn btn-primary w-100 w-sm-auto" id="bokunSearchBtn"><i class="bi bi-search me-1"></i>Search & Import</button>
                <button type="button" class="btn btn-light w-100 w-sm-auto" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
