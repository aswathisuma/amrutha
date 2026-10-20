<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.head')
</head>
<body class="font-sans text-ink antialiased">
@php($me = auth()->user())

<div id="app" class="min-h-screen flex">
    {{-- SIDEBAR --}}
    <aside id="sidebar" class="fixed z-40 lg:static inset-y-0 left-0 w-[248px] bg-ink text-white/80 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
        <div class="h-16 flex items-center gap-2.5 px-5 border-b border-white/10 shrink-0">
            <div class="w-9 h-9 rounded-xl bg-brand grid place-items-center font-display font-bold text-white text-lg shadow-soft">H</div>
            <div class="leading-tight">
                <div class="font-display font-bold text-white text-[15px] tracking-tight">{{ config('crm.brand.name') }}</div>
                <div class="text-[10.5px] text-white/45 -mt-0.5">{{ config('crm.brand.tagline') }}</div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-0.5 text-[13.5px]">
            @foreach (config('crm.nav') as $group => $items)
                @php($visible = collect($items)->filter(fn ($i, $id) => $me->canSee($id)))
                @continue($visible->isEmpty())
                <div class="text-[10px] uppercase tracking-wider text-white/30 px-2 pt-3 pb-1">{{ $group }}</div>
                @foreach ($visible as $id => $item)
                    @php($active = request()->routeIs($id) || request()->routeIs($id.'.*'))
                    <a href="{{ route($id) }}" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg transition {{ $active ? 'bg-white/10 text-white' : 'text-white/65 hover:text-white hover:bg-white/5' }}">
                        <span class="{{ $active ? 'text-brand-400' : '' }}">{!! crm_icon($item['icon']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? null) === 'unread' && $navUnread)
                            <span class="ml-auto num text-[10px] bg-brand text-white rounded-full px-1.5 py-0.5">{{ $navUnread }}</span>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </nav>

        <div class="p-3 border-t border-white/10">
            <div class="text-[10.5px] uppercase tracking-wider text-white/35 px-2 mb-1.5">Signed in as</div>
            @if ($switchUsers->isNotEmpty())
                <form method="POST" action="{{ route('switch-user') }}">
                    @csrf
                    <select id="roleSwitch" name="user_id" onchange="this.form.submit()" class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-2 text-[12.5px] text-white/90 focus:outline-none focus:border-brand-400">
                        @foreach ($switchUsers as $u)
                            <option value="{{ $u->id }}" class="text-ink" @selected($u->id === $me->id)>{{ $u->roleLabel() }} · {{ $u->name }}</option>
                        @endforeach
                    </select>
                </form>
            @else
                <div class="w-full bg-white/5 border border-white/10 rounded-lg px-2.5 py-2 text-[12.5px] text-white/90">{{ $me->roleLabel() }} · {{ $me->name }}</div>
            @endif
        </div>
    </aside>
    <div id="scrim" class="fixed inset-0 z-30 bg-ink/40 scrim hidden lg:hidden"></div>

    {{-- MAIN --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <header class="h-16 bg-white/85 backdrop-blur border-b border-slate2 flex items-center gap-3 px-4 lg:px-6 sticky top-0 z-20">
            <button id="menuBtn" class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-paper text-ink" aria-label="Menu">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <button id="searchOpen" class="group flex items-center gap-2 flex-1 max-w-md px-3 py-2 rounded-xl bg-paper border border-slate2 text-slate-400 text-[13px] hover:border-brand-400/60 transition">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
                <span class="truncate">Search customers, bookings, invoices…</span>
                <kbd class="ml-auto hidden sm:block text-[10px] font-medium text-slate-400 bg-white border border-slate2 rounded px-1.5 py-0.5">⌘K</kbd>
            </button>
            <div class="ml-auto flex items-center gap-1.5">
                @if ($me->canSee('inbox'))
                    <a href="{{ route('inbox') }}" title="WhatsApp Inbox" class="relative p-2.5 rounded-xl hover:bg-paper text-ink">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2Zm5.8 14.03c-.24.68-1.42 1.3-1.95 1.34-.5.04-.98.22-3.32-.7-2.8-1.1-4.6-3.98-4.74-4.17-.14-.19-1.14-1.52-1.14-2.9 0-1.38.72-2.06.98-2.34.24-.26.53-.33.71-.33.18 0 .35 0 .5.01.16.01.38-.06.59.45.24.55.79 1.9.86 2.04.07.14.12.3.02.48-.09.19-.14.3-.28.46-.14.16-.3.36-.42.48-.14.14-.29.29-.12.57.16.28.72 1.19 1.55 1.93 1.06.95 1.96 1.24 2.24 1.38.28.14.44.12.6-.07.16-.19.69-.8.88-1.08.18-.28.37-.23.62-.14.25.09 1.6.75 1.87.89.28.14.46.2.53.32.07.11.07.66-.17 1.34Z"/></svg>
                        @if ($navUnread)<span class="absolute top-1.5 right-1.5 w-2 h-2 bg-brand-500 rounded-full ring-2 ring-white"></span>@endif
                    </a>
                @endif
                <div class="relative">
                    <button id="bellBtn" class="relative p-2.5 rounded-xl hover:bg-paper text-ink" aria-label="Notifications">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 1 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        @if (count($notifications))
                            <span id="bellDot" class="absolute top-1.5 right-1.5 min-w-[15px] h-[15px] px-0.5 grid place-items-center bg-amber text-white text-[9px] font-bold rounded-full ring-2 ring-white">{{ count($notifications) }}</span>
                        @endif
                    </button>
                    <div id="bellPanel" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-soft border border-slate2 overflow-hidden z-30">
                        <div class="px-4 py-3 border-b border-slate2 flex items-center justify-between">
                            <span class="font-semibold text-[13px]">Notifications</span>
                            <button id="clearBell" class="text-[11px] text-brand-600">Mark read</button>
                        </div>
                        @forelse ($notifications as $n)
                            <a href="{{ $n['url'] }}" class="px-4 py-3 border-b border-slate2 last:border-0 flex gap-3 hover:bg-paper">
                                <span class="{{ $n['color'] }} mt-0.5">{!! crm_icon($n['icon'], 'w-4 h-4') !!}</span>
                                <div class="leading-tight"><div class="text-[12.5px] font-medium text-ink">{{ $n['title'] }}</div><div class="text-[11.5px] text-slate-400">{{ $n['sub'] }}</div></div>
                            </a>
                        @empty
                            <div class="px-4 py-6 text-center text-[12px] text-slate-400">You're all caught up.</div>
                        @endforelse
                    </div>
                </div>
                <div class="w-px h-6 bg-slate2 mx-1"></div>
                <div class="relative">
                    <button id="userChip" class="flex items-center gap-2 pl-1 pr-1 text-left">
                        <x-avatar :name="$me->name" :size="32" />
                        <div class="hidden sm:block leading-tight">
                            <div class="text-[12.5px] font-medium text-ink">{{ $me->name }}</div>
                            <div class="text-[10.5px] text-slate-400">{{ $me->roleLabel() }}</div>
                        </div>
                    </button>
                    <div id="userPanel" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-soft border border-slate2 overflow-hidden z-30">
                        <div class="px-4 py-3 border-b border-slate2 leading-tight"><div class="text-[12.5px] font-medium text-ink">{{ $me->name }}</div><div class="text-[11px] text-slate-400 truncate">{{ $me->email }}</div></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="w-full text-left px-4 py-2.5 text-[12.5px] text-rose-600 hover:bg-paper">Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1 overflow-y-auto">
            <div data-view class="p-4 lg:p-6 max-w-[1400px] mx-auto">
                @yield('content')
            </div>
        </main>
    </div>
</div>

{{-- DRAWER --}}
<div id="drawerWrap" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-ink/40 scrim" data-close-drawer></div>
    <div id="drawer" class="absolute right-0 top-0 h-full w-full max-w-[520px] bg-paper shadow-2xl drawer-enter overflow-y-auto"></div>
</div>

{{-- MODAL --}}
<div id="modalWrap" class="fixed inset-0 z-50 hidden grid place-items-center p-4">
    <div class="absolute inset-0 bg-ink/40 scrim" data-close-modal></div>
    <div id="modal" class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[88vh] overflow-y-auto"></div>
</div>

{{-- COMMAND PALETTE --}}
<div id="cmdWrap" class="fixed inset-0 z-[60] hidden pt-[12vh] px-4">
    <div class="absolute inset-0 bg-ink/40 scrim" data-close-cmd></div>
    <div class="relative mx-auto w-full max-w-xl bg-white rounded-2xl shadow-2xl border border-slate2 overflow-hidden">
        <div class="flex items-center gap-2 px-4 border-b border-slate2">
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input id="cmdInput" placeholder="Search everything in {{ config('crm.brand.name') }}…" class="flex-1 py-3.5 text-[14px] outline-none placeholder:text-slate-400" autocomplete="off"/>
            <kbd class="text-[10px] text-slate-400 bg-paper border border-slate2 rounded px-1.5 py-0.5">esc</kbd>
        </div>
        <div id="cmdResults" class="max-h-[52vh] overflow-y-auto p-2"></div>
    </div>
</div>

{{-- TOASTS --}}
<div id="toasts" class="fixed bottom-5 right-5 z-[70] space-y-2 w-[320px] max-w-[calc(100vw-2.5rem)]"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@php($crmToasts = array_values(array_filter([session('toast'), $errors->any() ? [$errors->first(), 'err'] : null])))
<script>
    window.CRM = {
        searchUrl: @json(route('search')),
        toasts: @json($crmToasts),
        reopen: @json(session('reopen')),
    };
</script>
<script src="{{ asset('js/crm.js') }}"></script>
@stack('scripts')
</body>
</html>
