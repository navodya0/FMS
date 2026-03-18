@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card shadow-sm border-0 p-4">
        <div class="row">
            <div class="col-6">
                <h3 class="fw-bold">
                    Vehicle QR
                </h3>
            </div>
            <div class="col-6 text-end">
                <h4 class="badge bg-warning text-dark ms-2">
                    {{ $vehicleCount }} QR Images Uploaded
                </h4>
            </div>
        </div>

        <hr>

        @php
            $canEdit = auth()->check() && auth()->user()->email === 'it@explorevacations.lk';
        @endphp

        <table class="table table-bordered" id="qaImagesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Registration No</th>
                    <th>Company</th>
                    <th>Ownership Type</th>
                    <th>Uploaded QR</th>
                    @if($canEdit)
                        <th>Upload QR</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($vehicles as $key => $vehicle)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $vehicle->reg_no }}</td>
                    <td>{{ $vehicle->company->name ?? 'N/A' }}</td>                    
                    <td>{{ $vehicle->ownershipType->ownership_name ?? 'N/A' }}</td>                    
                    <td>
                        @if($vehicle->qrImages->count())
                            @foreach($vehicle->qrImages as $qr)

                                <button class="btn btn-sm btn-success mb-1"
                                    data-bs-toggle="modal"
                                    data-bs-target="#qrModal"
                                    onclick="showQR('{{ $vehicle->reg_no }}', '{{ url('storage/app/public/'.$qr->qr_image) }}')">
                                    <i class="bi bi-qr-code"></i> View
                                </button>

                                <a href="{{ url('storage/app/public/'.$qr->qr_image) }}"
                                    class="btn btn-sm btn-primary mb-1"
                                    download="{{ $vehicle->reg_no }}.png">
                                    <i class="bi bi-download"></i> Download
                                </a>

                            @endforeach
                        @else
                            <span class="text-muted">No QR uploaded</span>
                        @endif
                    </td>

                    @if($canEdit)
                    <td>
                        <form action="{{ route('qr.upload', $vehicle->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            <div class="row">
                                <div class="col-lg-6">
                                    <input type="file"
                                        name="qr_image"
                                        class="form-control mb-2"
                                        required
                                        accept="image/*">
                                </div>
                                <div class="col-lg-6">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        Upload
                                    </button>
                                </div>
                            </div>
                        </form>
                    </td>
                    @endif

                </tr>
                @empty
                <tr>
                    <td colspan="{{ $canEdit ? 4 : 3 }}" class="text-center">
                        No active vehicles found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content shadow">

                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        Vehicle QR - <span id="vehicleReg"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body text-center">
                    <img id="qrPreview" src="" class="img-fluid rounded shadow" style="max-height:400px">
                </div>

                <div class="modal-footer justify-content-between">
                    @if($canEdit)
                        <a id="downloadQR" href="" class="btn btn-success" download>
                            Download QR
                        </a>
                    @endif

                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <script>
        function showQR(regNo, imageUrl)
        {
            document.getElementById('vehicleReg').innerText = regNo;
            document.getElementById('qrPreview').src = imageUrl;

            let downloadBtn = document.getElementById('downloadQR');
            if (downloadBtn) {
                downloadBtn.href = imageUrl;
            }
        }
    </script>

    <script>
        $(document).ready(function() {
            $('#qaImagesTable').DataTable({
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                lengthChange: true,
                pageLength: 25,
                columnDefs: [
                    { orderable: false, targets: -1 }
                ]
            });
        });
    </script>
</div>
@endsection