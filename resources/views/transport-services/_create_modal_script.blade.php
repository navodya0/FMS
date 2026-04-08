<script>
document.addEventListener('DOMContentLoaded', function () {
    const $transportModal = $('#transportServiceModal');
    const $editModal = $('#editTransportServiceModal');

    const createModal = document.getElementById('transportServiceModal');
    const editModal = document.getElementById('editTransportServiceModal');
    const deleteModal = document.getElementById('deleteTransportServiceModal');

    const createForm = createModal?.querySelector('form');
    const editForm = document.getElementById('editTransportServiceForm');
    const deleteForm = document.getElementById('deleteTransportServiceForm');

    const typeInput = document.getElementById('ts_type');
    const title = document.getElementById('transportServiceTitle');

    const shuttleDateInput = document.getElementById('shuttle_service_date');
    const loadShuttleBookingsBtn = document.getElementById('loadShuttleBookingsBtn');
    const shuttleBookingsContainer = document.getElementById('shuttleBookingsContainer');
    const shuttleBookingsBody = document.getElementById('shuttleBookingsBody');
    const shuttleNoBookings = document.getElementById('shuttleNoBookings');
    const deleteNoteField = document.getElementById('delete_note');

    if ($('#transportServicesTable').length) {
        $('#transportServicesTable').DataTable({
            responsive: true,
            pageLength: 10,
            order: [[4, 'desc']],
            columnDefs: [
                {
                    targets: -1,
                    orderable: false,
                    searchable: false
                }
            ]
        });
    }

    function initEditSelect2() {
        const $vehicle = $('#edit_vehicle_id');
        const $chauffer = $('#edit_chauffer_id');

        if ($vehicle.length) {
            if ($vehicle.hasClass('select2-hidden-accessible')) {
                $vehicle.select2('destroy');
            }
            $vehicle.select2({
                dropdownParent: $editModal,
                width: '100%'
            });
        }

        if ($chauffer.length) {
            if ($chauffer.hasClass('select2-hidden-accessible')) {
                $chauffer.select2('destroy');
            }
            $chauffer.select2({
                dropdownParent: $editModal,
                width: '100%'
            });
        }
    }

    function initShuttleSelect2() {
        $('.shuttle-vehicle-select').each(function () {
            const $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }
            $el.select2({
                dropdownParent: $transportModal,
                width: '100%'
            });
        });

        $('.shuttle-chauffer-select').each(function () {
            const $el = $(this);
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }
            $el.select2({
                dropdownParent: $transportModal,
                width: '100%'
            });
        });
    }

    $transportModal.on('shown.bs.modal', function () {
        initShuttleSelect2();
    });

    $editModal.on('shown.bs.modal', function () {
        initEditSelect2();
    });

    if (createModal) {
        createModal.addEventListener('show.bs.modal', function () {
            if (typeInput) typeInput.value = 'shuttle';
            if (title) title.textContent = 'Add Shuttle Services';

            shuttleBookingsBody.innerHTML = '';
            shuttleBookingsContainer.classList.add('d-none');
            shuttleNoBookings.classList.add('d-none');
            shuttleDateInput.value = '';
        });
    }

    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const b = event.relatedTarget;
            const id = b?.getAttribute('data-id');

            if (deleteForm && id) {
                deleteForm.action = `{{ url('/transport-services') }}/${id}`;
            }

            if (deleteNoteField) {
                deleteNoteField.value = '';
            }
        });
    }

    if (deleteForm) {
        deleteForm.addEventListener('submit', function (e) {
            const note = deleteNoteField?.value.trim() || '';
            if (!note) {
                e.preventDefault();
                alert('Please provide a note for deletion.');
                deleteNoteField?.focus();
            }
        });
    }

    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const b = event.relatedTarget;
            const id = b?.getAttribute('data-id');

            if (!b || !id || !editForm) return;

            editForm.action = `{{ url('/transport-services') }}/${id}`;

            document.getElementById('edit_type').value = b.getAttribute('data-type') || '';
            $('#edit_chauffer_id').val(b.getAttribute('data-chauffer_id') || '').trigger('change');
            document.getElementById('edit_start').value = b.getAttribute('data-start') || '';
            document.getElementById('edit_end').value = b.getAttribute('data-end') || '';
            document.getElementById('edit_pickup').value = b.getAttribute('data-pickup') || '';
            document.getElementById('edit_dropoff').value = b.getAttribute('data-dropoff') || '';
            document.getElementById('edit_passengers').value = b.getAttribute('data-passengers') || '';

            const currentVehicleId = b.getAttribute('data-vehicle_id');
            loadAvailableVehicles('#edit_start', '#edit_end', '#edit_vehicle_id', currentVehicleId);
        });
    }

    async function loadAvailableVehicles(startSelector, endSelector, vehicleSelector, selectedVehicleId = null) {
        const start = $(startSelector).val();
        const end = $(endSelector).val();
        const loaderSelector = '#edit_vehicle_loader';

        if (!start) {
            $(vehicleSelector).html('<option value="">Select vehicle</option>').trigger('change');
            $(loaderSelector).addClass('d-none').text('Loading vehicles...');
            return;
        }

        try {
            $(loaderSelector).removeClass('d-none').text('Loading vehicles...');
            $(vehicleSelector).prop('disabled', true);

            const response = await fetch(`{{ route('transport-services.available-vehicles') }}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
            if (!response.ok) throw new Error('Failed to fetch vehicles');

            const vehicles = await response.json();

            let options = '<option value="">Select vehicle</option>';
            vehicles.forEach(vehicle => {
                const selected = selectedVehicleId && String(selectedVehicleId) === String(vehicle.id) ? 'selected' : '';
                options += `<option value="${vehicle.id}" ${selected}>${vehicle.reg_no}</option>`;
            });

            $(vehicleSelector).html(options).trigger('change');

            if (selectedVehicleId) {
                $(vehicleSelector).val(String(selectedVehicleId)).trigger('change');
            }

            if (vehicles.length === 0) {
                $(loaderSelector).removeClass('d-none').text('No vehicles available for selected dates.');
            } else {
                $(loaderSelector).addClass('d-none').text('Loading vehicles...');
            }
        } catch (error) {
            console.error('Error loading vehicles:', error);
            $(loaderSelector).removeClass('d-none').text('Failed to load vehicles.');
        } finally {
            $(vehicleSelector).prop('disabled', false);
        }
    }

    async function loadAvailableVehiclesForShuttleRow(row, selectedVehicleId = null) {
        const startInput = row.querySelector('.shuttle-start');
        const endInput = row.querySelector('.shuttle-end');
        const vehicleSelect = row.querySelector('.shuttle-vehicle-select');
        const loader = row.querySelector('.shuttle-vehicle-loader');

        if (!startInput || !vehicleSelect) return;

        const start = startInput.value;
        const end = endInput ? endInput.value : '';

        if (!start) {
            vehicleSelect.innerHTML = '<option value="">Select vehicle</option>';
            $(vehicleSelect).trigger('change');
            if (loader) {
                loader.classList.add('d-none');
                loader.textContent = 'Loading vehicles...';
            }
            return;
        }

        try {
            if (loader) {
                loader.classList.remove('d-none');
                loader.textContent = 'Loading vehicles...';
            }

            vehicleSelect.disabled = true;

            const response = await fetch(`{{ route('transport-services.available-vehicles') }}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
            if (!response.ok) throw new Error('Failed to fetch vehicles');

            const vehicles = await response.json();
            let options = '<option value="">Select vehicle</option>';

            vehicles.forEach(vehicle => {
                const selected = selectedVehicleId && String(selectedVehicleId) === String(vehicle.id) ? 'selected' : '';
                options += `<option value="${vehicle.id}" ${selected}>${vehicle.reg_no}</option>`;
            });

            vehicleSelect.innerHTML = options;
            $(vehicleSelect).trigger('change');

            if (selectedVehicleId) {
                $(vehicleSelect).val(String(selectedVehicleId)).trigger('change');
            }

            if (loader) {
                if (vehicles.length === 0) {
                    loader.classList.remove('d-none');
                    loader.textContent = 'No vehicles available for selected dates.';
                } else {
                    loader.classList.add('d-none');
                    loader.textContent = 'Loading vehicles...';
                }
            }
        } catch (error) {
            console.error('Error loading shuttle row vehicles:', error);
            if (loader) {
                loader.classList.remove('d-none');
                loader.textContent = 'Failed to load vehicles.';
            }
        } finally {
            vehicleSelect.disabled = false;
        }
    }

    function toggleShuttleRow(row) {
        const checkbox = row.querySelector('.shuttle-row-check');
        const enabled = !!checkbox?.checked;

        row.querySelectorAll('.shuttle-field').forEach(field => {
            field.disabled = !enabled;
            field.required = enabled;
        });

        row.classList.toggle('table-light', !enabled);
    }

    $(document).on('change', '.shuttle-row-check', function () {
        const row = this.closest('tr');
        if (row) {
            toggleShuttleRow(row);
        }
    });

    $('#edit_start, #edit_end').on('change', function () {
        const selectedVehicleId = $('#edit_vehicle_id').val();
        loadAvailableVehicles('#edit_start', '#edit_end', '#edit_vehicle_id', selectedVehicleId);
    });

    $(document).on('change', '.shuttle-start, .shuttle-end', async function () {
        const row = this.closest('tr');
        if (!row) return;

        const currentVehicleId = $(row).find('.shuttle-vehicle-select').val() || null;
        await loadAvailableVehiclesForShuttleRow(row, currentVehicleId);
    });

    if (loadShuttleBookingsBtn) {
        loadShuttleBookingsBtn.addEventListener('click', async function () {
            const selectedDate = shuttleDateInput?.value || '';

            if (!selectedDate) {
                alert('Please select a shuttle date.');
                shuttleDateInput?.focus();
                return;
            }

            shuttleBookingsBody.innerHTML = '';
            shuttleBookingsContainer.classList.add('d-none');
            shuttleNoBookings.classList.add('d-none');

            loadShuttleBookingsBtn.disabled = true;
            loadShuttleBookingsBtn.textContent = 'Loading...';

            try {
                const res = await fetch(`{{ route('transport-services.shuttle-bookings') }}?date=${encodeURIComponent(selectedDate)}`);
                if (!res.ok) throw new Error('Failed to load shuttle bookings');

                const bookings = await res.json();

                if (!Array.isArray(bookings) || bookings.length === 0) {
                    shuttleNoBookings.classList.remove('d-none');
                    return;
                }

                bookings.forEach((booking, index) => {
                    shuttleBookingsBody.insertAdjacentHTML('beforeend', renderShuttleRow(booking, index));
                });

                shuttleBookingsContainer.classList.remove('d-none');

                initShuttleSelect2();
                attachAutocompleteToClass('.shuttle-pickup');
                attachAutocompleteToClass('.shuttle-dropoff');

                const rows = document.querySelectorAll('#shuttleBookingsBody tr');

                for (let rowIndex = 0; rowIndex < rows.length; rowIndex++) {
                    const row = rows[rowIndex];
                    const booking = bookings[rowIndex];

                    toggleShuttleRow(row);

                    await loadAvailableVehiclesForShuttleRow(
                        row,
                        booking?.existing_vehicle_id || null
                    );

                    if (booking?.existing_employee_id) {
                        $(row).find('.shuttle-chauffer-select').val(String(booking.existing_employee_id)).trigger('change');
                    }
                }
            } catch (e) {
                console.error(e);
                alert('Failed to load shuttle bookings.');
            } finally {
                loadShuttleBookingsBtn.disabled = false;
                loadShuttleBookingsBtn.textContent = 'Load Bookings';
            }
        });
    }

    function renderShuttleRow(booking, index) {
        const tripType = booking.trip_type || '';

        const assignedStart = booking.existing_assigned_start_at
            ? formatDateTimeLocal(booking.existing_assigned_start_at)
            : (tripType === 'arrival'
                ? formatDateTimeLocal(booking.arrival_date)
                : formatDateTimeLocal(booking.departure_date));

        const assignedEnd = booking.existing_assigned_end_at
            ? formatDateTimeLocal(booking.existing_assigned_end_at)
            : '';

        let defaultPickup = 'Seeduwa Office';
        let defaultDropoff = '';
        const bookingPickup = booking.vehicle_pickup || '';

        if (tripType === 'arrival') {
            defaultPickup = bookingPickup || 'Seeduwa Office';
            defaultDropoff = 'Seeduwa Office';
        } else if (tripType === 'departure') {
            defaultPickup = 'Seeduwa Office';
            defaultDropoff = bookingPickup || '';
        } else {
            defaultPickup = bookingPickup || 'Seeduwa Office';
            defaultDropoff = 'Seeduwa Office';
        }

        const pickupValue = booking.existing_pickup_location || defaultPickup;
        const dropoffValue = booking.existing_dropoff_location || defaultDropoff;
        const passengerValue = booking.existing_passenger_count || booking.passengers || 1;
        const noteValue = booking.existing_note || '';
        const isChecked = booking.existing_transport_id ? 'checked' : '';

        return `
            <tr class="shuttle-row">
                <td>
                    <div class="form-check mb-2">
                        <input class="form-check-input shuttle-row-check"
                            type="checkbox"
                            name="shuttle_items[${index}][selected]"
                            value="1"
                            ${isChecked}>
                        <label class="form-check-label fw-semibold">Use</label>
                    </div>

                    <strong>${escapeHtml(booking.booking_number || '-')}</strong>
                    <div><small class="text-muted text-uppercase">${escapeHtml(tripType || '-')}</small></div>

                    <input type="hidden" name="shuttle_items[${index}][rental_id]" value="${booking.id || ''}">
                    <input type="hidden" name="shuttle_items[${index}][trip_code]" value="${escapeHtml(tripType || '')}">
                    <input type="hidden" name="shuttle_items[${index}][transport_service_id]" value="${booking.existing_transport_id || ''}">
                    <input type="hidden" name="shuttle_items[${index}][passenger_count]" value="${escapeHtml(passengerValue)}">
                    <input type="hidden" name="shuttle_items[${index}][note]" value="${escapeHtml(noteValue)}">
                </td>


                <td>
                    <input type="datetime-local"
                        class="form-control shuttle-start shuttle-field"
                        name="shuttle_items[${index}][assigned_start_at]"
                        value="${assignedStart}">
                </td>

                <td>
                    <input type="datetime-local"
                        class="form-control shuttle-end shuttle-field"
                        name="shuttle_items[${index}][assigned_end_at]"
                        value="${assignedEnd}">
                </td>

                <td>
                    <select class="form-select shuttle-vehicle-select shuttle-field"
                        name="shuttle_items[${index}][vehicle_id]">
                        <option value="">Select vehicle</option>
                    </select>
                    <small class="text-muted d-none shuttle-vehicle-loader">Loading vehicles...</small>
                </td>

                <td>
                    <select class="form-select shuttle-chauffer-select shuttle-field"
                        name="shuttle_items[${index}][employee_id]">
                        <option value="">Select chauffer</option>
                        @foreach($chauffers as $c)
                            <option value="{{ $c['employee_id'] }}">
                                {{ $c['preferred_name'] }} ({{ $c['whatsapp_number'] }})
                            </option>
                        @endforeach
                    </select>
                </td>

                <td>
                    <input type="text"
                        class="form-control shuttle-pickup shuttle-field"
                        name="shuttle_items[${index}][pickup_location]"
                        value="${escapeHtml(pickupValue)}">
                </td>

                <td>
                    <input type="text"
                        class="form-control shuttle-dropoff shuttle-field"
                        name="shuttle_items[${index}][dropoff_location]"
                        value="${escapeHtml(dropoffValue)}">
                </td>
            </tr>
        `;
    }

    function formatDateTimeLocal(value) {
        if (!value) return '';

        const s = String(value).trim().replace(' ', 'T');
        const match = s.match(/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2})/);
        return match ? `${match[1]}T${match[2]}` : '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

   window.attachAutocomplete = function (inputId) {
        const input = document.getElementById(inputId);
        if (!input || input.dataset.autocompleteAttached === '1') return;

        const ac = new google.maps.places.Autocomplete(input, {
            componentRestrictions: { country: "lk" },
            fields: ["formatted_address"]
        });

        ac.addListener("place_changed", () => {
            const place = ac.getPlace();
            if (place?.formatted_address) input.value = place.formatted_address;
        });

        input.dataset.autocompleteAttached = '1';
    };

    window.attachAutocompleteToClass = function (selector) {
        document.querySelectorAll(selector).forEach(input => {
            if (input.dataset.autocompleteAttached === '1') return;

            const ac = new google.maps.places.Autocomplete(input, {
                componentRestrictions: { country: "lk" },
                fields: ["formatted_address"]
            });

            ac.addListener("place_changed", () => {
                const place = ac.getPlace();
                if (place?.formatted_address) input.value = place.formatted_address;
            });

            input.dataset.autocompleteAttached = '1';
        });
    };

    window.initSriLankaLocationAutocomplete = function () {
        document.querySelectorAll('#edit_pickup, #edit_dropoff, .shuttle-pickup, .shuttle-dropoff')
            .forEach(input => {
                if (input.dataset.autocompleteAttached === '1') return;

                const ac = new google.maps.places.Autocomplete(input, {
                    componentRestrictions: { country: "lk" }
                });

                input.dataset.autocompleteAttached = '1';
            });
    };

    if (createForm) {
        createForm.addEventListener('submit', function (e) {
            if (!validateShuttleForm()) {
                e.preventDefault();
            }
        });
    }

    function validateShuttleForm() {
        const date = shuttleDateInput?.value || '';
        if (!date) {
            alert('Please select a shuttle date.');
            shuttleDateInput?.focus();
            return false;
        }

        const rows = shuttleBookingsBody?.querySelectorAll('tr') || [];
        if (!rows.length) {
            alert('Please load shuttle bookings first.');
            return false;
        }

        const checkedRows = [...rows].filter(row => row.querySelector('.shuttle-row-check')?.checked);

        if (!checkedRows.length) {
            alert('Please select at least one booking.');
            return false;
        }

        for (const row of checkedRows) {
            const bookingNo = row.querySelector('strong')?.textContent?.trim() || 'Booking';
            const start = row.querySelector('.shuttle-start')?.value || '';
            const end = row.querySelector('.shuttle-end')?.value || '';
            const vehicle = row.querySelector('.shuttle-vehicle-select')?.value || '';
            const chauffer = row.querySelector('.shuttle-chauffer-select')?.value || '';
            const pickup = row.querySelector('.shuttle-pickup')?.value.trim() || '';
            const dropoff = row.querySelector('.shuttle-dropoff')?.value.trim() || '';

            if (!start) {
                alert(`${bookingNo}: assigned start is required.`);
                row.querySelector('.shuttle-start')?.focus();
                return false;
            }

            if (end && end < start) {
                alert(`${bookingNo}: assigned end must be after or equal to assigned start.`);
                row.querySelector('.shuttle-end')?.focus();
                return false;
            }

            if (!vehicle) {
                alert(`${bookingNo}: please select a vehicle.`);
                row.querySelector('.shuttle-vehicle-select')?.focus();
                return false;
            }

            if (!chauffer) {
                alert(`${bookingNo}: please select a chauffer.`);
                row.querySelector('.shuttle-chauffer-select')?.focus();
                return false;
            }

            if (!pickup) {
                alert(`${bookingNo}: pickup location is required.`);
                row.querySelector('.shuttle-pickup')?.focus();
                return false;
            }

            if (!dropoff) {
                alert(`${bookingNo}: dropoff location is required.`);
                row.querySelector('.shuttle-dropoff')?.focus();
                return false;
            }
        }

        return true;
    }
});
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initSriLankaLocationAutocomplete"
    async
    defer>
</script>