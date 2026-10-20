<!DOCTYPE html>
<html lang="en">
<head>
    @section('title', $iv->number)
    @include('partials.head')
    <style>
        html, body { background: #fff; }
        @media print { .no-print { display: none; } @page { margin: 16mm; } }
    </style>
</head>
<body class="font-sans text-ink antialiased">
<div class="max-w-xl mx-auto p-8">
    @include('invoices._body')
    <div class="flex items-center justify-between mt-6 text-[11.5px] text-slate-400">
        <span>Status: {{ $iv->status }}{{ $iv->isPaid() ? ' · '.$iv->method : '' }}</span>
        <span>Thank you for choosing {{ config('crm.brand.name') }}.</span>
    </div>
    <div class="no-print mt-8 flex gap-2">
        <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-brand text-white text-[13px] font-medium">Print / Save as PDF</button>
        <button onclick="window.close()" class="px-3.5 py-2 rounded-xl bg-white border border-slate2 text-[13px] font-medium">Close</button>
    </div>
</div>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 400));</script>
</body>
</html>
