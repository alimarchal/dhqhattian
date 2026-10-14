{{-- Settings hub: one card per screen, shown only when the user has its permission. --}}
<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb"><a href="{{ route('dashboard') }}">Dashboard</a><span aria-hidden="true">›</span><span>Settings</span></nav>
                <h1 class="ak-title">Settings</h1>
                <p class="ak-sub">Set up the hospital and control who can use the system.</p>
            </div>
        </div>
    </x-slot>

    @include('partials.ui-style')
    <style>
        .st-section { margin-top: 24px; }
        .st-section:first-of-type { margin-top: 0; }
        .st-section h2 { margin: 0 0 2px; font-size: 15px; font-weight: 800; color: var(--ak-text); }
        .st-section p { margin: 0 0 12px; font-size: 13px; color: var(--ak-muted); }
        .st-grid { display: grid; gap: 14px; grid-template-columns: 1fr; }
        @media (min-width: 640px) { .st-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 1024px) { .st-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .st-card { display: flex; gap: 14px; align-items: flex-start; padding: 16px; background: #fff; border: 1px solid var(--ak-border); border-radius: 12px; text-decoration: none; color: inherit; box-shadow: 0 1px 2px rgba(15,23,42,.04); transition: box-shadow .15s, border-color .15s, transform .15s; }
        .st-card:hover { border-color: #94a3b8; box-shadow: 0 6px 16px rgba(15,23,42,.08); transform: translateY(-1px); }
        .st-card b { display: block; font-size: 15px; color: var(--ak-text); }
        .st-card small { display: block; margin-top: 3px; font-size: 12.5px; color: var(--ak-muted); line-height: 1.4; }
    </style>

    <div class="ak-page">
        <x-status-message />
        @forelse ($sections as $section)
            <section class="st-section" aria-label="{{ $section['title'] }}">
                <h2>{{ $section['title'] }}</h2>
                <p>{{ $section['hint'] }}</p>
                <div class="st-grid">
                    @foreach ($section['cards'] as $card)
                        <a href="{{ route($card['route']) }}" class="st-card">
                            <span class="ak-kpi-icon ak-tone-{{ $card['tone'] }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}" /></svg></span>
                            <span><b>{{ $card['label'] }}</b><small>{{ $card['text'] }}</small></span>
                        </a>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="ak-empty"><h2>Nothing to set up</h2><p>You do not have permission for any settings screen.</p></div>
        @endforelse
    </div>
</x-app-layout>
