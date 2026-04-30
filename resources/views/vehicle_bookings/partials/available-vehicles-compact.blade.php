@if($vehicles->isEmpty())
    <div class="alert alert-warning text-center py-1 mb-0 small">
        No available vehicles today
    </div>
@else
    @foreach($vehicles as $type => $typeVehicles)
        @php
            $categoryGroups = $typeVehicles->groupBy(fn ($v) => $v->vehicleCategory->name ?? 'Uncategorized');
        @endphp

        <div class="mb-2">
            <div class="fw-bold small border-bottom">
                {{ $type }}
                <span class="badge bg-primary">{{ $typeVehicles->count() }}</span>
            </div>

            @foreach($categoryGroups as $category => $categoryVehicles)
                <div class="mt-1">
                    <div class="small text-muted">
                        {{ $category }}
                        <span class="badge bg-success">{{ $categoryVehicles->count() }}</span>
                    </div>

                    <div class="d-flex flex-wrap gap-1">
                        @foreach($categoryVehicles as $v)
                            <span class="badge bg-light text-dark border">
                                {{ $v->reg_no }} - {{ $v->make }} {{ $v->model }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif