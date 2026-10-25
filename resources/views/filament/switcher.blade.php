{{-- Admin panel language switcher (LocalizationPlugin): same POST route as the site's. --}}
@php
    $localization = app(\Atannex\Localization\Localization::class);
    $current = $localization->current();
@endphp

@if ($localization->isMultilingual())
<x-filament::dropdown placement="bottom-end" teleport>
    <x-slot name="trigger">
        <x-filament::button color="gray" outlined size="sm" icon="heroicon-m-language"
                            :tooltip="__('localization::messages.language')"
                            :aria-label="__('localization::messages.current', ['language' => $current->native])">
            {{ strtoupper($current->code) }}
        </x-filament::button>
    </x-slot>

    <x-filament::dropdown.list>
        @foreach ($localization->locales() as $locale)
        <x-filament::dropdown.list.item
            tag="form"
            method="post"
            :action="$localization->switchUrl($locale->code)"
            :icon="$locale->is($current) ? 'heroicon-m-check' : null"
            :color="$locale->is($current) ? 'primary' : 'gray'"
        >
            {{-- The item adds the CSRF field itself when tag="form". --}}
            <span lang="{{ $locale->tag() }}">{{ $locale->native }}</span>
        </x-filament::dropdown.list.item>
        @endforeach
    </x-filament::dropdown.list>
</x-filament::dropdown>
@endif
