<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') — {{ config('crm.brand.name') }} CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;450;500;600;700&display=swap" rel="stylesheet">
{{-- Precompiled Tailwind v3.4 (see tailwind.config.js). Rebuild after adding new classes:
     npx tailwindcss@3.4.17 -i resources/css/tailwind.css -o public/css/app.css --minify --}}
<link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
<style>
  :root{ color-scheme: light; }
  html,body{ background:#f4f7f5; }
  ::-webkit-scrollbar{ width:9px; height:9px; }
  ::-webkit-scrollbar-thumb{ background:#c8d3cf; border-radius:8px; }
  ::-webkit-scrollbar-track{ background:transparent; }
  .num{ font-family:"Space Grotesk"; font-feature-settings:"tnum"; }
  [data-view]{ animation:fade .18s ease; }
  @keyframes fade{ from{ opacity:0; transform:translateY(4px);} to{opacity:1; transform:none;} }
  @media (prefers-reduced-motion: reduce){ *{ animation:none !important; } }
  .drawer-enter{ animation:slidein .22s cubic-bezier(.2,.8,.2,1); }
  @keyframes slidein{ from{ transform:translateX(100%);} to{ transform:none;} }
  .scrim{ backdrop-filter:blur(2px); }
  .input{ width:100%; border:1px solid #e4eae7; border-radius:.5rem; padding:.5rem .75rem; background:#fff; outline:none; font-size:13px; }
  .input:focus{ border-color:#34c27f; box-shadow:0 0 0 3px rgba(52,194,127,.15); }
</style>
