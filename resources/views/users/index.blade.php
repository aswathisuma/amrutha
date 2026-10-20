@extends('layouts.app')
@section('title', 'Users')

@section('content')
<x-page-head title="Users & Roles" sub="Team access control">
    <x-btn data-toast="Invites need a mail driver — configure MAIL_* in .env, or add users with php artisan tinker.">+ Invite User</x-btn>
</x-page-head>

<div class="bg-white rounded-2xl border border-slate2 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-[13px]">
            <thead class="bg-paper text-slate-500 text-[11.5px]">
            <tr>
                <th class="text-left font-medium px-4 py-2.5">User</th>
                <th class="text-left font-medium px-4 py-2.5">Role</th>
                <th class="text-left font-medium px-4 py-2.5 hidden sm:table-cell">Branch</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($users as $u)
                <tr class="border-t border-slate2 hover:bg-paper">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2.5"><x-avatar :name="$u->name" :size="32" />
                            <div class="leading-tight"><div class="font-medium text-ink">{{ $u->name }}</div><div class="text-[11px] text-slate-400">{{ $u->email }}</div></div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><x-badge :color="in_array($u->role, ['super', 'admin']) ? 'bg-violet-50 text-violet-700' : ($u->role === 'tech' ? 'bg-blue-50 text-blue-700' : 'bg-slate-100 text-slate-600')">{{ $u->roleLabel() }}</x-badge></td>
                    <td class="px-4 py-3 hidden sm:table-cell text-slate-600">{{ in_array($u->role, ['super', 'admin']) ? 'All' : ($u->branch?->name ?? '—') }}</td>
                    <td class="px-4 py-3"><x-badge :color="$u->status === 'Active' ? 'bg-brand-50 text-brand-600' : 'bg-slate-100 text-slate-500'">{{ $u->status }}</x-badge></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
