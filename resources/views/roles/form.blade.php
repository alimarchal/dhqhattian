{{-- Add / edit a role: its name and the permissions it gives (same matrix as the user form). --}}
@php
    $route = $crud['route'];
    $isCreate = ! $record->exists;
    $selected = array_map('strval', old('permissions', $selected ?? []));
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="ak-head">
            <div>
                <nav class="ak-crumbs" aria-label="Breadcrumb">
                    <a href="{{ route('settings.index') }}">Settings</a><span aria-hidden="true">›</span>
                    <a href="{{ route('roles.index') }}">Roles</a><span aria-hidden="true">›</span><span>{{ $isCreate ? 'Add' : 'Edit' }}</span>
                </nav>
                <h1 class="ak-title">{{ $isCreate ? 'Add Role' : 'Edit Role: '.$record->name }}</h1>
                <p class="ak-sub">Name the role, then tick what its users may open and do.</p>
            </div>
            <div class="ak-head-actions">
                <a href="{{ route('roles.index') }}" class="ak-btn ak-btn-outline"><span aria-hidden="true">←</span> Back to Roles</a>
            </div>
        </div>
    </x-slot>

    @include('partials.ui-style')

    <div class="ak-page">
        <x-status-message />
        @if ($errors->any())
            <div class="ak-alert ak-alert-error" role="alert"><b>Please fix the following before saving:</b>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="POST" action="{{ $isCreate ? route('roles.store') : route('roles.update', $record) }}" novalidate
            x-data="Object.assign(window.permissionMatrix({ permissions: {{ \Illuminate\Support\Js::from($selected) }}, tab: {{ \Illuminate\Support\Js::from($catalog[0]['key'] ?? 'operations') }} }), { saving: false })"
            @submit="saving = true">
            @csrf
            @unless ($isCreate) @method('PUT') @endunless

            <section class="uf-card" style="max-width: 880px">
                <header class="uf-card-head"><div><h2 class="uf-card-title"><span class="uf-step">1</span> Role</h2></div></header>
                <div class="uf-body">
                    <div class="uf-field">
                        <label for="name">Role name <span class="uf-req">*</span></label>
                        <input id="name" type="text" name="name" value="{{ old('name', $record->name) }}" required maxlength="255" autocomplete="off" @readonly($locked) @if ($isCreate) autofocus @endif>
                        @error('name') <p class="uf-error">{{ $message }}</p> @enderror
                    </div>
                    @if ($statusSupported && ! $locked)
                        <div class="uf-field" style="margin-top:16px; max-width: 320px">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="Active" @selected(old('status', $roleStatus) === 'Active')>Active</option>
                                <option value="Inactive" @selected(old('status', $roleStatus) === 'Inactive')>Inactive</option>
                            </select>
                            <p class="ak-help">Inactive roles are hidden when giving a role to a user. Nothing is deleted.</p>
                            @error('status') <p class="uf-error">{{ $message }}</p> @enderror
                        </div>
                    @endif
                    @if ($locked)
                        <p class="uf-note uf-note-warn" style="margin-top:12px">Super-Admin always has every permission; it cannot be renamed or changed here.</p>
                    @endif
                </div>
            </section>

            @unless ($locked)
                <section class="uf-card" style="margin-top:20px" aria-labelledby="uf-perms">
                    <header class="uf-card-head">
                        <div>
                            <h2 class="uf-card-title" id="uf-perms"><span class="uf-step">2</span> Permissions</h2>
                            <p class="uf-card-sub">Arranged like the application menus. Users with this role get everything ticked here.</p>
                        </div>
                        <span class="ak-count" x-text="permissions.length + ' selected'"></span>
                    </header>
                    @include('partials.permission-matrix', ['catalog' => $catalog, 'withRoles' => false])
                    @error('permissions') <p class="uf-error" style="padding:0 20px 16px">{{ $message }}</p> @enderror
                </section>
            @endunless

            <div class="uf-actions" style="margin-top:20px">
                <p>Fields marked <span class="uf-req">*</span> are required.</p>
                <div>
                    <a href="{{ route('roles.index') }}" class="ak-btn ak-btn-outline">Cancel</a>
                    <button type="submit" class="ak-btn ak-btn-primary" :disabled="saving"><span x-show="!saving">{{ $isCreate ? 'Create role' : 'Save changes' }}</span><span x-show="saving" x-cloak>Saving…</span></button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
