{{-- Above create/edit pages of translatable resources (LocalizationPlugin). --}}
<div class="fi-section rounded-xl bg-white px-4 py-3 text-sm shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" data-localization-banner>
    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-gray-700 dark:text-gray-200">
        <x-filament::icon icon="heroicon-m-language" class="h-5 w-5 text-primary-500" />
        <span>{{ __('localization::messages.editing', ['language' => $locale->native]) }}</span>
        <span class="text-gray-500 dark:text-gray-400">
            @if ($locale->is($default))
                {{ __('localization::messages.editing_default') }}
            @else
                {{ __('localization::messages.editing_other', ['language' => $locale->native, 'default' => $default->native]) }}
            @endif
            {{ __('localization::messages.editing_hint') }}
        </span>
    </p>
</div>
