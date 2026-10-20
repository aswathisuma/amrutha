@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<x-page-head title="Dashboard" :sub="'Live operations across '.$scopeLabel">
    <div class="relative">
        <div class="flex items-center bg-white border border-slate2 rounded-xl p-0.5 text-[12.5px]">
            @foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month'] as $key => $label)
                <a href="{{ route('dashboard', ['range' => $key]) }}" class="px-3 py-1.5 rounded-lg {{ $range === $key ? 'bg-ink text-white' : 'text-slate-500 hover:text-ink' }}">{{ $label }}</a>
            @endforeach
            <button type="button" onclick="document.getElementById('customRange').classList.toggle('hidden')" class="px-3 py-1.5 rounded-lg {{ $range === 'custom' ? 'bg-ink text-white' : 'text-slate-500 hover:text-ink' }}">Custom</button>
        </div>
        <form id="customRange" method="GET" action="{{ route('dashboard') }}" class="hidden absolute right-0 mt-2 z-10 bg-white rounded-2xl shadow-soft border border-slate2 p-3 w-64 space-y-2 text-[12.5px]">
            <input type="hidden" name="range" value="custom">
            <x-field label="From"><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></x-field>
            <x-field label="To"><input type="date" name="to" value="{{ $to->toDateString() }}" class="input"></x-field>
            <x-btn type="submit" class="w-full justify-center">Apply</x-btn>
        </form>
    </div>
</x-page-head>

<div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-3 mb-4">
    <x-kpi label="Total Customers" :value="$stats['customers']" :sub="'+'.$stats['newCustomers'].' this week'" rule="bg-brand" icon="users" />
    <x-kpi label="New Leads" :value="$stats['newLeads']" :sub="count(config('crm.sources')).' sources'" rule="bg-blue-500" icon="target" />
    <x-kpi label="Today's Bookings" :value="$stats['today']" :sub="$stats['pending'].' pending'" rule="bg-violet-500" icon="clipboard" />
    <x-kpi label="In Progress" :value="$stats['inProgress']" :sub="$stats['assigned'].' assigned'" rule="bg-amber-500" icon="wrench" />
    <x-kpi label="Completed" :value="$stats['done']" :sub="$stats['cancelled'].' cancelled'" rule="bg-teal-500" icon="star" />
    <x-kpi label="Avg Rating" :value="$stats['avgRating'].'★'" :sub="$stats['reviews'].' reviews'" rule="bg-amber" icon="star" />
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
    <div class="bg-gradient-to-br from-ink to-ink-600 text-white rounded-2xl p-5">
        <div class="text-[12px] text-white/60">Revenue {{ ['today' => 'today', 'week' => 'this week', 'month' => 'this month', 'custom' => $from->format('d M').' – '.$to->format('d M')][$range] }}</div>
        <div class="num text-[30px] font-bold mt-1">{{ money($stats['revRange']) }}</div>
        <div class="flex gap-4 mt-3 text-[12px]">
            <div><div class="text-white/50">Today</div><div class="num font-semibold text-brand-400">{{ money($stats['revToday']) }}</div></div>
            <div><div class="text-white/50">Outstanding</div><div class="num font-semibold text-amber-500">{{ money($stats['outstanding']) }}</div></div>
        </div>
    </div>
    <div class="md:col-span-2 bg-white rounded-2xl border border-slate2 p-5">
        <div class="flex items-center justify-between mb-3"><span class="text-[13px] font-semibold text-ink">Booking & revenue trend</span><span class="text-[11px] text-slate-400">Last 7 days</span></div>
        <div class="h-[150px]"><canvas id="trendChart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
    <div class="bg-white rounded-2xl border border-slate2 p-5">
        <div class="text-[13px] font-semibold text-ink mb-3">Job flow</div>
        @php($max = max(max($flow), 1))
        <div class="space-y-2.5">
            @foreach ($flow as $label => $v)
                <div class="flex items-center gap-3 text-[12.5px]">
                    <span class="w-24 text-slate-500 shrink-0">{{ $label }}</span>
                    <div class="flex-1 h-2 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full bg-brand-400" style="width:{{ $v / $max * 100 }}%"></div></div>
                    <span class="num font-semibold w-6 text-right">{{ $v }}</span>
                </div>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-2xl border border-slate2 p-5">
        <div class="text-[13px] font-semibold text-ink mb-3">Service mix</div>
        <div class="h-[180px]"><canvas id="svcChart"></canvas></div>
    </div>
    <div class="bg-white rounded-2xl border border-slate2 p-5">
        <div class="flex items-center justify-between mb-3"><span class="text-[13px] font-semibold text-ink">Recent activity</span><a href="{{ route('activity') }}" data-panel-url="{{ route('activity') }}" class="text-[11.5px] text-brand-600">Timeline</a></div>
        <div class="space-y-3 max-h-[180px] overflow-y-auto pr-1">
            @forelse ($activity as $a)
                <div class="flex gap-2.5 text-[12.5px]">
                    <div class="w-1.5 h-1.5 rounded-full bg-brand-400 mt-1.5 shrink-0"></div>
                    <div class="flex-1 leading-tight"><span class="font-medium text-ink">{{ $a->subject }}</span> <span class="text-slate-500">{{ $a->action }}</span><div class="text-[10.5px] text-slate-400">{{ ago($a->created_at) }} ago</div></div>
                </div>
            @empty
                <div class="text-[12px] text-slate-400">No activity yet.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
  (() => {
    const trend = @json($trend);
    new Chart(document.getElementById('trendChart'), {
      type: 'bar',
      data: {
        labels: trend.labels,
        datasets: [
          { type: 'line', label: 'Revenue', data: trend.revenue, borderColor: '#0f8f5b', backgroundColor: 'transparent', tension: .4, yAxisID: 'y1', pointRadius: 0, borderWidth: 2 },
          { label: 'Bookings', data: trend.bookings, backgroundColor: '#cbe8db', borderRadius: 5, yAxisID: 'y' },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + (c.dataset.yAxisID === 'y1' ? '₹' + c.parsed.y.toLocaleString('en-IN') : c.parsed.y) } } },
        scales: { y: { grid: { display: false }, ticks: { font: { size: 10 }, precision: 0 } }, y1: { position: 'right', grid: { display: false }, ticks: { display: false } }, x: { grid: { display: false }, ticks: { font: { size: 10 } } } },
      },
    });

    const mix = @json($serviceMix);
    new Chart(document.getElementById('svcChart'), {
      type: 'doughnut',
      data: { labels: Object.keys(mix), datasets: [{ data: Object.values(mix), backgroundColor: ['#0f8f5b', '#34c27f', '#2563eb', '#7c3aed', '#c77800', '#0891b2', '#db2777', '#94a3b8'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 10 }, padding: 8 } } } },
    });
  })();
</script>
@endpush
