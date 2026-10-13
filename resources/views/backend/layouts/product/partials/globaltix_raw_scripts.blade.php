{{-- GlobalTix / Provider Live Raw Modal Scripts --}}
<script>
    // ==================== LIVE GLOBALTIX / BOKUN RAW MODAL ====================
    let currentLiveRawId = null;
    let currentLiveRawData = null;

    window.viewLiveProductRaw = function(id, apiId, platform, event) {
        if (event) event.preventDefault();
        currentLiveRawId = id;

        $('#gtRawModalTitle').text('Loading Live Activity...');
        $('#gtRawPlatformBadge').text(platform ? platform.toUpperCase() : 'GLOBALTIX');
        $('#gtRawDbIdBadge').text(`DB ID: #${id}`);
        $('#gtRawApiIdBadge').text(`API ID: ${apiId || id}`);
        $('#gtRawCityBadge').text('City: -');

        $('#gtRawLoading').removeClass('d-none');
        $('#gtRawError').addClass('d-none');
        $('#gtRawTabContent').addClass('d-none');
        $('#globaltixRawModal').modal('show');

        // Set default date range for timeslots (today to +14 days)
        const today = new Date().toISOString().split('T')[0];
        const next14 = new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        $('#gtTimeslotStartDate').val(today);
        $('#gtTimeslotEndDate').val(next14);

        let url = "{{ route('product.liveRaw', ':id') }}".replace(':id', id);

        $.ajax({
            url: url,
            type: 'GET',
            success: function(resp) {
                $('#gtRawLoading').addClass('d-none');

                if (!resp.success) {
                    $('#gtRawErrorMsg').text(resp.message || 'Failed to retrieve live data.');
                    $('#gtRawError').removeClass('d-none');
                    return;
                }

                currentLiveRawData = resp;
                $('#gtRawTabContent').removeClass('d-none');
                renderLiveRawModalContent(resp);

                if (typeof dTable !== 'undefined') {
                    dTable.ajax.reload(null, false);
                }
            },
            error: function(xhr) {
                $('#gtRawLoading').addClass('d-none');
                let msg = 'Failed to connect to provider live API.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $('#gtRawErrorMsg').text(msg);
                $('#gtRawError').removeClass('d-none');
            }
        });
    };

    $('#globaltixRawModal').on('hidden.bs.modal', function () {
        if (typeof dTable !== 'undefined') {
            dTable.ajax.reload(null, false);
        }
    });

    window.refreshCurrentLiveRawData = function() {
        if (currentLiveRawId) {
            const apiId = currentLiveRawData?.product?.product_api_id || currentLiveRawId;
            const platform = currentLiveRawData?.platform || 'globaltix';
            window.viewLiveProductRaw(currentLiveRawId, apiId, platform);
        }
    };

    function renderLiveRawModalContent(data) {
        const product = data.product || {};
        const info = data.product_info || {};
        const options = data.product_options || [];
        const ticketTypes = data.ticket_types || [];
        const availabilities = data.availability || [];

        $('#gtRawModalTitle').text(product.title || info.name || info.title || 'Live Activity Details');
        $('#gtRawDbIdBadge').text(`DB ID: #${product.id || currentLiveRawId || 'N/A'}`);
        $('#gtRawApiIdBadge').text(`API ID: ${product.product_api_id || currentLiveRawId || 'N/A'}`);
        $('#gtRawCityBadge').text(`City: ${product.city || info.city || 'N/A'}`);

        // 1. Render Options & Ticket Types
        renderLiveOptions(options, ticketTypes);

        // 2. Populate Timeslot Ticket Select & Initial Timeslots
        populateTimeslotTicketSelect(ticketTypes);
        renderTimeslotsTable(availabilities);

        // 3. Render Product Info
        renderLiveInfo(info, product);

        // 4. Render Raw JSON
        const rawPretty = JSON.stringify(data.raw_response || data, null, 2);
        $('#gtRawJsonCode').text(rawPretty);
    }

    function renderLiveOptions(options, ticketTypes) {
        const container = $('#gtOptionsContainer');
        container.empty();

        const optionsList = Array.isArray(options) ? options : (options ? [options] : []);
        $('#gtOptionsCountBadge').text(`${optionsList.length} Option(s)`);

        if (optionsList.length === 0) {
            container.html('<div class="alert alert-warning">No options found in live API response.</div>');
            return;
        }

        optionsList.forEach((opt, idx) => {
            const optId = opt.id || `opt-${idx}`;
            const optName = opt.name || opt.title || `Option ${idx + 1}`;
            const tickets = opt.ticketType || [];

            let ticketsHtml = '';
            if (Array.isArray(tickets) && tickets.length > 0) {
                ticketsHtml = `
                    <div class="table-responsive mt-3">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr class="small text-uppercase text-secondary">
                                    <th>Ticket ID</th>
                                    <th>Ticket Type</th>
                                    <th>Recommended Selling</th>
                                    <th>Selling Price</th>
                                    <th>Nett Price</th>
                                    <th>Original Price</th>
                                    <th>Currency</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${tickets.map(t => `
                                    <tr>
                                        <td class="fw-bold text-primary">#${escapeHtml(t.id || 'N/A')}</td>
                                        <td class="fw-semibold">${escapeHtml(t.name || 'Standard')}</td>
                                        <td class="text-success fw-bold">${t.recommendedSellingPrice !== undefined && t.recommendedSellingPrice !== null ? escapeHtml(t.recommendedSellingPrice) : '<span class="text-muted">-</span>'}</td>
                                        <td>${t.sellingPrice !== undefined && t.sellingPrice !== null ? escapeHtml(t.sellingPrice) : '<span class="text-muted">-</span>'}</td>
                                        <td class="text-warning-emphasis">${t.nettPrice !== undefined && t.nettPrice !== null ? escapeHtml(t.nettPrice) : '<span class="text-muted">-</span>'}</td>
                                        <td class="text-muted text-decoration-line-through">${t.originalPrice !== undefined && t.originalPrice !== null ? escapeHtml(t.originalPrice) : '-'}</td>
                                        <td><span class="badge bg-light text-dark border">${escapeHtml(t.currency || 'EUR')}</span></td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            } else {
                ticketsHtml = '<p class="text-muted small mb-0 mt-2">No ticket types specified for this option.</p>';
            }

            const cardHtml = `
                <div class="card border rounded-3 shadow-sm overflow-hidden mb-2">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-dark me-2">Option ID: ${escapeHtml(optId)}</span>
                            <span class="fw-bold fs-15 text-dark">${escapeHtml(optName)}</span>
                        </div>
                        <div class="d-flex gap-1">
                            ${opt.ticketValidity ? `<span class="badge bg-secondary-transparent text-secondary">${escapeHtml(opt.ticketValidity)}</span>` : ''}
                            ${opt.isCancellable ? '<span class="badge bg-success-transparent text-success">Cancellable</span>' : '<span class="badge bg-danger-transparent text-danger">Non-refundable</span>'}
                        </div>
                    </div>
                    <div class="card-body p-3">
                        ${opt.description ? `<p class="small text-muted mb-2">${escapeHtml(opt.description)}</p>` : ''}
                        ${ticketsHtml}
                    </div>
                </div>
            `;
            container.append(cardHtml);
        });
    }

    function populateTimeslotTicketSelect(ticketTypes) {
        const select = $('#gtTimeslotTicketSelect');
        select.empty();

        if (!ticketTypes || ticketTypes.length === 0) {
            select.append('<option value="">No ticket types available</option>');
            return;
        }

        ticketTypes.forEach(t => {
            const optText = `[#${t.ticket_id}] ${t.ticket_name} (${t.option_name}) - ${t.selling_price || '-'} ${t.currency}`;
            select.append(`<option value="${escapeHtml(t.ticket_id)}">${escapeHtml(optText)}</option>`);
        });
    }

    window.fetchLiveTimeslotsForSelectedTicket = function() {
        const ticketId = $('#gtTimeslotTicketSelect').val();
        const dateFrom = $('#gtTimeslotStartDate').val();
        const dateTo   = $('#gtTimeslotEndDate').val();

        if (!ticketId) {
            toastr.warning('Please select a ticket type.');
            return;
        }

        if (!dateFrom || !dateTo) {
            toastr.warning('Please select both start and end dates.');
            return;
        }

        $('#gtTimeslotTableLoading').removeClass('d-none');
        $('#gtTimeslotsTableBody').empty();

        $.ajax({
            url: "{{ route('product.liveAvailability') }}",
            type: "POST",
            data: {
                ticket_id: ticketId,
                date_from: dateFrom,
                date_to:   dateTo,
                _token:    $('meta[name="csrf-token"]').attr("content")
            },
            success: function(resp) {
                $('#gtTimeslotTableLoading').addClass('d-none');
                const slots = resp.data || [];
                renderTimeslotsTable(slots);
            },
            error: function(xhr) {
                $('#gtTimeslotTableLoading').addClass('d-none');
                let msg = 'Failed to fetch timeslots.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                toastr.error(msg);
                $('#gtTimeslotsTableBody').html(`<tr><td colspan="7" class="text-center py-4 text-danger">${escapeHtml(msg)}</td></tr>`);
            }
        });
    };

    function renderTimeslotsTable(slots) {
        const tbody = $('#gtTimeslotsTableBody');
        tbody.empty();

        const slotList = Array.isArray(slots) ? slots : [];
        $('#gtTimeslotCountBadge').text(`${slotList.length} Slot(s)`);

        if (slotList.length === 0) {
            tbody.html('<tr><td colspan="7" class="text-center py-4 text-muted">No availability timeslots found for the selected date range.</td></tr>');
            return;
        }

        slotList.forEach(item => {
            const isAvail = item.isAvailable !== false && (item.available === undefined || item.available > 0);
            const statusBadge = isAvail
                ? '<span class="badge bg-success">Available</span>'
                : '<span class="badge bg-danger">Sold Out</span>';

            const timeDisplay = item.formatted_time || item.time || (item.time_slot ? item.time_slot : 'All Day');
            const dateDisplay = item.formatted_date || item.date || 'N/A';
            const capacityDisplay = item.available !== undefined && item.available !== null ? item.available : (item.total || 'N/A');
            const sellingPrice = item.recommendedSellingPrice ?? item.sellingPrice ?? (item.price?.amount ?? '-');
            const nettPrice = item.nettPrice ?? '-';
            const currency = item.currency ?? (item.price?.currency ?? 'EUR');

            const row = `
                <tr>
                    <td class="fw-semibold">${escapeHtml(dateDisplay)}</td>
                    <td><span class="badge bg-light text-dark border px-2 py-1"><i class="fe fe-clock me-1 text-info"></i>${escapeHtml(timeDisplay)}</span></td>
                    <td>${statusBadge}</td>
                    <td class="fw-bold">${escapeHtml(capacityDisplay)}</td>
                    <td class="text-success fw-bold">${escapeHtml(sellingPrice)}</td>
                    <td class="text-secondary">${escapeHtml(nettPrice)}</td>
                    <td><span class="badge bg-primary-transparent text-primary">${escapeHtml(currency)}</span></td>
                </tr>
            `;
            tbody.append(row);
        });
    }

    function renderLiveInfo(info, product) {
        const container = $('#gtInfoContainer');
        container.empty();

        const title = info.name || info.title || product.title || 'Untitled';
        const desc = info.description || info.full_description || 'No description provided.';
        const city = info.city || product.city || 'N/A';
        const country = info.country || 'N/A';
        const address = info.address || info.location?.address || 'N/A';
        const category = info.category?.name || info.productCategory || 'N/A';

        const formatInclusionText = (inc) => {
            if (!inc) return '';
            if (typeof inc === 'string') return inc;
            if (typeof inc === 'object') {
                return inc.value || inc.name || inc.label || inc.title || inc.description || JSON.stringify(inc);
            }
            return String(inc);
        };

        const formatHighlightText = (h) => {
            if (!h) return '';
            if (typeof h === 'string') return h;
            if (typeof h === 'object') {
                return h.value || h.name || h.label || h.title || h.description || JSON.stringify(h);
            }
            return String(h);
        };

        let highlightsHtml = '';
        if (Array.isArray(info.highlights) && info.highlights.length > 0) {
            highlightsHtml = `
                <div class="mt-3">
                    <h6 class="fw-bold small text-uppercase text-secondary mb-2">Highlights</h6>
                    <ul class="list-group list-group-flush small">
                        ${info.highlights.map(h => `<li class="list-group-item px-0 py-1"><i class="fe fe-check-circle text-success me-2"></i>${escapeHtml(formatHighlightText(h))}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        let inclusionsHtml = '';
        if (Array.isArray(info.inclusions) && info.inclusions.length > 0) {
            inclusionsHtml = `
                <div class="mt-3">
                    <h6 class="fw-bold small text-uppercase text-secondary mb-2">Inclusions</h6>
                    <div class="d-flex flex-wrap gap-1">
                        ${info.inclusions.map(inc => `<span class="badge bg-success-transparent text-success">${escapeHtml(formatInclusionText(inc))}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        const html = `
            <div class="col-md-7">
                <h5 class="fw-bold text-dark mb-2">${escapeHtml(title)}</h5>
                <div class="d-flex gap-2 mb-3">
                    <span class="badge bg-primary">${escapeHtml(category)}</span>
                    <span class="badge bg-info">${escapeHtml(city)}, ${escapeHtml(country)}</span>
                </div>
                <p class="text-muted small" style="white-space: pre-line; line-height: 1.6;">${escapeHtml(desc)}</p>
                ${highlightsHtml}
                ${inclusionsHtml}
            </div>
            <div class="col-md-5">
                <div class="card border rounded-3 p-3 bg-light">
                    <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Location & Booking Info</h6>
                    <p class="small mb-2"><strong>Database ID (DB ID):</strong> <span class="badge bg-warning text-dark fw-bold">#${escapeHtml(product.id || currentLiveRawId || 'N/A')}</span></p>
                    <p class="small mb-2"><strong>Product API ID:</strong> <span class="badge bg-dark">${escapeHtml(product.product_api_id || 'N/A')}</span></p>
                    <p class="small mb-2"><strong>City:</strong> ${escapeHtml(city)}</p>
                    <p class="small mb-2"><strong>Country:</strong> ${escapeHtml(country)}</p>
                    <p class="small mb-2"><strong>Address:</strong> ${escapeHtml(address)}</p>
                    <p class="small mb-2"><strong>Stored DB Price:</strong> <span class="badge bg-dark">${product.stored_price ? Number(product.stored_price).toFixed(2) : '0.00'} EUR</span></p>
                    ${info.meetingPoint ? `<p class="small mb-2"><strong>Meeting Point:</strong> ${escapeHtml(info.meetingPoint)}</p>` : ''}
                </div>
            </div>
        `;
        container.html(html);
    }

    window.copyGtRawJson = function() {
        const code = $('#gtRawJsonCode').text();
        navigator.clipboard.writeText(code).then(() => {
            toastr.success('Raw Provider JSON copied to clipboard!');
        }).catch(() => {
            toastr.info('Please select and copy the text manually.');
        });
    };

    if (typeof escapeHtml !== 'function') {
        window.escapeHtml = (text) => {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        };
    }
</script>
