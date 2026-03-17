@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm border-0 p-4">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
            <h4 class="fw-bold mb-0">Fuel Barrels</h4>

            <form action="{{ route('barrels.store') }}" method="POST" class="d-flex align-items-center gap-2">
                @csrf
                <select name="capacity" class="form-select" required>
                    <option value="20">20L</option>
                    <option value="25">25L</option>
                </select>

                <button type="submit" class="btn btn-primary">
                    Add
                </button>
            </form>
        </div>

        <div class="row" id="barrel-container">
            @forelse($barrels as $barrel)
                <div class="col-md-3 mb-4">
                    <div class="card text-center shadow-md  h-100" style="border: 2px solid rgb(3, 49, 84)">
                        <div class="card-body d-flex flex-column justify-content-center">
                            <h5 class="fw-bold mb-2">
                                Barrel {{ $barrel->barrel_number }} :
                                <span class="text-muted" style="font-size: 14px;">{{ $barrel->capacity }}L</span>
                            </h5>

                            @php
                                $percentage = ($barrel->capacity > 0) 
                                    ? ($barrel->current_fuel / $barrel->capacity) * 100 
                                    : 0;
                            @endphp

                            <div class="mb-3">
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar 
                                        {{ $percentage > 60 ? 'bg-success' : ($percentage > 30 ? 'bg-warning' : 'bg-danger') }}"
                                        style="width: {{ $percentage }}%">
                                    </div>
                                </div>
                                <small>{{ number_format($barrel->current_fuel,2) }} / {{ $barrel->capacity }} L</small>
                            </div>

                            <div class="d-flex justify-content-center gap-2 mb-3">
                                <button type="button" class="btn btn-sm btn-success"data-bs-toggle="modal"data-bs-target="#fuelActionModal"onclick="openFuelModal('refill', {{ $barrel->id }}, {{ $barrel->barrel_number }}, {{ $barrel->capacity }})" >
                                    Refill
                                </button>

                                <button type="button" class="btn btn-sm btn-warning"data-bs-toggle="modal"data-bs-target="#fuelActionModal"onclick="openFuelModal('take', {{ $barrel->id }}, {{ $barrel->barrel_number }}, {{ $barrel->capacity }})" >
                                    Take Fuel
                                </button>
                            </div>

                            @if($barrel->barrel_number > 9)
                                <form action="{{ route('barrels.destroy', $barrel->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        Remove
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info mb-0">No barrels found.</div>
                </div>
            @endforelse
        </div>

    </div>

    <!-- Fuel Summary Tables -->
    <div class="card shadow-sm border-0 mt-4 p-3">
        <div class="card-body">
            <ul class="nav nav-tabs" id="fuelLogTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="refilled-tab" data-bs-toggle="tab" data-bs-target="#refilled" type="button" role="tab">
                        Fuel Refilled
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="taken-tab" data-bs-toggle="tab" data-bs-target="#taken" type="button" role="tab">
                        Fuel Taken
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="vehicle-summary-tab" data-bs-toggle="tab" data-bs-target="#vehicle-summary" type="button" role="tab">
                        Vehicle Summary
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-3" id="fuelLogTabsContent">
                {{-- Refilled Tab --}}
                <div class="tab-pane fade show active" id="refilled" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" id="fuelrefilledtable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Barrel</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Refilled Date</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $refilledLogs = $fuelLogs->where('fuel_refilled_amount', '>', 0); @endphp

                                @forelse($refilledLogs as $key => $log)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>Barrel {{ $log->barrel->barrel_number ?? '-' }}</td>
                                        <td>{{ $log->vehicle->reg_no ?? ('Vehicle #'.($log->vehicle->id ?? '')) }}</td>
                                        <td>{{ $log->fuel_refilled_amount }} L</td>
                                        <td>{{ $log->fuel_refilled_date ? \Carbon\Carbon::parse($log->fuel_refilled_date)->format('Y-m-d H:i') : '-' }}</td>
                                        <td>{{ $log->created_by ?? '-' }}</td>
                                    </tr>
                                @empty
                                    {{-- <tr>
                                        <td colspan="6" class="text-center">No refill records found.</td> 
                                    </tr> --}}
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Taken Tab --}}
                <div class="tab-pane fade" id="taken" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" id="takenTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Barrel</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Taken Date</th>
                                    <th>Created By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $takenLogs = $fuelLogs->where('fuel_taken_count', '>', 0); @endphp

                                @forelse($takenLogs as $key => $log)
                                    <tr>
                                        <td>{{ $key + 1 }}</td>
                                        <td>Barrel {{ $log->barrel->barrel_number ?? '-' }}</td>
                                        <td>{{ $log->vehicle->reg_no ?? ('Vehicle #'.($log->vehicle->id ?? '')) }}</td>
                                        <td>{{ $log->fuel_taken_count }} L</td>
                                        <td>{{ $log->fuel_taken_date ? \Carbon\Carbon::parse($log->fuel_taken_date)->format('Y-m-d H:i') : '-' }}</td>
                                        <td>{{ $log->created_by ?? '-' }}</td>
                                    </tr>
                                @empty
                                    {{-- <tr>
                                        <td colspan="6" class="text-center">No fuel taken records found.</td>
                                    </tr> --}}
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Vehicle Summary Tab --}}
                <div class="tab-pane fade" id="vehicle-summary" role="tabpanel">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" id="vehicleSummaryTable">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Vehicle</th>
                                    <th>Total Refilled</th>
                                    <th>Total Taken</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $vehicleSummary = $fuelLogs->groupBy('vehicle_id');
                                @endphp

                                @forelse($vehicleSummary as $vehicleId => $logs)
                                    @php
                                        $vehicle = $logs->first()->vehicle;
                                        $totalRefilled = $logs->sum('fuel_refilled_amount');
                                        $totalTaken = $logs->sum('fuel_taken_count');
                                    @endphp
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $vehicle->reg_no ?? ('Vehicle #'.$vehicleId) }}</td>
                                        <td>{{ number_format($totalRefilled, 2) }} L</td>
                                        <td>{{ number_format($totalTaken, 2) }} L</td>
                                    </tr>
                                @empty
                                    {{-- <tr>
                                        <td colspan="4" class="text-center">No vehicle summary found.</td> 
                                    </tr> --}}
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Fuel Action Modal -->
<div class="modal fade" id="fuelActionModal" tabindex="-1" aria-labelledby="fuelActionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('fuel-logs.store') }}" method="POST">
                @csrf

                <div class="modal-header">
                    <h5 class="modal-title" id="fuelActionModalLabel">Fuel Action</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="barrel_id" id="modal_barrel_id">
                    <input type="hidden" name="action_type" id="modal_action_type">
                    <input type="hidden" id="modal_barrel_capacity">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Barrel</label>
                            <input type="text" class="form-control" id="modal_barrel_name" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="vehicle_id" class="form-label">Vehicle <span class="text-danger">*</span></label>
                            <select name="vehicle_id" id="vehicle_id" class="form-select select2" required>
                                <option value="">Select Vehicle</option>
                                @foreach(($vehicles ?? collect()) as $vehicle)
                                    <option value="{{ $vehicle->id }}">
                                        {{ $vehicle->reg_no ?? ('Vehicle #'.$vehicle->id) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="amount" class="form-label">Fuel Capacity <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" required>
                            <small class="text-muted" id="amount_limit_text"></small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="action_date" class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="action_date" id="action_date" class="form-control" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" id="modal_submit_btn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#vehicle_id').select2({
            dropdownParent: $('#fuelActionModal'),
            placeholder: 'Select Vehicle',
            allowClear: true,
            width: '100%'
        });
    });
