<style>
.flatpickr-day.booked-rental-date,
.flatpickr-day.booked-rental-date:hover {
    background: #dc3545 !important;
    color: #fff !important;
    border-color: #dc3545 !important;
    text-decoration: line-through;
}
</style>

<div class="modal fade" id="freezeVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('vehicle-freezes.store') }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">❄ Freeze Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                @php
                    $today = \Carbon\Carbon::today()->format('Y-m-d');
                    $tomorrow = \Carbon\Carbon::tomorrow()->format('Y-m-d');
                @endphp
                
                <div class="modal-body row g-3">

                    <div class="col-md-12">
                        <div id="bookedRentalNote" class="alert alert-warning d-none mb-0">
                            <strong>Note:</strong> Marked dates are already booked rentals.
                                    {{-- <div id="bookedRentalList" class="small mt-2"></div> --}}

                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vehicle<span class="text-danger">*</span></label>
                        {{-- <select name="vehicle_id" class="form-select select2" required> --}}
                            <select name="vehicle_id" id="freeze_vehicle_id" class="form-select select2" required>
                            <option value="">Select Vehicle</option>
                            @foreach($vehicles as $vehicle)
                                <option value="{{ $vehicle->id }}">
                                    {{ $vehicle->reg_no }} - {{ $vehicle->make }} {{ $vehicle->model }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Start Date<span class="text-danger">*</span></label>
                        <input type="text" id="freeze_start_date" name="start_date" class="form-control" required>

                    </div>

                    <div class="col-md-3">
                        <label class="form-label">End Date<span class="text-danger">*</span></label>
                        <input type="text" id="freeze_end_date" name="end_date" class="form-control" required>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        
                        <select name="reason" id="reasonSelect" class="form-control" required>
                            <option value="">-- Select Reason --</option>
                            <option value="need more time for vehicle repair">Need more time for vehicle repair</option>
                            <option value="need more time for vehicle maintenance">Need more time for vehicle maintenance</option>
                            <option value="need more time for police clearance">Need more time for police clearance</option>
                            <option value="need more time for the insurance DR approval">Need more time for the insurance DR approval</option>
                            <option value="owner requested more time for the repair">Owner requested more time for the repair</option>
                            <option value="owner requested the vehicle for his personal use">Owner requested the vehicle for his personal use</option>
                            <option value="personal use for company staff">Personal use for company staff</option>
                            <option value="Other">Other</option>
                        </select>
                        <input type="text" name="other_reason" id="otherReasonInput" class="form-control mt-2" placeholder="Type reason" style="display: none;">
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Freeze Vehicle</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')



<script>
    $(document).on('shown.bs.modal', '#freezeVehicleModal', function () {
        const $select = $('#freeze_vehicle_id');

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({
            dropdownParent: $('#freezeVehicleModal'),
            placeholder: 'Search vehicle...',
            allowClear: true,
            width: '100%'
        });
    });
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const noteBox = document.getElementById('bookedRentalNote');
    const listBox = document.getElementById('bookedRentalList');

    let disabledDates = [];

    function paintBookedDates(dObj, dStr, fp, dayElem) {
        const date = flatpickr.formatDate(dayElem.dateObj, 'Y-m-d');

        if (disabledDates.includes(date)) {
            dayElem.classList.add('booked-rental-date');
            dayElem.title = 'Booked rental';
        }
    }

    const startPicker = flatpickr('#freeze_start_date', {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        disable: [],
        onDayCreate: paintBookedDates
    });

    const endPicker = flatpickr('#freeze_end_date', {
        dateFormat: 'Y-m-d',
        minDate: 'today',
        disable: [],
        onDayCreate: paintBookedDates
    });

    function resetBookedInfo() {
        disabledDates = [];

        startPicker.clear();
        endPicker.clear();

        startPicker.set('disable', []);
        endPicker.set('disable', []);

        if (noteBox) noteBox.classList.add('d-none');
        if (listBox) listBox.innerHTML = '';

        startPicker.redraw();
        endPicker.redraw();
    }

    function loadBookedDates(vehicleId) {
        resetBookedInfo();

        if (!vehicleId) return;

        fetch(`/vehicle-freezes/${vehicleId}/booked-dates`)
            .then(res => {
                if (!res.ok) throw new Error('Route not found or server error');
                return res.json();
            })
            .then(data => {
                disabledDates = data.disabled_dates || [];
                const bookedRanges = data.booked_ranges || [];

                startPicker.set('disable', disabledDates);
                endPicker.set('disable', disabledDates);

                startPicker.redraw();
                endPicker.redraw();

                if (bookedRanges.length && noteBox) {
                    noteBox.classList.remove('d-none');

                    if (listBox) {
                        listBox.innerHTML = bookedRanges.map(r => `
                            <div>
                                <span class="badge bg-danger">${r.booking_number}</span>
                                ${r.from} to ${r.to}
                            </div>
                        `).join('');
                    }
                }
            })
            .catch(err => {
                console.error('Booked dates fetch error:', err);
            });
    }

    $('#freeze_vehicle_id').on('change select2:select', function () {
        loadBookedDates(this.value);
    });
});
</script>

@endpush
