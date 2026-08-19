<div class="modal fade" id="dailyRecordModal" tabindex="-1" aria-labelledby="dailyRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold" id="dailyRecordModalLabel">Daily Vehicle Record</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                {{-- Date Filter --}}
                <div class="row align-items-end mb-3">
                    <div class="col-md-5">
                        <label for="dailyRecordDate" class="form-label fw-semibold mb-1">
                            <i class="bi bi-calendar3"></i> Select Date
                        </label>
                        <input type="date" id="dailyRecordDate" class="form-control form-control-sm"
                               value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-4 d-flex gap-2 mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-success" id="dailyRecordFilterBtn">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="dailyRecordTodayBtn">
                            <i class="bi bi-arrow-counterclockwise"></i> Today
                        </button>
                    </div>
                </div>

                <hr class="mt-0 mb-3">

                {{-- Loading Spinner --}}
                <div id="dailyRecordSpinner" class="text-center py-4 d-none">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>

                {{-- Tables Container --}}
                <div id="dailyRecordContent">
                    {{-- Today Arrivals --}}
                    <h6 class="fw-bold text-success mb-2" id="arrivalsHeading">
                        🚗 Today Arrivals (<span id="arrivalsCount">{{ count($todayArrivals) }}</span>)
                    </h6>                
                    <div class="table-responsive mb-4">
                        <table class="table table-sm table-bordered align-middle" id="todayArrivalsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking No</th>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Company</th>
                                    <th>Arrival Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($todayArrivals as $rental)
                                    <tr>
                                        <td>{{ $rental->booking_number ?? '-' }}</td>
                                        <td>{{ $rental->vehicle->reg_no ?? '-' }}</td>
                                        <td>{{ $rental->salutation ?? '-' }} {{ $rental->driver_name ?? '-' }}</td>
                                        <td>{{ $rental->vehicle->vehicleType->type_name ?? '-' }}</td>
                                        <td>{{ $rental->company->name ?? '-' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($rental->arrival_date)->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Today Departures --}}
                    <h6 class="fw-bold text-danger mb-2" id="departuresHeading">
                        🚙 Today Departures (<span id="departuresCount">{{ count($todayDepartures) }}</span>)
                    </h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle" id="todayDeparturesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Booking No</th>
                                    <th>Vehicle</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Company</th>
                                    <th>Departure Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($todayDepartures as $rental)
                                    @php
                                        $departure =
                                            $rental->emer_departure_date ?? $rental->departure_date;
                                    @endphp
                                    <tr>
                                        <td>{{ $rental->booking_number ?? $rental->emer_booking_number ?? '-' }}</td>
                                        <td>{{ $rental->vehicle->reg_no ?? '-' }}</td>
                                        <td>{{ $rental->salutation ?? '-' }} {{ $rental->driver_name ?? '-' }}</td>
                                        <td>{{ $rental->vehicle->vehicleType->type_name ?? '-' }}</td>
                                        <td>{{ $rental->company->name ?? '-' }}</td>
                                        <td>{{ \Carbon\Carbon::parse($departure)->format('d M Y') }}</td>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let arrivalsTable, departuresTable;
        const todayStr = '{{ now()->toDateString() }}';

        function initOrReinitDataTables() {
            // Destroy existing DataTable instances if they exist
            if ($.fn.DataTable.isDataTable('#todayArrivalsTable')) {
                $('#todayArrivalsTable').DataTable().destroy();
            }
            if ($.fn.DataTable.isDataTable('#todayDeparturesTable')) {
                $('#todayDeparturesTable').DataTable().destroy();
            }

            arrivalsTable = $('#todayArrivalsTable').DataTable({
                pageLength: 5,
                lengthChange: false,
                ordering: true,
                searching: true,
                info: false,
                autoWidth: false
            });

            departuresTable = $('#todayDeparturesTable').DataTable({
                pageLength: 5,
                lengthChange: false,
                ordering: true,
                searching: true,
                info: false,
                autoWidth: false
            });

            arrivalsTable.columns.adjust();
            departuresTable.columns.adjust();
        }

        function updateHeadingLabel(date) {
            const selected = new Date(date + 'T00:00:00');
            const today = new Date(todayStr + 'T00:00:00');
            const isToday = selected.toDateString() === today.toDateString();
            const label = isToday
                ? 'Today'
                : selected.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });

            $('#arrivalsHeading').html('🚗 ' + label + ' Arrivals (<span id="arrivalsCount">' + $('#arrivalsCount').text() + '</span>)');
            $('#departuresHeading').html('🚙 ' + label + ' Departures (<span id="departuresCount">' + $('#departuresCount').text() + '</span>)');
        }

        function loadDailyRecord(date) {
            $('#dailyRecordSpinner').removeClass('d-none');
            $('#dailyRecordContent').addClass('opacity-50');

            $.ajax({
                url: '{{ route("vehicle-bookings.dailyRecord") }}',
                data: { date: date },
                success: function (res) {
                    // Destroy existing DataTables before replacing tbody
                    if ($.fn.DataTable.isDataTable('#todayArrivalsTable')) {
                        $('#todayArrivalsTable').DataTable().destroy();
                    }
                    if ($.fn.DataTable.isDataTable('#todayDeparturesTable')) {
                        $('#todayDeparturesTable').DataTable().destroy();
                    }

                    // Rebuild arrivals tbody
                    let aRows = '';
                    res.arrivals.forEach(function (r) {
                        aRows += '<tr>' +
                            '<td>' + r.booking_number + '</td>' +
                            '<td>' + r.reg_no + '</td>' +
                            '<td>' + r.customer + '</td>' +
                            '<td>' + r.type + '</td>' +
                            '<td>' + r.company + '</td>' +
                            '<td>' + r.date + '</td>' +
                            '</tr>';
                    });
                    $('#todayArrivalsTable tbody').html(aRows);
                    $('#arrivalsCount').text(res.arrivals.length);

                    // Rebuild departures tbody
                    let dRows = '';
                    res.departures.forEach(function (r) {
                        dRows += '<tr>' +
                            '<td>' + r.booking_number + '</td>' +
                            '<td>' + r.reg_no + '</td>' +
                            '<td>' + r.customer + '</td>' +
                            '<td>' + r.type + '</td>' +
                            '<td>' + r.company + '</td>' +
                            '<td>' + r.date + '</td>' +
                            '</tr>';
                    });
                    $('#todayDeparturesTable tbody').html(dRows);
                    $('#departuresCount').text(res.departures.length);

                    updateHeadingLabel(date);
                    initOrReinitDataTables();
                },
                error: function () {
                    alert('Failed to load daily records. Please try again.');
                },
                complete: function () {
                    $('#dailyRecordSpinner').addClass('d-none');
                    $('#dailyRecordContent').removeClass('opacity-50');
                }
            });
        }

        // Modal shown – initialize DataTables for the server-rendered "today" data
        $('#dailyRecordModal').on('shown.bs.modal', function () {
            initOrReinitDataTables();
        });

        // Filter button
        $('#dailyRecordFilterBtn').on('click', function () {
            const date = $('#dailyRecordDate').val();
            if (date) {
                loadDailyRecord(date);
            }
        });

        // Today button – reset to today
        $('#dailyRecordTodayBtn').on('click', function () {
            $('#dailyRecordDate').val(todayStr);
            loadDailyRecord(todayStr);
        });
    });
</script>