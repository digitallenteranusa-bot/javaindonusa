<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Services\Billing\CreditNoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditNoteController extends Controller
{
    protected CreditNoteService $creditNoteService;

    public function __construct(CreditNoteService $creditNoteService)
    {
        $this->creditNoteService = $creditNoteService;
    }

    /**
     * List all credit notes
     */
    public function index(Request $request)
    {
        $query = CreditNote::with([
            'customer:id,customer_id,name',
            'createdBy:id,name',
            'approvedBy:id,name',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%")
                            ->orWhere('customer_id', 'like', "%{$search}%");
                    });
            });
        }

        $creditNotes = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $stats = [
            'pending' => CreditNote::where('status', 'pending')->count(),
            'approved' => CreditNote::where('status', 'approved')->count(),
            'total_approved_amount' => CreditNote::where('status', 'approved')->sum('amount'),
        ];

        return Inertia::render('Admin/CreditNote/Index', [
            'creditNotes' => $creditNotes,
            'filters' => $request->only(['status', 'type', 'search']),
            'stats' => $stats,
        ]);
    }

    /**
     * Create credit note form
     */
    public function create(Request $request)
    {
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::with('package:id,name,price')
                ->find($request->customer_id);
        }

        return Inertia::render('Admin/CreditNote/Create', [
            'customer' => $customer,
        ]);
    }

    /**
     * Store a new credit note
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'type' => 'required|in:refund,credit,adjustment',
            'amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:500',
            'invoice_id' => 'nullable|exists:invoices,id',
            'payment_id' => 'nullable|exists:payments,id',
            'notes' => 'nullable|string|max:1000',
        ], [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'type.required' => 'Tipe credit note wajib dipilih.',
            'amount.required' => 'Jumlah wajib diisi.',
            'amount.min' => 'Jumlah minimal Rp 1.',
            'reason.required' => 'Alasan wajib diisi.',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        $creditNote = $this->creditNoteService->create(
            customer: $customer,
            amount: (float) $validated['amount'],
            type: $validated['type'],
            reason: $validated['reason'],
            invoice: isset($validated['invoice_id']) ? \App\Models\Invoice::find($validated['invoice_id']) : null,
            payment: isset($validated['payment_id']) ? \App\Models\Payment::find($validated['payment_id']) : null,
            notes: $validated['notes'] ?? null,
        );

        return redirect()->route('admin.credit-notes.index')
            ->with('success', "Credit note #{$creditNote->credit_note_number} berhasil dibuat, menunggu persetujuan.");
    }

    /**
     * Approve credit note
     */
    public function approve(CreditNote $creditNote)
    {
        try {
            $this->creditNoteService->approve($creditNote, auth()->user());
            return back()->with('success', "Credit note #{$creditNote->credit_note_number} disetujui.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject credit note
     */
    public function reject(Request $request, CreditNote $creditNote)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->creditNoteService->reject($creditNote, auth()->user(), $request->reason);
            return back()->with('success', "Credit note #{$creditNote->credit_note_number} ditolak.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
