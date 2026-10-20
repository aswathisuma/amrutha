@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<x-page-head title="Reports" sub="Performance across the network" />

<div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    <div class="bg-white rounded-2xl border border-slate2 p-5"><div class="text-[13px] font-semibold mb-3">Revenue by branch</div><div class="h-[220px]"><canvas id="branchChart"></canvas></div></div>
    <div class="bg-white rounded-2xl border border-slate2 p-5"><div class="text-[13px] font-semibold mb-3">Lead conversion funnel</div><div class="h-[220px]"><canvas id="funnelChart"></canvas></div></div>
    <div class="bg-white rounded-2xl border border-slate2 p-5"><div class="text-[13px] font-semibold mb-3">Technician performance <span class="font-normal text-slate-400">· jobs done</span></div><div class="h-[220px]"><canvas id="techChart"></canvas></div></div>
    <div class="bg-white rounded-2xl border border-slate2 p-5"><div class="text-[13px] font-semibold mb-3">Service performance <span class="font-normal text-slate-400">· bookings</span></div><div class="h-[220px]"><canvas id="svcPerfChart"></canvas></div></div>
</div>
@endsection

@push('scripts')
<script>
  (() => {
    const C = @json($charts);
    const axes = (x = 10, y = 10) => ({ y: { grid: { display: false }, ticks: { font: { size: y } } }, x: { grid: { display: false }, ticks: { font: { size: x } } } });
    const base = { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } };

    new Chart(document.getElementById('branchChart'), {
      type: 'bar',
      data: { labels: C.branch.labels, datasets: [{ label: 'Revenue', data: C.branch.data, backgroundColor: '#0f8f5b', borderRadius: 6 }] },
      options: { ...base, plugins: { ...base.plugins, tooltip: { callbacks: { label: (c) => '₹' + c.parsed.y.toLocaleString('en-IN') } } }, scales: axes(9, 10) },
    });
    new Chart(document.getElementById('funnelChart'), {
      type: 'bar',
      data: { labels: C.funnel.labels, datasets: [{ label: 'Leads', data: C.funnel.data, backgroundColor: '#34c27f', borderRadius: 6 }] },
      options: { ...base, indexAxis: 'y', scales: axes(10, 9) },
    });
    new Chart(document.getElementById('techChart'), {
      type: 'bar',
      data: { labels: C.tech.labels, datasets: [{ label: 'Jobs', data: C.tech.data, backgroundColor: '#2563eb', borderRadius: 6 }] },
      options: { ...base, scales: axes(9, 10) },
    });
    new Chart(document.getElementById('svcPerfChart'), {
      type: 'polarArea',
      data: { labels: C.service.labels, datasets: [{ data: C.service.data, backgroundColor: ['#0f8f5b', '#34c27f', '#2563eb', '#7c3aed', '#c77800', '#0891b2', '#db2777', '#94a3b8'] }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } } },
    });
  })();
</script>
@endpush
