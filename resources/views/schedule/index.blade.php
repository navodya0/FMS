@extends('layouts.app')
@section('content')

<style>


    @media (min-width: 1200px) {
        .modal-xl {
            --bs-modal-width: 1700px !important;
        }
    }
</style>

<div class="container py-4">
    @php
        $dept = strtolower(trim(auth()->user()->department ?? ''));
        $isAdmin = strtolower(trim(auth()->user()->name ?? '')) === 'admin';

        $canManageShuttle   = $isAdmin || $dept === 'rent a car department';
        $canManageTransfers = $isAdmin || $dept === 'transfers department';
    @endphp

    <div class="d-flex gap-2 my-3">
        @if($canManageShuttle)
            {{-- <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#transportServiceModal" data-type="shuttle">
                Shuttle
            </button> --}}
        @endif

        @if($canManageTransfers)
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#transportServiceModal" data-type="transfers">
                Add Transfers
            </button>
        @endif
    </div>

    <hr class="my-4">
    <h5 class="mb-2 fw-bold">Transport Services</h5>

    <div class="card">
        <div class="card-body">
            <table class="table table-responsive table-striped align-middle" id="transportServicesTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Vehicle</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Pickup</th>
                        <th>Dropoff</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transportServices as $i => $ts)
                        @php
                            $type = strtolower(trim($ts->type)); 

                            $canEditRow = $isAdmin
                                || ($type === 'shuttle' && $canManageShuttle)
                                || ($type === 'transfers' && $canManageTransfers);
                        @endphp

                        @if($type === 'shuttle')
                            @continue
                        @endif
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="text-capitalize">{{ $ts->type }}</td>
                            <td>{{ $ts->vehicle->reg_no ?? '-' }}</td>
                            <td>{{ optional($ts->assigned_start_at)->format('Y-m-d H:i') }}</td>
                            <td>{{ $ts->assigned_end_at ? $ts->assigned_end_at->format('Y-m-d H:i') : '-' }}</td>
                            <td>{{ $ts->pickup_location }}</td>
                            <td>{{ $ts->dropoff_location }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-warning {{ $canEditRow ? '' : 'disabled' }}"
                                    @if($canEditRow)
                                        data-bs-toggle="modal"
                                        data-bs-target="#editTransportServiceModal"
                                        data-id="{{ $ts->id }}"
                                        data-type="{{ $ts->type }}"
                                        data-vehicle_id="{{ $ts->vehicle_id }}"
                                        data-employee_id="{{ $ts->employee_id }}"
                                        data-start="{{ $ts->assigned_start_at ? $ts->assigned_start_at->format('Y-m-d\TH:i') : '' }}"
                                        data-end="{{ $ts->assigned_end_at ? $ts->assigned_end_at->format('Y-m-d\TH:i') : '' }}"
                                        data-pickup="{{ $ts->pickup_location }}"
                                        data-dropoff="{{ $ts->dropoff_location }}"
                                        data-passengers="{{ $ts->passenger_count }}"
                                        data-trip_code="{{ $ts->trip_code }}"
                                        data-note="{{ $ts->note }}"
                                    @else
                                        type="button"
                                        disabled
                                        title="No permission for this type"
                                    @endif
                                >
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                <button
                                    class="btn btn-sm btn-danger {{ $canEditRow ? '' : 'disabled' }}"
                                    @if($canEditRow)
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteTransportServiceModal"
                                        data-id="{{ $ts->id }}"
                                    @else
                                        type="button"
                                        disabled
                                        title="No permission for this type"
                                    @endif
                                >
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .pac-container {
        z-index: 9999 !important;
    }
</style>

{{-- CREATE MODAL --}}
<div class="modal fade" id="transportServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="transportServiceTitle">Add Transfers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                {{-- ADD TRANSFER TO transfers TABLE --}}
                <div id="transfers-create-section">
                    <h6 class="fw-bold mb-3">Add Transfer Bookings</h6>

                    <form method="POST" action="{{ route('transfers.store') }}" class="border rounded p-3 mb-4" id="multiTransferCreateForm">
                        @csrf

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0" id="newTransfersTable">
                                <thead>
                                    <tr>
                                        <th>Booking Number</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th style="width: 80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="newTransfersBody">
                                    <tr>
                                        <td>
                                            <input type="text" class="form-control" name="transfers[0][booking_number]" required>
                                        </td>
                                        <td>
                                            <input type="date" class="form-control" name="transfers[0][start_date]" required>
                                        </td>
                                        <td>
                                            <input type="date" class="form-control" name="transfers[0][end_date]">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-sm btn-danger remove-transfer-row" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-primary" id="addTransferRowBtn">
                                Add More
                            </button>

                            <button type="submit" class="btn btn-success">
                                Save Transfer Bookings
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ASSIGN TRANSFERS TO transport_services TABLE --}}
                <div id="transfers-assign-section">
                    <h6 class="fw-bold mb-3">Assign Added Transfers</h6>

                    <form method="POST" action="{{ route('transport-services.store') }}" id="transferAssignForm">
                        @csrf
                        <input type="hidden" name="type" value="transfers">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead>
                                    <tr>
                                        <th>Select</th>
                                        <th>Booking No</th>
                                        <th>Assigned Start</th>
                                        <th>Assigned End</th>
                                        <th class="text-center" style="width: 90px;">No Vehicle</th>
                                        <th>Vehicle</th>
                                        <th>Chauffer</th>
                                        <th>Pickup</th>
                                        <th>Dropoff</th>
                                        <th style="width: 110px;">Passengers</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transfers as $i => $transfer)
                                        <tr class="transfer-row">
                                            <td class="text-center">
                                                <input type="checkbox"
                                                    class="form-check-input transfer-row-check"
                                                    name="transfer_items[{{ $i }}][selected]"
                                                    value="1"
                                                    checked>
                                            </td>

                                            <td>
                                                {{ $transfer->booking_number }}
                                                <input type="hidden" name="transfer_items[{{ $i }}][transfer_id]" value="{{ $transfer->id }}">
                                            </td>

                                            <td>
                                                <input type="datetime-local"
                                                    class="form-control transfer-start transfer-field"
                                                    name="transfer_items[{{ $i }}][assigned_start_at]"
                                                    required>
                                            </td>

                                            <td>
                                                <input type="datetime-local"
                                                    class="form-control transfer-end transfer-field"
                                                    name="transfer_items[{{ $i }}][assigned_end_at]">
                                            </td>

                                            <td class="text-center">
                                                <input type="hidden"
                                                    name="transfer_items[{{ $i }}][is_vehicle_assigned]"
                                                    value="1"
                                                    class="transfer-is-vehicle-assigned-hidden">

                                                <input type="checkbox"
                                                    class="form-check-input transfer-without-vehicle-toggle"
                                                    title="Assign without vehicle">
                                            </td>

                                            <td class="transfer-vehicle-wrapper">
                                                <select class="form-select transfer-vehicle-select transfer-field"
                                                        name="transfer_items[{{ $i }}][vehicle_id]"
                                                        required>
                                                    <option value="">Select vehicle</option>
                                                </select>
                                                <small class="text-muted d-none transfer-vehicle-loader">Loading vehicles...</small>
                                            </td>

                                            <td class="transfer-vehicle-type-wrapper d-none">
                                                <select class="form-select transfer-vehicle-type-select transfer-field"
                                                        name="transfer_items[{{ $i }}][vehicle_type_id]">
                                                    <option value="">Select type</option>
                                                    @foreach($vehicleTypes as $vehicleType)
                                                        <option value="{{ $vehicleType->id }}">{{ $vehicleType->type_name }}</option>
                                                    @endforeach
                                                </select>
                                            </td>

                                            <td>
                                                <select class="form-select transfer-chauffer-select transfer-field"
                                                        name="transfer_items[{{ $i }}][employee_id]"
                                                        required>
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
                                                    class="form-control transfer-pickup transfer-field"
                                                    name="transfer_items[{{ $i }}][pickup_location]"
                                                    value="Seeduwa Office"
                                                    required>
                                            </td>

                                            <td>
                                                <input type="text"
                                                    class="form-control transfer-dropoff transfer-field"
                                                    name="transfer_items[{{ $i }}][dropoff_location]"
                                                    required>
                                            </td>

                                            <td>
                                                <input type="number"
                                                    class="form-control transfer-field"
                                                    name="transfer_items[{{ $i }}][passenger_count]"
                                                    min="1"
                                                    value="1">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 text-end">
                            <button class="btn btn-primary" type="submit">Save Assigned Transfers</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- EDIT MODAL --}}
