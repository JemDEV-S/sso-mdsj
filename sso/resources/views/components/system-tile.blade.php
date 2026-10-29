@props(['system', 'index' => 0])
@php
    $key = mb_strtolower($system->code.' '.$system->name);
    $icon = 'apps';
    $tone = ['blue', 'teal', 'gold', 'navy'][$index % 4];
    if (str_contains($key, 'sgd') || str_contains($key, 'document')) {
        $icon = 'document'; $tone = 'blue';
    } elseif (str_contains($key, 'mesa') || str_contains($key, 'partes')) {
        $icon = 'inbox'; $tone = 'teal';
    } elseif (str_contains($key, 'rrhh') || str_contains($key, 'human') || str_contains($key, 'papeleta')) {
        $icon = 'users'; $tone = 'gold';
    } elseif (str_contains($key, 'licencia') || str_contains($key, 'obra')) {
        $icon = 'building'; $tone = 'navy';
    } elseif (str_contains(mb_strtolower($system->description ?? ''), 'document')) {
        $icon = 'document'; $tone = 'blue';
    }
@endphp
<a class="system-card tone-{{ $tone }}" href="{{ $system->launch_url }}" @if($system->description) aria-describedby="system-description-{{ $index }}" @endif>
    <span class="system-icon"><x-icon :name="$icon" /></span>
    <h3>{{ $system->name }}</h3>
    @if($system->description)<span class="system-description" id="system-description-{{ $index }}">{{ $system->description }}</span>@endif
</a>