</script>

<script>
    $(function () {
        ['#fuelrefilledtable', '#takenTable', '#vehicleSummaryTable']
        .forEach(id => $(id).DataTable({
            paging: true,
            searching: true,
            ordering: true,
            info: true,
            lengthChange: true,
            pageLength: 25,
            columnDefs: [
                { orderable: false, targets: -1 }
            ]
        }));
    });
</script>

<script>
    function openFuelModal(actionType, barrelId, barrelNumber, barrelCapacity) {
        document.getElementById('modal_barrel_id').value = barrelId;
        document.getElementById('modal_action_type').value = actionType;
        document.getElementById('modal_barrel_name').value = 'Barrel ' + barrelNumber + ' (' + barrelCapacity + 'L)';
        document.getElementById('modal_barrel_capacity').value = barrelCapacity;

        const amountInput = document.getElementById('amount');
        amountInput.max = barrelCapacity;
        amountInput.value = '';
        document.getElementById('amount_limit_text').innerText = 'Maximum allowed: ' + barrelCapacity + 'L';

        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        document.getElementById('action_date').min = now.toISOString().slice(0, 16);

        const title = actionType === 'refill' ? 'Refill Fuel' : 'Take Fuel';
        const buttonText = actionType === 'refill' ? 'Save Refill' : 'Save Fuel Taken';

        document.getElementById('fuelActionModalLabel').innerText = title;
        document.getElementById('modal_submit_btn').innerText = buttonText;
    }

    document.getElementById('amount').addEventListener('input', function () {
        const max = parseFloat(this.max);
        const value = parseFloat(this.value);

        if (value > max) {
            this.value = max;
        }
    });
</script>
@endsection