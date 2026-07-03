@extends('layouts.app')

@section('content')
<div class="container py-0">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">Admin Ticket Management</h3>

        <button type="button" class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#categoryModal">
            + Ticket Categories
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header fw-bold">
            All Tickets
        </div>

        <div class="card-body pb-0">
            <div class="row align-items-center mb-3">

                <div class="col-md-10">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <button class="nav-link active priority-tab" data-priority="all" type="button">All</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link priority-tab" data-priority="low" type="button">Low</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link priority-tab" data-priority="medium" type="button">Medium</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link priority-tab" data-priority="high" type="button">High</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link priority-tab" data-priority="urgent" type="button">Urgent</button>
                        </li>
                    </ul>
                </div>

                <div class="col-md-2 text-end">
                    <select id="statusFilter" class="form-select">
                        <option value="">All</option>
                        <option value="Pending">Pending</option>
                        <option value="Open">Open</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                        <option value="Closed">Closed</option>
                    </select>
                </div>

            </div>
        </div>

        <div class="card-body table-responsive">
            <table id="adminTicketsTable" class="table table-bordered table-striped">
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

                            <td>
                                @if($ticket->priority == 'low')
                                    <span class="badge bg-primary">Low</span>
                                @elseif($ticket->priority == 'medium')
                                    <span class="badge bg-warning text-dark">Medium</span>
                                @elseif($ticket->priority == 'high')
                                    <span class="badge bg-success">High</span>
                                @else
                                    <span class="badge bg-danger">Urgent</span>
                                @endif
                            </td>

                            <td>
                                @if($ticket->status == 'pending')
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @elseif($ticket->status == 'open')
                                    <span class="badge bg-primary">Open</span>
                                @elseif($ticket->status == 'in_progress')
                                    <span class="badge bg-info text-dark">In Progress</span>
                                @elseif($ticket->status == 'resolved')
                                    <span class="badge bg-success">Resolved</span>
                                @else
                                    <span class="badge bg-dark">Closed</span>
                                @endif
                            </td>

                            <td>{{ $ticket->created_at->format('Y-m-d h:i A') }}</td>

                            <td>
                                <button type="button"
                                    class="btn btn-sm {{ in_array($ticket->status, ['resolved', 'closed']) ? 'btn-success' : 'btn-primary' }}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#ticketModal{{ $ticket->id }}">
                                    {{ in_array($ticket->status, ['resolved', 'closed']) ? 'View' : 'View / Update' }}
                                </button>
                            </td>
                        </tr>

                        <div class="modal fade ticket-modal" id="ticketModal{{ $ticket->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">

                                    <form action="{{ route('admin.help.tickets.update', $ticket->id) }}" method="POST">
                                        @csrf
                                        @method('PUT')

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
                                                        <img src="{{ asset('storage/' . $ticket->image_path) }}" class="img-fluid rounded border" style="max-height:150px;">
                                                    </div>
                                                </div>
                                            @endif

                                            @if(!in_array($ticket->status, ['closed']))
                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Update Status</label>
                                                    <select name="status" class="form-control select2-modal" required>
                                                        <option value="pending" {{ $ticket->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                                        <option value="open" {{ $ticket->status == 'open' ? 'selected' : '' }}>Open</option>
                                                        <option value="in_progress" {{ $ticket->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                                        <option value="resolved" {{ $ticket->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                                        <option value="closed" {{ $ticket->status == 'closed' ? 'selected' : '' }}>Closed</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-bold">Add Comment</label>
                                                    <textarea name="comment" rows="3" class="form-control" placeholder="Add update or note..."></textarea>
                                                </div>
                                            @else
                                                <div class="alert alert-success">
                                                    This ticket is closed. Updates are disabled.
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
                                                                style="max-height:200px;">
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

                                            @if($ticket->status != 'closed')
                                                <button type="submit" class="btn btn-primary">
                                                    Save Changes
                                                </button>
                                            @endif
                                        </div>

                                    </form>

                                </div>
                            </div>
                        </div>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">Manage Ticket Categories</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <form action="{{ route('admin.ticket-categories.store') }}" method="POST">
                    @csrf

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <label class="form-label">Category Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Description</label>
                            <input type="text" name="description" class="form-control">
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                Add
                            </button>
                        </div>
                    </div>
                </form>

                <hr>

                <div class="table-responsive">
                    <table id="categoryTable" class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th width="200">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach($categories as $category)
                                <tr>
                                    <td>
                                        <form action="{{ route('admin.ticket-categories.update', $category->id) }}"
                                            method="POST"
                                            class="update-category-form">
                                            @csrf
                                            @method('PUT')

                                            <input type="text"
                                                name="name"
                                                class="form-control"
                                                value="{{ $category->name }}"
                                                required>
                                    </td>

                                    <td>
                                            <input type="text"
                                                name="description"
                                                class="form-control"
                                                value="{{ $category->description }}">
                                    </td>

                                    <td>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                Update
                                            </button>
                                        </form>

                                        <form action="{{ route('admin.ticket-categories.delete', $category->id) }}"
                                            method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Delete this category?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<style>
    .select2-container {
        z-index: 9999;
    }

    .select2-container .select2-selection--single {
        height: 38px;
        padding: 5px 8px;
        border: 1px solid #ced4da;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        let table = $('#adminTicketsTable').DataTable({
            pageLength: 10,
            ordering: false,
            searching: true
        });

        $('#categoryTable').DataTable({
            pageLength: 5,
            ordering: false,
            searching: true
        });

        $('.priority-tab').on('click', function () {
            $('.priority-tab').removeClass('active');
            $(this).addClass('active');

            let priority = $(this).data('priority');

            if (priority === 'all') {
                table.column(4).search('').draw();
            } else {
                table.column(4).search(priority, true, false).draw();
            }
        });

        $('#statusFilter').on('change', function () {
            let status = $(this).val();

            if (status === '') {
                table.column(5).search('').draw();
            } else {
                table.column(5).search(status, true, false).draw();
            }
        });

        $('.ticket-modal').on('shown.bs.modal', function () {
            let modal = $(this);

            modal.find('.select2-modal').select2({
                width: '100%',
                dropdownParent: modal
            });
        });
    });
</script>
@endpush