@if($vehicles->isEmpty())
    <div class="alert alert-warning text-center py-1 mb-0 small">
        No available vehicles today
    </div>
@else
    @foreach($vehicles as $type => $typeVehicles)
        @php
            $categoryGroups = $typeVehicles
                ->groupBy(fn ($v) => $v->vehicleCategory->name ?? 'Uncategorized')
                ->sortKeys();
        @endphp

        <div class="mb-2">
            <!-- Type Row -->
            <div class="d-flex align-items-center flex-wrap small border-bottom pb-1">
                
                <!-- Type Name -->
                <span class="fw-bold me-2">
                    {{ $type }} :
                </span>

                <!-- Categories -->
                @foreach($categoryGroups as $category => $categoryVehicles)
                    <span class="me-3 category-pill">
                        {{ $category }} :
                        <strong>{{ $categoryVehicles->count() }}</strong>
                    </span>
                @endforeach

            </div>
        </div>
    @endforeach
@endif