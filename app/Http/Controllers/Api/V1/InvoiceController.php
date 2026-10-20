<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\InvoiceController as WebInvoices;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InvoiceController extends ApiController
{
    /** GET /invoices?status=&q= */
    public function index(Request $request)
    {
        $this->requireSection($request, 'invoices', 'payments');
        $f = $request->validate([
            'status' => ['nullable', Rule::in(['Pending', 'Partial', 'Paid', 'Refunded'])],
            'q' => ['nullable', 'string', 'max:40'],
        ]);

        $q = WebInvoices::scoped($request->user())
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where('number', 'like', "%$v%"));

        return $this->paginate($q, $request, InvoiceResource::class, ['id', 'issued_at', 'total'], '-issued_at');
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->requireSection($request, 'invoices', 'payments');
        abort_unless(WebInvoices::scoped($request->user())->whereKey($invoice->id)->exists(), 403);

        return $this->respond(new InvoiceResource($invoice->load(['items', 'payments'])));
    }

    /**
     * POST /invoices/{id}/payments { amount, method, reference? }
     * The Idempotency-Key header doubles as the ledger key, so a retried
     * request can never create a second payment.
     */
    public function pay(Request $request, Invoice $invoice, PaymentService $payments)
    {
        $this->requireSection($request, 'payments');
        abort_unless(WebInvoices::scoped($request->user())->whereKey($invoice->id)->exists(), 403);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(config('crm.pay_methods'))],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $key = $request->header('Idempotency-Key');
        $payment = $payments->record($invoice, $data['amount'], $data['method'], $request->user(), $data['reference'] ?? null, $key ? "api:{$request->user()->id}:{$key}" : null);

        return $this->created([
            'payment' => (new PaymentResource($payment))->resolve($request),
            'invoice' => (new InvoiceResource($invoice->fresh()))->resolve($request),
        ]);
    }
}
