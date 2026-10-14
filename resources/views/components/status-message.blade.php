@php
    $messages = collect([
        'success' => session('success'),
        'success_status' => session('status'),
        'success_message' => session('message'),
        'error' => session('error'),
    ])->filter();
@endphp
@foreach ($messages as $type => $text)
    <div class="ak-alert {{ str_starts_with($type, 'success') ? 'ak-alert-success' : 'ak-alert-error' }}" role="status">{{ $text }}</div>
@endforeach
