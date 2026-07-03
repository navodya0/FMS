<style>
    .pac-container {
        z-index: 9999 !important;
    }

    @media (min-width: 1200px) {
        .modal-xl {
            --bs-modal-width: 1700px !important;
        }
    }
</style>

{{-- CREATE MODAL --}}
<div class="modal fade" id="transportServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <form class="modal-content" method="POST" action="{{ route('transport-services.store') }}">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="transportServiceTitle">Add Shuttle Services</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="type" id="ts_type" value="shuttle">

                <div id="shuttleBulkFields">
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Date<span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="shuttle_service_date" required>
                        </div>

                        <div class="col-md-3 d-flex align-items-end">
                            <button type="button" class="btn btn-primary w-50" id="loadShuttleBookingsBtn">
                                Load Bookings
                            </button>
                        </div>
                    </div>

                    <div id="shuttleBookingsContainer" class="d-none">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking</th>
                                        <th>Assigned Start</th>
                                        <th>Vehicle</th>
                                        <th>Chauffer</th>
                                        <th>Pickup</th>
                                        <th>Dropoff</th>
                                    </tr>
                                </thead>
                                <tbody id="shuttleBookingsBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="shuttleNoBookings" class="alert alert-info d-none mb-0">
                        No arrival or departure bookings found for the selected date.
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary" type="submit">Save</button>
            </div>
        </form>
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

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Assigned Start<span class="text-danger">*</span></label>
                        <input class="form-control" type="datetime-local" name="assigned_start_at" id="edit_start" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Assigned End</label>
                        <input class="form-control" type="datetime-local" name="assigned_end_at" id="edit_end">
                    </div>

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