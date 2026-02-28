<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InvoiceController extends Controller
{
    /**
     * List invoices (paginated, filterable).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Invoice::with(['customer']);

        // Scope by role
        $user = $request->user();
        if ($user->role === 'penagih') {
            $query->whereHas('customer', function ($q) use ($user) {
                $q->where('collector_id', $user->id);
            });
        }

        // Filters
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('period_year')) {
            $query->where('period_year', $request->input('period_year'));
        }

        if ($request->filled('period_month')) {
            $query->where('period_month', $request->input('period_month'));
        }

        $invoices = $query->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->paginate($request->input('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    /**
     * Get invoice detail.
     */
    public function show(Request $request, Invoice $invoice): InvoiceResource
    {
        // Scope by role
        $user = $request->user();
        if ($user->role === 'penagih') {
            $invoice->load('customer');
            if ($invoice->customer->collector_id !== $user->id) {
                abort(403, 'Akses ditolak.');
            }
        }

        $invoice->load(['customer']);

        return new InvoiceResource($invoice);
    }
}
