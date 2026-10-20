<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\BookingWorkflow;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $invoices = InvoiceController::scoped($request->user())->with('customer')->latest('issued_at')->get();
        $paid = $invoices->where('status', 'Paid');

        return view('payments.index', [
            'invoices' => $invoices,
            'paid' => $paid,
            'due' => $invoices->where('status', '!=', 'Paid'),
            'thisMonth' => $paid->filter(fn ($i) => $i->paid_at?->isCurrentMonth())->sum('total'),
            'methods' => $paid->groupBy('method')->map->count()->sortDesc(),
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice, BookingWorkflow $workflow)
    {
        abort_unless(InvoiceController::scoped($request->user())->whereKey($invoice->id)->exists(), 403);
        $method = $request->validate(['method' => ['nullable', Rule::in(config('crm.pay_methods'))]])['method'] ?? 'UPI';
        $workflow->markInvoicePaid($invoice, $method);

        return $this->done('Payment of '.money($invoice->total).' recorded');
    }
}
