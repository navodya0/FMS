@extends('layouts.app')

@section('content')
<div class="container py-0">

    <h3 class="mb-4 fw-bold">HelpDesk Summary</h3>

    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card border-primary">
                <div class="card-body text-center">
                    <h6>Total</h6>
                    <h3>{{ $totalTickets }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <h6>Pending</h6>
                    <h3>{{ $pendingTickets }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <h6>Open</h6>
                    <h3>{{ $openTickets }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-secondary">
                <div class="card-body text-center">
                    <h6>In Progress</h6>
                    <h3>{{ $inProgressTickets }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6>Resolved</h6>
                    <h3>{{ $resolvedTickets }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-2 mb-3">
            <div class="card border-dark">
                <div class="card-body text-center">
                    <h6>Closed</h6>
                    <h3>{{ $closedTickets }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">

            <div class="row align-items-center">

                <div class="col-md-6">
                    <h5 class="mb-0 fw-bold">
                        All HelpDesk Tickets
                    </h5>
                </div>

                <div class="col-md-3 ms-auto">
                    <select id="categoryFilter" class="form-select">
                        <option value="">All Categories</option>

                        @foreach($categories as $category)
                            <option value="{{ $category->name }}">
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body table-responsive">
            <table id="summaryTicketsTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>User</th>
                        <th>Category</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Created Date</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($tickets as $ticket)
                        <tr>
                            <td>{{ $ticket->ticket_no }}</td>
                            <td>{{ $ticket->user->name ?? '-' }}</td>
                            <td>{{ $ticket->category->name ?? '-' }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</td>
                            <td>{{ $ticket->created_at->format('Y-m-d h:i A') }}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#summaryTicketModal{{ $ticket->id }}">
                                    View
                                </button>
                            </td>
                        </tr>
                        <div class="modal fade" id="summaryTicketModal{{ $ticket->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5 class="modal-title">
                                            {{ $ticket->ticket_no }} - {{ $ticket->subject }}
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">

                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <strong>User:</strong>
                                                <p>{{ $ticket->user->name ?? '-' }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <strong>Category:</strong>
                                                <p>{{ $ticket->category->name ?? '-' }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <strong>Priority:</strong>
                                                <p>{{ ucfirst($ticket->priority) }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <strong>Status:</strong>
                                                <p>{{ ucfirst(str_replace('_', ' ', $ticket->status)) }}</p>
                                            </div>

                                            <div class="col-md-6">
                                                <strong>Created:</strong>
                                                <p>{{ $ticket->created_at->format('Y-m-d h:i A') }}</p>
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <strong>Description:</strong>
                                            <div class="border rounded p-3 bg-light mt-2">
                                                {{ $ticket->description }}
                                            </div>
                                        </div>

                                        @if($ticket->image_path)
                                            <div class="mb-3">
                                                <strong>Ticket Image:</strong>
                                                <div class="mt-2">
                                                    <img src="{{ asset('storage/' . $ticket->image_path) }}"
                                                        class="img-fluid rounded border"
                                                        style="max-height: 250px;">
                                                </div>
                                            </div>
                                        @endif

                                        <hr>

                                        <h6 class="fw-bold mb-3">Comments</h6>

                                        @forelse($ticket->comments as $comment)
                                            <div class="border rounded p-3 mb-2">
                                                <div class="d-flex justify-content-between">
                                                    <strong>
                                                        {{ $comment->user->name ?? 'User' }}

                                                        @if($comment->added_by_type == 'user')
                                                            <span class="badge bg-secondary">User</span>
                                                        @else
                                                            <span class="badge bg-primary">Admin</span>
                                                        @endif
                                                    </strong>

                                                    <small class="text-muted">
                                                        {{ $comment->created_at->format('Y-m-d h:i A') }}
                                                    </small>
                                                </div>

                                                <p class="mb-0 mt-2">
                                                    {{ $comment->comment }}
                                                </p>

                                                @if($comment->image_path)
                                                    <div class="mt-2">
                                                        <img src="{{ asset('storage/' . $comment->image_path) }}"
                                                            class="img-fluid rounded border"
                                                            style="max-height: 200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-muted">No comments yet.</p>
                                        @endforelse

                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                            Close
                                        </button>
                                    </div>

                                </div>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function () {

        $('#categoryFilter').select2({
            width: '100%',
            placeholder: 'All Categories'
        });

        let table = $('#summaryTicketsTable').DataTable({
            pageLength: 10,
            ordering: false,
            searching: true
        });

        $('#categoryFilter').on('change', function () {

            let category = $(this).val();

            if (category === '') {
                table.column(2).search('').draw();
            } else {
                table.column(2).search('^' + category + '$', true, false).draw();
            }

        });

    });
</script>
@endpush