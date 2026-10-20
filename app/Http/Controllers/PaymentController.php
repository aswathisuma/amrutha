<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    public function index(Request $request)
    {
        $scoped = InvoiceController::scoped($request->user());
        $invoices = (clone $scoped)->with('customer')->latest('issued_at')->latest('id')->paginate(25);
        $all = (clone $scoped)->get(['id', 'total', 'amount_paid', 'status']);
        $ledger = Payment::whereIn('invoice_id', $all->pluck('id'));

        return view('payments.index', [
            'invoices' => $invoices,
            'collected' => (int) (clone $ledger)->sum('amount'),
            'collectedCount' => $all->where('status', 'Paid')->count(),
            'outstanding' => $all->whereIn('status', ['Pending', 'Partial'])->sum(fn ($i) => $i->balance()),
            'dueCount' => $all->whereIn('status', ['Pending', 'Partial'])->count(),
            'thisMonth' => (int) (clone $ledger)->where('received_at', '>=', local_now()->startOfMonth()->utc())->sum('amount'),
            'successRate' => $all->count() ? round($all->where('status', 'Paid')->count() / $all->count() * 100) : 0,
        ]);
    }

    /** Record a payment. The form's one-time token is the ledger idempotency key (no double charges on resubmit). */
    public function markPaid(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1'],
            'method' => ['nullable', Rule::in(config('crm.pay_methods'))],
            'reference' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ]);

        $p = $this->payments->record(
            $invoice,
            $data['amount'] ?? $invoice->balance(),
            $data['method'] ?? 'UPI',
            $request->user(),
            $data['reference'] ?? null,
            'web:'.$request->user()->id.':'.$data['idempotency_key'],
        );

        return $this->done('Payment of '.money($p->amount).' recorded', 'ok', $request->boolean('reopen') ? route('invoices.show', $invoice) : null);
    }

    public function refund(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request, $invoice);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $this->payments->refund($invoice, $data['amount'], $data['reason'], $request->user());

        return $this->done('Refund of '.money($data['amount']).' recorded', 'warn', route('invoices.show', $invoice));
    }

    protected function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_unless(InvoiceController::scoped($request->user())->whereKey($invoice->id)->exists(), 403);
    }
}
