<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    /**
     * List payments (paginated).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Payment::with(['customer']);

        // Scope by role
        $user = $request->user();
        if ($user->role === 'penagih') {
            $query->where('collector_id', $user->id);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $payments = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return PaymentResource::collection($payments);
    }

    /**
     * Create a new payment (from mobile app).
     */
    public function store(StorePaymentRequest $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->input('customer_id'));
        $user = $request->user();

        // Collectors can only pay for their assigned customers
        if ($user->role === 'penagih' && $customer->collector_id !== $user->id) {
            abort(403, 'Akses ditolak.');
        }

        $payment = $this->paymentService->processPayment(
            customer: $customer,
            amount: $request->input('amount'),
            paymentMethod: $request->input('payment_method'),
            collector: $user->role === 'penagih' ? $user : null,
            receivedBy: $user,
            notes: $request->input('notes'),
            referenceNumber: $request->input('reference_number'),
        );

        return (new PaymentResource($payment->load('customer')))
            ->response()
            ->setStatusCode(201);
    }
}
