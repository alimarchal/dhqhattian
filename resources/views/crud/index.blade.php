{{--
    Shared list screen for the master-data modules (departments, government departments,
    fee types, roles, permissions). Driven by the $crud array of MasterCrudController:
    title, route, permission, columns, search, filters. Same "ak" look as the Users list.
--}}
@php
    $authUser = auth()->user();
    $word = $crud['permission'];
    $canCreate = $authUser->can(($crud['createPermission'] ?? 'create '.$word));
    $canEdit = $authUser->can(($crud['editPermission'] ?? 'edit '.$word));
    $canDelete = $authUser->can(($crud['deletePermission'] ?? 'delete '.$word));
    $route = $crud['route'];
    $sortField = ltrim($sort, '-');
    $sortUrl = fn (string $field) => request()->fullUrlWithQuery(['sort' => $sort === $field ? '-'.$field : $field, 'page' => null]);
    $ariaSort = fn (string $field) => $sort === $field ? 'ascending' : ($sort === '-'.$field ? 'descending' : 'none');
    $sortMark = fn (string $field) => $sort === $field ? "\u{25B2}" : ($sort === '-'.$field ? "\u{25BC}" : '');
    $withFilters = fn (array $f) => request()->fullUrlWithQuery(['filter' => $f ?: null, 'page' => null]);
    $columnLabels = collect($crud['columns'])->pluck('label', 'key');
    $hasActions = $canEdit || $canDelete;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span><span>{{ $crud['title'] }}</span>
                </nav>
                <h1 class="ak-title">{{ $crud['title'] }}</h1>
                <p class="ak-sub">{{ $crud['lede'] ?? '' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('settings.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back</a>
                <button type="button" class="ak-btn ak-btn-outline" onclick="window.print()">Print</button>
                @if ($canCreate)
                    <a href="{{ route($route.'.create') }}" class="ak-btn ak-btn-primary"><span aria-hidden="true">＋</span> Add {{ strtolower($crud['singular']) }}</a>
                @endif
            </div>
        </div>
    </x-slot>

    @include('partials.ui-style')
    <style>@page { size: A4 landscape; margin: 10mm; }</style>

    <div class="ak-page" x-data="crudList()">
        <div class="ak-print-head">
            <div class="ak-print-bank">{{ config('app.name') }}</div>
            <div class="ak-print-title">{{ $crud['title'] }}</div>
            <table class="ak-print-meta"><tr><th>Printed</th><td>{{ now()->format('d.m.Y H:i') }} by {{ $authUser->name }}</td></tr></table>
        </div>

        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="ak-kpis" aria-label="Summary">
            <a href="{{ route($route.'.index') }}" class="ak-kpi is-active">
                <span class="ak-kpi-icon ak-tone-navy" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg></span>
                <span class="ak-kpi-body">
                    <span class="ak-kpi-label">{{ $crud['title'] }}</span>
                    <span class="ak-kpi-value">{{ number_format($total) }}</span>
                    <span class="ak-kpi-hint">{{ $filters ? 'matching the filters' : 'all records' }}</span>
                </span>
            </a>
            @foreach ($extra['kpis'] ?? [] as $kpi)
                <div class="ak-kpi">
                    <span class="ak-kpi-icon ak-tone-{{ $kpi['tone'] ?? 'slate' }}" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></span>
                    <span class="ak-kpi-body">
                        <span class="ak-kpi-label">{{ $kpi['label'] }}</span>
                        <span class="ak-kpi-value">{{ $kpi['value'] }}</span>
                        <span class="ak-kpi-hint">{{ $kpi['hint'] ?? '' }}</span>
                    </span>
                </div>
            @endforeach
        </section>

        <section class="ak-card" aria-label="{{ $crud['title'] }}">
            <form method="GET" action="{{ route($route.'.index') }}" class="ak-filters" x-data="{ busy: false }" @submit="busy = true">
                @if (request('sort')) <input type="hidden" name="sort" value="{{ request('sort') }}"> @endif
                <div class="ak-filter-row">
                    <div class="ak-field">
                        <label for="f_search">Search</label>
                        <div class="ak-search">
                            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                            <input id="f_search" type="search" name="filter[search]" value="{{ $filters['search'] ?? '' }}" autocomplete="off"
                                placeholder="{{ $crud['searchPlaceholder'] ?? 'Search…' }}" x-ref="search"
                                @keydown.window.slash="if (! ['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName)) { $event.preventDefault(); $refs.search.focus(); }">
                            <kbd aria-hidden="true">/</kbd>
                        </div>
                    </div>
                    @foreach ($crud['filters'] ?? [] as $filter)
                        @php $options = is_callable($filter['options']) ? ($filter['options'])() : $filter['options']; @endphp
                        <div class="ak-field">
                            <label for="f_{{ $filter['name'] }}">{{ $filter['label'] }}</label>
                            <select id="f_{{ $filter['name'] }}" name="filter[{{ $filter['name'] }}]">
                                <option value="">All</option>
                                @foreach ($options as $value => $label)
                                    <option value="{{ $value }}" @selected((string) ($filters[$filter['name']] ?? '') === (string) $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach
                    <div class="ak-field">
                        <label for="f_per_page">Rows per page</label>
                        <select id="f_per_page" name="per_page" onchange="this.form.requestSubmit()">
                            @foreach (\App\Http\Controllers\MasterCrudController::PER_PAGE as $n)
                                <option value="{{ $n }}" @selected($perPage === (string) $n)>{{ $n === 'all' ? 'All' : $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="ak-filter-buttons">
                        <button type="submit" class="ak-btn ak-btn-primary" :disabled="busy"><span x-show="!busy">Apply</span><span x-show="busy" x-cloak>Searching…</span></button>
                    </div>
                </div>
            </form>

            @if ($filters)
                <div class="ak-chips" aria-label="Active filters">
                    <span class="ak-chips-label">Filtered by</span>
                    @foreach ($filters as $key => $value)
                        @php
                            $filterDef = collect($crud['filters'] ?? [])->firstWhere('name', $key);
                            $shown = $value;
                            if ($filterDef) {
                                $opts = is_callable($filterDef['options']) ? ($filterDef['options'])() : $filterDef['options'];
                                $shown = $opts[$value] ?? $value;
                            }
                        @endphp
                        <a href="{{ $withFilters(\Illuminate\Support\Arr::except($filters, $key)) }}" class="ak-chip" aria-label="Remove filter {{ $key }}">
                            <b>{{ $filterDef['label'] ?? \Illuminate\Support\Str::headline($key) }}:</b> {{ $shown }} <span aria-hidden="true">×</span>
                        </a>
                    @endforeach
                    <a href="{{ $withFilters([]) }}" class="ak-chips-clear">Clear all</a>
                </div>
            @endif

            @if ($records->count() > 0)
                <div class="ak-table-wrap">
                    <div class="ak-dt-toolbar">
                        <p><b>{{ number_format($records->total()) }}</b> {{ \Illuminate\Support\Str::plural(strtolower($crud['singular']), $records->total()) }}
                            &middot; sorted by <b>{{ $columnLabels[$sortField] ?? 'ID' }}</b> ({{ str_starts_with($sort, '-') ? 'Z–A / highest first' : 'A–Z / lowest first' }})</p>
                    </div>
                    <div class="ak-dt-scroll" tabindex="0" aria-label="{{ $crud['title'] }} table">
                        <table class="ak-dt">
                            <caption class="sr-only">{{ $crud['title'] }}, {{ $records->total() }} results</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="ak-c">#</th>
                                    @foreach ($crud['columns'] as $column)
                                        @if ($column['sortable'] ?? false)
                                            <th scope="col" class="{{ ($column['align'] ?? '') === 'right' ? 'ak-r' : (($column['align'] ?? '') === 'center' ? 'ak-c' : '') }}" aria-sort="{{ $ariaSort($column['key']) }}"><a href="{{ $sortUrl($column['key']) }}">{{ $column['label'] }} <span aria-hidden="true">{{ $sortMark($column['key']) }}</span></a></th>
                                        @else
                                            <th scope="col" class="{{ ($column['align'] ?? '') === 'right' ? 'ak-r' : (($column['align'] ?? '') === 'center' ? 'ak-c' : '') }}">{{ $column['label'] }}</th>
                                        @endif
                                    @endforeach
                                    @if ($hasActions)<th scope="col" class="ak-c"><span class="sr-only">Actions</span></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($records as $record)
                                    @php $protected = isset($crud['protected']) && ($crud['protected'])($record); @endphp
                                    <tr>
                                        <td class="ak-c ak-muted">{{ $records->firstItem() + $loop->index }}</td>
                                        @foreach ($crud['columns'] as $column)
                                            @php
                                                $value = isset($column['value']) ? ($column['value'])($record) : data_get($record, $column['key']);
                                                $cls = ($column['align'] ?? '') === 'right' ? 'ak-r ak-mono' : (($column['align'] ?? '') === 'center' ? 'ak-c' : '');
                                            @endphp
                                            <td class="{{ $cls }}" data-label="{{ $column['label'] }}">
                                                @if (($column['badge'] ?? false) && filled($value))
                                                    <span class="ak-pill">{{ $value }}</span>
                                                @elseif ($loop->first)
                                                    <span class="ak-strong">{{ $value }}</span>
                                                @else
                                                    {{ filled($value) ? $value : '—' }}
                                                @endif
                                            </td>
                                        @endforeach
                                        @if ($hasActions)
                                            <td data-label="">
                                                <div class="ak-actions">
                                                    <span class="ak-slot">
                                                        @if ($canEdit)
                                                            <a href="{{ route($route.'.edit', $record) }}" class="ak-icon" title="Edit" aria-label="Edit {{ $record->{$crud['nameKey'] ?? 'name'} ?? '' }}">
                                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m16.86 4.49 2.65 2.65M4 20l4.2-.9 10.9-10.9a1.9 1.9 0 0 0-2.7-2.7L5.5 16.4 4 20Z" /></svg>
                                                            </a>
                                                        @endif
                                                    </span>
                                                    <span class="ak-slot">
                                                        @if ($canDelete && ! $protected)
                                                            <form method="POST" action="{{ route($route.'.destroy', $record) }}" @submit.prevent="askDelete($el, @js($record->{$crud['nameKey'] ?? 'name'} ?? ''))">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="ak-icon ak-icon-danger" title="Delete" aria-label="Delete">
                                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.35 9m-4.78 0L9.26 9m9.97-3.21c.34.05.68.11 1.02.17m-1.02-.17L18.16 19.67A2.25 2.25 0 0 1 15.92 21.75H8.08a2.25 2.25 0 0 1-2.24-2.08L4.77 5.79m14.46 0a48.1 48.1 0 0 0-3.48-.4m-12 .57c.34-.06.68-.12 1.02-.17m0 0a48.1 48.1 0 0 1 3.48-.4m7.5 0v-.92c0-1.18-.91-2.16-2.09-2.2a51.96 51.96 0 0 0-3.32 0c-1.18.04-2.09 1.02-2.09 2.2v.92m7.5 0a48.67 48.67 0 0 0-7.5 0" /></svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </span>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="ak-pager">
                    <p>Showing <b>{{ number_format($records->firstItem()) }}–{{ number_format($records->lastItem()) }}</b> of <b>{{ number_format($records->total()) }}</b></p>
                    <div class="ak-pager-right"><div>{{ $records->onEachSide(1)->links() }}</div></div>
                </div>
            @else
                <div class="ak-empty">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0Z" /></svg>
                    @if ($filters)
                        <h2>Nothing matches these filters</h2>
                        <p>Remove a filter or search with part of a name.</p>
                        <a href="{{ route($route.'.index') }}" class="ak-btn ak-btn-primary">Clear all filters</a>
                    @else
                        <h2>No {{ strtolower($crud['title']) }} yet</h2>
                        @if ($canCreate)<a href="{{ route($route.'.create') }}" class="ak-btn ak-btn-primary">＋ Add {{ strtolower($crud['singular']) }}</a>@endif
                    @endif
                </div>
            @endif
        </section>

        <div class="uf-modal" x-show="confirm.open" x-cloak style="display:none" @keydown.escape.window="confirm.open = false" role="dialog" aria-modal="true" aria-labelledby="cl-confirm-title">
            <div class="uf-modal-bg" x-show="confirm.open" x-transition.opacity @click="confirm.open = false"></div>
            <div class="uf-modal-box" x-show="confirm.open" x-transition>
                <div class="uf-modal-body">
                    <span class="uf-modal-icon ak-pill-red" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.01" /></svg></span>
                    <div>
                        <h3 id="cl-confirm-title" x-text="confirm.title"></h3>
                        <p style="margin:8px 0 0; font-size:14px; color:#334155">This cannot be undone. Records that are already in use cannot be deleted.</p>
                    </div>
                </div>
                <div class="uf-modal-foot">
                    <button type="button" class="ak-btn ak-btn-outline" @click="confirm.open = false">Cancel</button>
                    <button type="button" class="ak-btn ak-btn-danger-outline" @click="runConfirm()" :disabled="confirm.busy" x-text="confirm.busy ? 'Working…' : 'Delete {{ strtolower($crud['singular']) }}'"></button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            function crudList() {
                return {
                    confirm: { open: false, title: '', busy: false, form: null },
                    askDelete(form, name) { this.confirm = { open: true, title: 'Delete ' + (name || 'this record') + '?', busy: false, form }; },
                    runConfirm() { if (this.confirm.busy || !this.confirm.form) return; this.confirm.busy = true; this.confirm.form.submit(); },
                };
            }
        </script>
    @endpush
</x-app-layout>
