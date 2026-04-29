@if($vehicles->isEmpty())
    <div class="alert alert-warning text-center">
        No available vehicles for this date
    </div>
@else
<div class="accordion" id="availableTypeAccordion">
    @foreach($vehicles as $type => $typeVehicles)
        @php
            $typeIndex = $loop->index;
            $categoryGroups = $typeVehicles->groupBy(fn ($v) => $v->vehicleCategory->name ?? 'Uncategorized');
        @endphp

        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button {{ $typeIndex !== 0 ? 'collapsed' : '' }}"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#typeCollapse{{ $typeIndex }}">
                    {{ $type }}
                    <span class="badge bg-primary ms-2">{{ $typeVehicles->count() }}</span>
                </button>
            </h2>

            <div id="typeCollapse{{ $typeIndex }}"
                 class="accordion-collapse collapse {{ $typeIndex === 0 ? 'show' : '' }}"
                 data-bs-parent="#availableTypeAccordion">

                <div class="accordion-body">
                    <div class="accordion" id="categoryAccordion{{ $typeIndex }}">
                        @foreach($categoryGroups as $category => $categoryVehicles)
                            @php $catIndex = $loop->index; @endphp

                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button {{ $catIndex !== 0 ? 'collapsed' : '' }}"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#catCollapse{{ $typeIndex }}{{ $catIndex }}">
                                        {{ $category }}
                                        <span class="badge bg-success ms-2">{{ $categoryVehicles->count() }}</span>
                                    </button>
                                </h2>

                                <div id="catCollapse{{ $typeIndex }}{{ $catIndex }}"
                                     class="accordion-collapse collapse {{ $catIndex === 0 ? 'show' : '' }}"
                                     data-bs-parent="#categoryAccordion{{ $typeIndex }}">

                                    <div class="accordion-body">
                                        <div class="row">
                                            @foreach($categoryVehicles as $v)
                                                <div class="col-md-3 mb-2">
                                                    <div class="border rounded p-2 fw-bold">
                                                        {{ $v->reg_no }}
                                                        <br>
                                                
                                                        <small class="text-muted">
                                                            {{ $v->make }} {{ $v->model }}
                                                        </small>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </div>
    @endforeach
</div>
@endif