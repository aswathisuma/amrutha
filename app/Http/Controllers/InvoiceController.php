<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    /** Invoices visible to the user (branch managers only see their branch). */
    public static function scoped(User $user): Builder
    {
        return Invoice::query()->when($user->isBranchScoped(), fn ($q) => $q->whereHas('booking', fn ($b) => $b->where('branch_id', $user->branch_id)));
    }

    public function index(Request $request)
    {
        $invoices = self::scoped($request->user())->with('customer')->latest('issued_at')->latest('id')->paginate(25);

        return view('invoices.index', compact('invoices'));
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request->user(), $invoice);
        $invoice->load(['customer', 'technician', 'booking', 'items', 'payments.recorder']);

        return $this->panel('invoices.show', ['iv' => $invoice], route('invoices'));
    }

    public function print(Request $request, Invoice $invoice)
    {
        $this->authorizeInvoice($request->user(), $invoice);
        $invoice->load(['customer', 'technician', 'booking', 'items', 'payments']);

        return view('invoices.print', ['iv' => $invoice]);
    }

    protected function authorizeInvoice(User $user, Invoice $invoice): void
    {
        abort_unless(self::scoped($user)->whereKey($invoice->id)->exists(), 403);
    }
}
