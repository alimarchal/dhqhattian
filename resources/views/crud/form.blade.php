{{--
    Shared add / edit form for the master-data modules. Fields come from the
    MasterCrudController config: name, label, type (text|number|select|textarea),
    rules, options, help, step, default.
--}}
@php
    $route = $crud['route'];
    $title = ($isCreate ? 'Add ' : 'Edit ').$crud['singular'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route($route.'.index') }}">{{ $crud['title'] }}</a><span aria-hidden="true">›</span>
                    <span>{{ $isCreate ? 'Add' : 'Edit' }}</span>
                </nav>
                <h1 class="ak-title">{{ $title }}@unless ($isCreate): {{ $record->{$crud['nameKey'] ?? 'name'} ?? '' }}@endunless</h1>
                <p class="ak-sub">{{ $crud['formLede'] ?? 'Fields marked * are required.' }}</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route($route.'.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to {{ $crud['title'] }}</a>
            </div>
        </div>
    </x-slot>

    @include('partials.ui-style')

    <div class="ak-page">
        <x-status-message />

        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert">
                <b>Please fix the following before saving:</b>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $isCreate ? route($route.'.store') : route($route.'.update', $record) }}" novalidate x-data="{ saving: false }" @submit="saving = true">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            <section class="uf-card" style="max-width: 880px">
                <header class="uf-card-head">
                    <div>
                        <h2 class="uf-card-title">{{ $crud['singular'] }} details</h2>
                        <p class="uf-card-sub">{{ $crud['formHint'] ?? '' }}</p>
                    </div>
                </header>
                <div class="uf-body">
                    <div class="uf-fields">
                        @foreach ($crud['fields'] as $field)
                            @php
                                $name = $field['name'];
                                $type = $field['type'] ?? 'text';
                                $required = str_contains((string) (is_array($field['rules']) ? implode('|', array_map('strval', $field['rules'])) : $field['rules']), 'required');
                                $value = old($name, $record->{$name} ?? ($field['default'] ?? ''));
                            @endphp
                            <div class="uf-field" @if ($type === 'textarea') style="grid-column: 1 / -1" @endif>
                                <label for="{{ $name }}">{{ $field['label'] }} @if ($required)<span class="uf-req">*</span>@endif</label>
                                @if ($type === 'select')
                                    <select id="{{ $name }}" name="{{ $name }}">
                                        <option value="">{{ $field['placeholder'] ?? 'Select…' }}</option>
                                        @foreach ($field['options'] as $optValue => $optLabel)
                                            <option value="{{ $optValue }}" @selected((string) $value === (string) $optValue)>{{ $optLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($type === 'textarea')
                                    <textarea id="{{ $name }}" name="{{ $name }}" rows="3" maxlength="{{ $field['max'] ?? 1000 }}">{{ $value }}</textarea>
                                @else
                                    <input id="{{ $name }}" type="{{ $type }}" name="{{ $name }}" value="{{ $value }}"
                                        @if ($type === 'number') step="{{ $field['step'] ?? 'any' }}" @if (isset($field['min'])) min="{{ $field['min'] }}" @endif @endif
                                        @if (isset($field['placeholder'])) placeholder="{{ $field['placeholder'] }}" @endif
                                        @if ($required) required @endif maxlength="255" autocomplete="off" @if ($loop->first && $isCreate) autofocus @endif>
                                @endif
                                @if (! empty($field['help']))<p class="ak-help">{{ $field['help'] }}</p>@endif
                                @error($name) <p class="uf-error">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <div class="uf-actions" style="margin-top:20px; max-width: 880px">
                <p>Fields marked <span class="uf-req">*</span> are required.</p>
                <div>
                    <a href="{{ route($route.'.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                    <button type="submit" class="ak-btn ak-btn-primary" :disabled="saving"><span x-show="!saving">{{ $isCreate ? 'Create '.strtolower($crud['singular']) : 'Save changes' }}</span><span x-show="saving" x-cloak>Saving…</span></button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