<div class="modal fade" id="editTransportServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST" id="editTransportServiceForm">
            @csrf
            @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Transport Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="type" id="edit_type">
                <input type="hidden" name="status" value="ASSIGNED">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Vehicle<span class="text-danger">*</span></label>
                        <select class="form-select vehicle-select-edit" name="vehicle_id" id="edit_vehicle_id" required>                            
                            <option value="">Select vehicle</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">{{ $vehicle->reg_no }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Chauffer<span class="text-danger">*</span></label>
                        <select class="form-select chauffer-select-edit" name="employee_id" id="edit_chauffer_id" required>
                            <option value="">Select chauffer</option>
                            @foreach($chauffers as $c)
                                <option 
                                    value="{{ $c['employee_id'] }}"
                                    data-employee="{{ $c['employee_id'] }}"
                                >
                                    {{ $c['preferred_name'] }} ({{ $c['whatsapp_number'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Assigned Start<span class="text-danger">*</span></label>
                        <input class="form-control" type="datetime-local" name="assigned_start_at" id="edit_start" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Assigned End</label>
                        <input class="form-control" type="datetime-local" name="assigned_end_at" id="edit_end">
                    </div>

                   <div class="col-md-6">
                        <label class="form-label">Pickup<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="edit_pickup" name="pickup_location" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Dropoff<span class="text-danger">*</span></label>
                        <input class="form-control" type="text" id="edit_dropoff" name="dropoff_location" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Passengers<span class="text-danger">*</span></label>
                        <input class="form-control" type="number" name="passenger_count" id="edit_passengers" min="1" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-warning" type="submit">Update</button>
            </div>
        </form>
    </div>
</div>

{{-- DELETE CONFIRMATION MODAL --}}
<div class="modal fade" id="deleteTransportServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="POST" id="deleteTransportServiceForm">
            @csrf
            @method('DELETE')
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Delete Transport Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete this transport service?</p>
                <label class="form-label">Note (Required)</label>
                <textarea class="form-control" name="delete_note" id="delete_note" rows="3" placeholder="Please provide a reason for deletion..." required></textarea>
                <small class="text-muted d-block mt-2">A note is required for audit purposes.</small>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" type="submit" id="confirmDeleteBtn">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const $transportModal = $('#transportServiceModal');
        const $editModal = $('#editTransportServiceModal');

        const createModal = document.getElementById('transportServiceModal');
        const editModal = document.getElementById('editTransportServiceModal');
        const deleteModal = document.getElementById('deleteTransportServiceModal');

        const transferAssignForm = document.getElementById('transferAssignForm');
        const editForm = document.getElementById('editTransportServiceForm');
        const deleteForm = document.getElementById('deleteTransportServiceForm');
        const deleteNoteField = document.getElementById('delete_note');
        const title = document.getElementById('transportServiceTitle');

        const addTransferRowBtn = document.getElementById('addTransferRowBtn');
        const newTransfersBody = document.getElementById('newTransfersBody');

        if ($('#transportServicesTable').length) {
            $('#transportServicesTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[3, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }]
            });
        }

        function initSelect2($elements, parent) {
            $elements.each(function () {
                const $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    $el.select2('destroy');
                }
                $el.select2({
                    dropdownParent: parent,
                    width: '100%'
                });
            });
        }

        function initTransferSelect2() {
            initSelect2($('.transfer-vehicle-select'), $transportModal);
            initSelect2($('.transfer-vehicle-type-select'), $transportModal);
            initSelect2($('.transfer-chauffer-select'), $transportModal);
        }

        function initEditSelect2() {
            initSelect2($('#edit_vehicle_id'), $editModal);
            initSelect2($('#edit_chauffer_id'), $editModal);
        }

        function attachAutocompleteToClass(selector) {
            document.querySelectorAll(selector).forEach(input => {
                if (input.dataset.autocompleteAttached === '1') return;

                const autocomplete = new google.maps.places.Autocomplete(input, {
                    componentRestrictions: { country: "lk" },
                    fields: ["formatted_address", "name", "geometry"]
                });

                autocomplete.addListener("place_changed", function () {
                    const place = autocomplete.getPlace();
                    input.value = place.formatted_address || place.name || input.value;
                });

                input.dataset.autocompleteAttached = '1';
            });
        }

        window.initSriLankaLocationAutocomplete = function () {
            ['#edit_pickup', '#edit_dropoff'].forEach(sel => {
                const el = document.querySelector(sel);
                if (!el || el.dataset.autocompleteAttached === '1') return;

                const autocomplete = new google.maps.places.Autocomplete(el, {
                    componentRestrictions: { country: "lk" },
                    fields: ["formatted_address", "name", "geometry"]
                });

                autocomplete.addListener("place_changed", function () {
                    const place = autocomplete.getPlace();
                    el.value = place.formatted_address || place.name || el.value;
                });

                el.dataset.autocompleteAttached = '1';
            });

            attachAutocompleteToClass('.transfer-pickup');
            attachAutocompleteToClass('.transfer-dropoff');
        };

        async function fetchAvailableVehicles(start, end) {
            const response = await fetch(`{{ route('transport-services.available-vehicles') }}?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end || '')}`);
            if (!response.ok) throw new Error('Failed to fetch vehicles');
            return await response.json();
        }

        async function loadAvailableVehiclesForEdit(selectedVehicleId = null) {
            const start = $('#edit_start').val();
            const end = $('#edit_end').val();

            if (!start) {
                $('#edit_vehicle_id').html('<option value="">Select vehicle</option>').trigger('change');
                return;
            }

            try {
                $('#edit_vehicle_id').prop('disabled', true);

                const vehicles = await fetchAvailableVehicles(start, end);
                let options = '<option value="">Select vehicle</option>';

                vehicles.forEach(vehicle => {
                    const selected = selectedVehicleId && String(selectedVehicleId) === String(vehicle.id) ? 'selected' : '';
                    options += `<option value="${vehicle.id}" ${selected}>${vehicle.reg_no}</option>`;
                });

                $('#edit_vehicle_id').html(options).trigger('change');

                if (selectedVehicleId) {
                    $('#edit_vehicle_id').val(String(selectedVehicleId)).trigger('change');
                }
            } catch (error) {
                console.error(error);
            } finally {
                $('#edit_vehicle_id').prop('disabled', false);
            }
        }

        async function loadAvailableVehiclesForTransferRow(row, selectedVehicleId = null) {
            const withoutVehicle = row.querySelector('.transfer-without-vehicle-toggle')?.checked;
            const start = row.querySelector('.transfer-start')?.value || '';
            const end = row.querySelector('.transfer-end')?.value || '';
            const vehicleSelect = row.querySelector('.transfer-vehicle-select');
            const loader = row.querySelector('.transfer-vehicle-loader');

            if (!vehicleSelect || withoutVehicle) return;

            if (!start) {
                vehicleSelect.innerHTML = '<option value="">Select vehicle</option>';
                $(vehicleSelect).trigger('change');
                loader?.classList.add('d-none');
                return;
            }

            try {
                loader?.classList.remove('d-none');
                vehicleSelect.disabled = true;

                const vehicles = await fetchAvailableVehicles(start, end);
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
                    loader.classList.toggle('d-none', vehicles.length > 0);
                    loader.textContent = vehicles.length ? 'Loading vehicles...' : 'No vehicles available for selected dates.';
                }
            } catch (error) {
                console.error(error);
                if (loader) {
                    loader.classList.remove('d-none');
                    loader.textContent = 'Failed to load vehicles.';
                }
            } finally {
                vehicleSelect.disabled = false;
            }
        }

        function toggleTransferVehicleMode(row) {
            const withoutVehicle = row.querySelector('.transfer-without-vehicle-toggle')?.checked;
            const vehicleWrapper = row.querySelector('.transfer-vehicle-wrapper');
            const vehicleTypeWrapper = row.querySelector('.transfer-vehicle-type-wrapper');
            const vehicleSelect = row.querySelector('.transfer-vehicle-select');
            const vehicleTypeSelect = row.querySelector('.transfer-vehicle-type-select');
            const hiddenAssigned = row.querySelector('.transfer-is-vehicle-assigned-hidden');
            const loader = row.querySelector('.transfer-vehicle-loader');

            if (withoutVehicle) {
                vehicleWrapper?.classList.add('d-none');
                vehicleTypeWrapper?.classList.remove('d-none');
                loader?.classList.add('d-none');

                if (vehicleSelect) {
                    $(vehicleSelect).val('').trigger('change');
                    vehicleSelect.required = false;
                }

                if (vehicleTypeSelect) {
                    vehicleTypeSelect.required = true;
                }

                if (hiddenAssigned) {
                    hiddenAssigned.value = '0';
                }
            } else {
                vehicleWrapper?.classList.remove('d-none');
                vehicleTypeWrapper?.classList.add('d-none');

                if (vehicleSelect) {
                    vehicleSelect.required = true;
                }

                if (vehicleTypeSelect) {
                    vehicleTypeSelect.required = false;
                    $(vehicleTypeSelect).val('').trigger('change');
                }

                if (hiddenAssigned) {
                    hiddenAssigned.value = '1';
                }

                loadAvailableVehiclesForTransferRow(row, $(vehicleSelect).val() || null);
            }
        }

        function toggleTransferRow(row) {
            const enabled = !!row.querySelector('.transfer-row-check')?.checked;

            row.querySelectorAll('.transfer-field, .transfer-without-vehicle-toggle').forEach(field => {
                field.disabled = !enabled;
            });

            row.classList.toggle('table-light', !enabled);

            if (enabled) {
                toggleTransferVehicleMode(row);
            }
        }

        function validateTransferForm() {
            const rows = [...document.querySelectorAll('.transfer-row')].filter(
                row => row.querySelector('.transfer-row-check')?.checked
            );

            if (!rows.length) {
                alert('Please select at least one transfer.');
                return false;
            }

            for (const row of rows) {
                const bookingNo = row.children[1]?.innerText.trim() || 'Transfer';
                const start = row.querySelector('.transfer-start')?.value || '';
                const end = row.querySelector('.transfer-end')?.value || '';
                const withoutVehicle = row.querySelector('.transfer-without-vehicle-toggle')?.checked;
                const vehicle = row.querySelector('.transfer-vehicle-select')?.value || '';
                const vehicleType = row.querySelector('.transfer-vehicle-type-select')?.value || '';
                const chauffer = row.querySelector('.transfer-chauffer-select')?.value || '';
                const pickup = row.querySelector('.transfer-pickup')?.value.trim() || '';
                const dropoff = row.querySelector('.transfer-dropoff')?.value.trim() || '';
                const passengers = row.querySelector('[name*="[passenger_count]"]')?.value || '';

                if (!start) {
                    alert(`${bookingNo}: assigned start is required.`);
                    row.querySelector('.transfer-start')?.focus();
                    return false;
                }

                if (end && end < start) {
                    alert(`${bookingNo}: assigned end must be after or equal to assigned start.`);
                    row.querySelector('.transfer-end')?.focus();
                    return false;
                }

                if (withoutVehicle) {
                    if (!vehicleType) {
                        alert(`${bookingNo}: please select a vehicle type.`);
                        row.querySelector('.transfer-vehicle-type-select')?.focus();
                        return false;
                    }
                } else if (!vehicle) {
                    alert(`${bookingNo}: please select a vehicle.`);
                    row.querySelector('.transfer-vehicle-select')?.focus();
                    return false;
                }

                if (!chauffer) {
                    alert(`${bookingNo}: please select a chauffer.`);
                    row.querySelector('.transfer-chauffer-select')?.focus();
                    return false;
                }

                if (!pickup) {
                    alert(`${bookingNo}: pickup location is required.`);
                    row.querySelector('.transfer-pickup')?.focus();
                    return false;
                }

                if (!dropoff) {
                    alert(`${bookingNo}: dropoff location is required.`);
                    row.querySelector('.transfer-dropoff')?.focus();
                    return false;
                }

                if (!passengers || Number(passengers) < 1) {
                    alert(`${bookingNo}: passenger count must be at least 1.`);
                    row.querySelector('[name*="[passenger_count]"]')?.focus();
                    return false;
                }
            }

            return true;
        }

        function addNewTransferRow() {
            const index = newTransfersBody.querySelectorAll('tr').length;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td><input type="text" class="form-control" name="transfers[${index}][booking_number]" required></td>
                <td><input type="date" class="form-control" name="transfers[${index}][start_date]" required></td>
                <td><input type="date" class="form-control" name="transfers[${index}][end_date]"></td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger remove-transfer-row">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            newTransfersBody.appendChild(row);
            updateRemoveButtons();
        }

        function updateRemoveButtons() {
            const rows = newTransfersBody.querySelectorAll('tr');
            rows.forEach(row => {
                const btn = row.querySelector('.remove-transfer-row');
                if (btn) btn.disabled = rows.length === 1;
            });
        }

        if (createModal) {
            createModal.addEventListener('show.bs.modal', function (event) {
                const type = event.relatedTarget?.getAttribute('data-type') || 'transfers';
                title.textContent = type === 'shuttle' ? 'Add Shuttle Service' : 'Add Transfers Service';

                initTransferSelect2();
                attachAutocompleteToClass('.transfer-pickup');
                attachAutocompleteToClass('.transfer-dropoff');

                document.querySelectorAll('.transfer-row').forEach(toggleTransferRow);
            });
        }

        $transportModal.on('shown.bs.modal', function () {
            initTransferSelect2();
            attachAutocompleteToClass('.transfer-pickup');
            attachAutocompleteToClass('.transfer-dropoff');
            document.querySelectorAll('.transfer-row').forEach(toggleTransferRow);
        });

        $editModal.on('shown.bs.modal', initEditSelect2);

        $(document).on('change', '.transfer-row-check', function () {
            toggleTransferRow(this.closest('tr'));
        });

        $(document).on('change', '.transfer-without-vehicle-toggle', function () {
            toggleTransferVehicleMode(this.closest('tr'));
        });

        $(document).on('change', '.transfer-start, .transfer-end', function () {
            const row = this.closest('tr');
            if (row && !row.querySelector('.transfer-without-vehicle-toggle')?.checked) {
                loadAvailableVehiclesForTransferRow(row, $(row).find('.transfer-vehicle-select').val() || null);
            }
        });

        $('#edit_start, #edit_end').on('change', function () {
            loadAvailableVehiclesForEdit($('#edit_vehicle_id').val());
        });

        if (editModal) {
            editModal.addEventListener('show.bs.modal', function (event) {
                const b = event.relatedTarget;
                const id = b?.getAttribute('data-id');
                if (!b || !id || !editForm) return;

                editForm.action = `{{ url('/transport-services') }}/${id}`;
                document.getElementById('edit_type').value = b.getAttribute('data-type') || '';
                $('#edit_chauffer_id').val(b.getAttribute('data-employee_id') || '').trigger('change');
                $('#edit_start').val(b.getAttribute('data-start') || '');
                $('#edit_end').val(b.getAttribute('data-end') || '');
                $('#edit_pickup').val(b.getAttribute('data-pickup') || '');
                $('#edit_dropoff').val(b.getAttribute('data-dropoff') || '');
                $('#edit_passengers').val(b.getAttribute('data-passengers') || '');

                loadAvailableVehiclesForEdit(b.getAttribute('data-vehicle_id'));
            });
        }

        if (deleteModal) {
            deleteModal.addEventListener('show.bs.modal', function (event) {
                const id = event.relatedTarget?.getAttribute('data-id');
                if (deleteForm && id) deleteForm.action = `{{ url('/transport-services') }}/${id}`;
                if (deleteNoteField) deleteNoteField.value = '';
            });
        }

        if (deleteForm) {
            deleteForm.addEventListener('submit', function (e) {
                if (!(deleteNoteField?.value.trim())) {
                    e.preventDefault();
                    alert('Please provide a note for deletion.');
                    deleteNoteField?.focus();
                }
            });
        }

        if (transferAssignForm) {
            transferAssignForm.addEventListener('submit', function (e) {
                if (!validateTransferForm()) {
                    e.preventDefault();
                }
            });
        }

        if (addTransferRowBtn) {
            addTransferRowBtn.addEventListener('click', addNewTransferRow);
        }

        $(document).on('click', '.remove-transfer-row', function () {
            $(this).closest('tr').remove();
            updateRemoveButtons();
        });

        document.querySelectorAll('.transfer-row').forEach(toggleTransferRow);
        updateRemoveButtons();
    });
</script>

<script
    src="https://maps.googleapis.com/maps/api/js?key={{ env('GOOGLE_MAPS_API_KEY') }}&libraries=places&callback=initSriLankaLocationAutocomplete"
    async
    defer>
</script>

@endsection
