@extends('layouts.app')

@section('content')
<div class="container py-0">

    <h3 class="mb-4 fw-bold">IT Help Desk</h3>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-header fw-bold">Create New Ticket</div>

        <div class="card-body">
            <form action="{{ route('help.tickets.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control select2">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control select2" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="3" class="form-control" required>{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-primary">Submit Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header fw-bold">My Tickets</div>

        <div class="card-body table-responsive">
            <table id="ticketsTable" class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Ticket No</th>
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
                            <td>{{ $ticket->category->name ?? '-' }}</td>
                            <td>{{ $ticket->subject }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>
                                @if($ticket->status == 'pending')
                                    <span class="badge bg-warning text-dark">Pending</span>
                                @elseif($ticket->status == 'open')
                                    <span class="badge bg-primary">Open</span>
                                @elseif($ticket->status == 'in_progress')
                                    <span class="badge bg-info text-dark">In Progress</span>
                                @elseif($ticket->status == 'resolved')
                                    <span class="badge bg-success">Resolved</span>
                                    <small class="d-block text-success mt-1">
                                        Please confirm if this issue is solved
                                    </small>
                                @elseif($ticket->status == 'closed')
                                    <span class="badge bg-dark">Closed</span>
                                @endif
                            </td>
                            <td>{{ $ticket->created_at->format('Y-m-d h:i A') }}</td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#ticketModal{{ $ticket->id }}">
                                    View
                                </button>
                            </td>
                        </tr>

                        <div class="modal fade" id="ticketModal{{ $ticket->id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                                <div class="modal-content">

                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ $ticket->ticket_no }} - {{ $ticket->subject }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>

                                    <div class="modal-body">
                                        <p><strong>Category:</strong> {{ $ticket->category->name ?? '-' }}</p>
                                        <p><strong>Priority:</strong> {{ ucfirst($ticket->priority) }}</p>
                                        <p>
                                            <strong>Status:</strong>

                                            @if($ticket->status == 'pending')
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            @elseif($ticket->status == 'open')
                                                <span class="badge bg-primary">Open</span>
                                            @elseif($ticket->status == 'in_progress')
                                                <span class="badge bg-info text-dark">In Progress</span>
                                            @elseif($ticket->status == 'resolved')
                                                <span class="badge bg-success">Resolved</span>
                                                <div class="alert alert-warning mt-2 mb-0">
                                                    IT team marked this issue as resolved. Please complete this ticket by confirming whether your issue is solved.
                                                </div>
                                            @elseif($ticket->status == 'closed')
                                                <span class="badge bg-dark">Closed</span>
                                            @endif
                                        </p>

                                        <div class="mb-3">
                                            <strong>Description:</strong>
                                            <div class="border rounded p-3 bg-light mt-2">
                                                {{ $ticket->description }}
                                            </div>
                                        </div>

                                        @if($ticket->image_path)
                                            <div class="mb-3">
                                                <strong>Attached Image:</strong>
                                                <div class="mt-2">
                                                    <img src="{{ asset('storage/' . $ticket->image_path) }}" class="img-fluid rounded border" style="max-height:150px;">
                                                </div>
                                            </div>
                                        @endif

                                        <hr>

                                        <h6 class="fw-bold">Updates & Comments</h6>

                                        @forelse($ticket->comments as $comment)
                                            <div class="border rounded p-3 mb-2">
                                                <div class="d-flex justify-content-between">
                                                    <strong>
                                                        {{ $comment->user->name ?? 'User' }}
                                                        <span class="badge bg-secondary">{{ ucfirst($comment->added_by_type) }}</span>
                                                    </strong>
                                                    <small>{{ $comment->created_at->format('Y-m-d h:i A') }}</small>
                                                </div>

                                                <p class="mb-0 mt-2">{{ $comment->comment }}</p>

                                                @if($comment->image_path)
                                                    <div class="mt-2">
                                                        <img src="{{ asset('storage/' . $comment->image_path) }}" class="img-fluid rounded border" style="max-height:200px;">
                                                    </div>
                                                @endif
                                            </div>
                                        @empty
                                            <p class="text-muted">No updates yet.</p>
                                        @endforelse

                                        @if($ticket->status == 'resolved')
                                            <hr>

                                            <h6 class="fw-bold">Is your issue solved?</h6>

                                            <form action="{{ route('help.tickets.user-reply', $ticket->id) }}" method="POST" enctype="multipart/form-data">
                                                @csrf

                                                <div class="mb-3">
                                                    <label class="form-label">Solved?</label>
                                                    <select name="is_solved" class="form-control" required>
                                                        <option value="">Select</option>
                                                        <option value="yes">Yes, solved</option>
                                                        <option value="no">No, not solved</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Comment</label>
                                                    <textarea name="comment" rows="3" class="form-control"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Image</label>
                                                    <input type="file" name="image" class="form-control" accept="image/*">
                                                </div>

                                                <div class="text-end">
                                                    <button type="submit" class="btn btn-primary">Submit Response</button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>

                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('.select2').select2({ width: '100%' });

        $('#ticketsTable').DataTable({
            pageLength: 10,
            ordering: false,
            searching: true
        });
    });
</script>
@endpush