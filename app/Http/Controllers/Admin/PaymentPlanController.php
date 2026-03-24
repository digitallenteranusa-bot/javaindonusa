<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentPlan;
use App\Services\Billing\PaymentPlanService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentPlanController extends Controller
{
    protected PaymentPlanService $planService;

    public function __construct(PaymentPlanService $planService)
    {
        $this->planService = $planService;
    }

    /**
     * List all payment plans
     */
    public function index(Request $request)
    {
        $query = PaymentPlan::with([
            'customer:id,customer_id,name,total_debt',
            'createdBy:id,name',
        ])->withCount('installments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_id', 'like', "%{$search}%");
            });
        }

        $plans = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15))
            ->withQueryString();

        $stats = [
            'active' => PaymentPlan::where('status', 'active')->count(),
            'completed' => PaymentPlan::where('status', 'completed')->count(),
            'total_active_amount' => PaymentPlan::where('status', 'active')->sum('remaining_amount'),
        ];

        return Inertia::render('Admin/PaymentPlan/Index', [
            'plans' => $plans,
            'filters' => $request->only(['status', 'search']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show payment plan detail with installments
     */
    public function show(PaymentPlan $paymentPlan)
    {
        $paymentPlan->load([
            'customer:id,customer_id,name,phone,total_debt',
            'installments.payment',
            'createdBy:id,name',
        ]);

        return Inertia::render('Admin/PaymentPlan/Show', [
            'plan' => $paymentPlan,
        ]);
    }

    /**
     * Create payment plan form
     */
    public function create(Request $request)
    {
        $customer = null;
        if ($request->filled('customer_id')) {
            $customer = Customer::find($request->customer_id);
        }

        return Inertia::render('Admin/PaymentPlan/Create', [
            'customer' => $customer,
        ]);
    }

    /**
     * Store a new payment plan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'installment_count' => 'required|integer|min:2|max:24',
            'total_amount' => 'nullable|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ], [
            'customer_id.required' => 'Pelanggan wajib dipilih.',
            'installment_count.required' => 'Jumlah cicilan wajib diisi.',
            'installment_count.min' => 'Minimal 2 cicilan.',
            'installment_count.max' => 'Maksimal 24 cicilan.',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        // Check no active plan exists
        $existingPlan = PaymentPlan::where('customer_id', $customer->id)
            ->where('status', PaymentPlan::STATUS_ACTIVE)
            ->first();

        if ($existingPlan) {
            return back()->with('error', 'Pelanggan sudah memiliki cicilan aktif. Batalkan dulu yang lama sebelum buat baru.');
        }

        try {
            $plan = $this->planService->createPlan(
                $customer,
                $validated['installment_count'],
                $validated['total_amount'] ?? null,
                $validated['notes'] ?? null
            );

            return redirect()->route('admin.payment-plans.show', $plan)
                ->with('success', "Cicilan {$plan->installment_count}x berhasil dibuat untuk {$customer->name}.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel a payment plan
     */
    public function cancel(Request $request, PaymentPlan $paymentPlan)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->planService->cancelPlan($paymentPlan, $request->reason);
            return back()->with('success', 'Cicilan berhasil dibatalkan.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
