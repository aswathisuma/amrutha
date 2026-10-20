@extends('layouts.app')
@section('title', 'WA Templates')

@section('content')
<x-page-head title="WhatsApp Templates" sub="Meta-approved message templates">
    <x-btn data-toast="Templates are created and approved in Meta Business Manager, then synced here.">+ New Template</x-btn>
</x-page-head>

<div class="bg-brand-50 border border-brand-400/30 rounded-xl p-3 mb-4 text-[12px] text-brand-600">Template messages can start conversations and need Meta approval. Session messages (free-form) are only allowed within 24h of a customer's last message.</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    @foreach ($templates as $t)
        <div class="bg-white rounded-2xl border border-slate2 p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="num font-semibold text-[13px] text-ink">{{ $t->name }}</span>
                <div class="flex items-center gap-1.5">
                    <x-badge color="bg-slate-100 text-slate-600">{{ $t->category }}</x-badge>
                    <x-badge :color="$t->status === 'Approved' ? 'bg-brand-50 text-brand-600' : 'bg-amber-50 text-amber'">{{ $t->status }}</x-badge>
                </div>
            </div>
            <div class="bg-paper rounded-lg p-3 text-[12px] text-slate-600 whitespace-pre-line leading-snug">{{ $t->body }}</div>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-[10.5px] text-slate-400">
                @forelse ($t->variables() as $v)
                    <span class="bg-brand-50 text-brand-600 px-1.5 py-0.5 rounded">{{ $v }}</span>
                @empty
                    No variables
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@endsection
