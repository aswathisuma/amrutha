<!DOCTYPE html>
<html lang="en">
<head>
    @section('title', 'Sign in')
    @include('partials.head')
</head>
<body class="font-sans text-ink antialiased min-h-screen grid place-items-center p-4">
<div class="w-full max-w-sm">
    <div class="flex items-center gap-2.5 justify-center mb-6">
        <div class="w-10 h-10 rounded-xl bg-brand grid place-items-center font-display font-bold text-white text-lg shadow-soft">H</div>
        <div class="leading-tight">
            <div class="font-display font-bold text-[17px] tracking-tight">{{ config('crm.brand.name') }}</div>
            <div class="text-[11px] text-slate-500 -mt-0.5">Field Service CRM</div>
        </div>
    </div>

    <form method="POST" action="{{ url('/login') }}" class="bg-white rounded-2xl border border-slate2 shadow-soft p-6 space-y-4">
        @csrf
        <h1 class="font-display text-[19px] font-bold">Sign in</h1>
        @error('email')
            <div class="text-[12.5px] text-rose-600 bg-rose-50 rounded-lg px-3 py-2">{{ $message }}</div>
        @enderror
        <x-field label="Email">
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="input">
        </x-field>
        <x-field label="Password">
            <input name="password" type="password" required class="input">
        </x-field>
        <label class="flex items-center gap-2 text-[12.5px] text-slate-500"><input type="checkbox" name="remember" class="accent-brand"> Remember me</label>
        <button class="w-full bg-brand hover:bg-brand-600 text-white rounded-xl py-2.5 text-[13.5px] font-medium transition">Sign in</button>
    </form>

    @if ($demoUsers->isNotEmpty())
        <div class="mt-4 bg-white rounded-2xl border border-slate2 p-4">
            <div class="text-[11px] uppercase tracking-wider text-slate-400 mb-2">Demo accounts · password <span class="num text-ink normal-case">password</span></div>
            <div class="space-y-1">
                @foreach ($demoUsers as $u)
                    <button type="button" onclick="document.querySelector('[name=email]').value='{{ $u->email }}';document.querySelector('[name=password]').value='password';"
                            class="w-full flex items-center gap-2.5 px-2 py-1.5 rounded-lg hover:bg-paper text-left">
                        <x-avatar :name="$u->name" :size="26" />
                        <span class="text-[12.5px] font-medium">{{ $u->name }}</span>
                        <span class="ml-auto text-[11px] text-slate-400">{{ $u->roleLabel() }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif
</div>
</body>
</html>
