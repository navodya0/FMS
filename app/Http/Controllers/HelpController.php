<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HelpController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->email === 'it@explorevacations.lk') {
            $tickets = Ticket::with(['user', 'category', 'comments.user'])
                ->orderByRaw("
                    CASE
                        WHEN status IN ('resolved', 'closed') THEN 1
                        ELSE 0
                    END ASC
                ")
                ->orderBy('created_at', 'desc')
                ->get();

            $categories = TicketCategory::orderBy('name')->get();

            return view('help.admin', compact('tickets', 'categories'));
        }

        $categories = TicketCategory::orderBy('name')->get();

        $tickets = Ticket::with(['category', 'comments.user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('help.index', compact('categories', 'tickets'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:ticket_categories,id',
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ticket-images', 'public');
        }

        Ticket::create([
            'ticket_no' => 'TKT-' . date('Y') . '-' . str_pad(Ticket::count() + 1, 4, '0', STR_PAD_LEFT),
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'subject' => $request->subject,
            'description' => $request->description,
            'image_path' => $imagePath,
            'priority' => $request->priority,
            'status' => 'pending',
        ]);

        return redirect()->back()->with('success', 'Ticket created successfully.');
    }

    public function adminUpdate(Request $request, Ticket $ticket)
    {
        $request->validate([
            'status' => 'required|in:pending,open,in_progress,resolved,closed',
            'comment' => 'nullable|string',
        ]);

        $ticket->update([
            'status' => $request->status,
        ]);

        if ($request->filled('comment')) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'added_by_type' => 'admin',
                'comment' => $request->comment,
            ]);
        }

        return redirect()->back()->with('success', 'Ticket updated successfully.');
    }

    public function userReply(Request $request, Ticket $ticket)
    {
        abort_if($ticket->user_id !== Auth::id(), 403);

        $request->validate([
            'is_solved' => 'required|in:yes,no',
            'comment' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('ticket-comment-images', 'public');
        }

        if ($request->is_solved === 'yes') {
            $ticket->update([
                'status' => 'closed',
            ]);

            $comment = $request->comment ?: 'User confirmed the issue is solved.';
        } else {
            $ticket->update([
                'status' => 'in_progress',
            ]);

            $comment = $request->comment ?: 'User said the issue is not solved.';
        }

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'added_by_type' => 'user',
            'comment' => $comment,
            'image_path' => $imagePath,
        ]);

        return redirect()->back()->with('success', 'Response submitted successfully.');
    }

    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150|unique:ticket_categories,name',
            'description' => 'nullable|string',
        ]);

        TicketCategory::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Ticket category added successfully.');
    }

    public function updateCategory(Request $request, TicketCategory $category)
    {
        $request->validate([
            'name' => 'required|string|max:150|unique:ticket_categories,name,' . $category->id,
            'description' => 'nullable|string',
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return redirect()->back()->with('success', 'Ticket category updated successfully.');
    }

    public function deleteCategory(TicketCategory $category)
    {
        $category->delete();

        return redirect()->back()->with('success', 'Ticket category deleted successfully.');
    }

    public function summary()
    {
        $tickets = Ticket::with(['user', 'category', 'comments.user'])
            ->orderByRaw("
                CASE
                    WHEN status IN ('resolved', 'closed') THEN 1
                    ELSE 0
                END ASC
            ")
            ->orderBy('created_at', 'desc')
            ->get();

        $totalTickets = Ticket::count();
        $pendingTickets = Ticket::where('status', 'pending')->count();
        $openTickets = Ticket::where('status', 'open')->count();
        $inProgressTickets = Ticket::where('status', 'in_progress')->count();
        $resolvedTickets = Ticket::where('status', 'resolved')->count();
        $closedTickets = Ticket::where('status', 'closed')->count();
        $categories = TicketCategory::orderBy('name')->get();

        return view('help.summary', compact(
            'tickets',
            'totalTickets',
            'pendingTickets',
            'openTickets',
            'inProgressTickets',
            'resolvedTickets',
            'closedTickets',
            'categories'
        ));
    }
}