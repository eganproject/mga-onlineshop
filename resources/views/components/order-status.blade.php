@props(['status'])
<span {{ $attributes->merge(['class' => 'badge '.$status->badgeClasses()]) }}>
    <span class="size-1.5 rounded-full bg-current"></span>
    {{ $status->label() }}
</span>
