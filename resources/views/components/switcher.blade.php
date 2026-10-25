@props(['variant' => 'list', 'flags' => false, 'codes' => false])
{{--
    A dependency-free language switcher. Each language is a POST form to the
    switcher route, so it works without JavaScript; style it through the
    data-localization-* hooks or pass classes.

      list      every language as a button (default)
      select    a <select> that submits on change (progressive: a button without JS)
      details   a <details> disclosure menu
--}}
@php
    $localization = app(\Atannex\Localization\Localization::class);
    $current = $localization->current();
    $label = fn ($locale) => trim(($flags ? ($locale->flag() ?? '').' ' : '').($codes ? strtoupper($locale->code) : $locale->native));
@endphp

@if ($localization->isMultilingual())
@if ($variant === 'select')
<form method="POST" action="{{ $localization->switchUrl() }}" data-localization-switcher="select" {{ $attributes }}>
    @csrf
    <label>
        <span class="sr-only">{{ __('localization::messages.choose') }}</span>
        <select name="locale" onchange="this.form.submit()">
            @foreach ($localization->locales() as $locale)
            <option value="{{ $locale->code }}" lang="{{ $locale->tag() }}" @selected($locale->is($current))>{{ $label($locale) }}</option>
            @endforeach
        </select>
    </label>
    <noscript><button type="submit">{{ __('localization::messages.language') }}</button></noscript>
</form>
@elseif ($variant === 'details')
<details data-localization-switcher="details" {{ $attributes }}>
    <summary aria-label="{{ __('localization::messages.current', ['language' => $current->native]) }}">{{ $label($current) }}</summary>
    <ul role="list">
        @foreach ($localization->locales() as $locale)
        <li>
            <form method="POST" action="{{ $localization->switchUrl($locale->code) }}">
                @csrf
                <button type="submit" lang="{{ $locale->tag() }}" @if ($locale->is($current)) aria-current="true" @endif>{{ $label($locale) }}</button>
            </form>
        </li>
        @endforeach
    </ul>
</details>
@else
<nav aria-label="{{ __('localization::messages.language') }}" data-localization-switcher="list" {{ $attributes }}>
    @foreach ($localization->locales() as $locale)
    <form method="POST" action="{{ $localization->switchUrl($locale->code) }}" style="display:inline">
        @csrf
        <button type="submit" lang="{{ $locale->tag() }}" title="{{ $locale->native }}" @if ($locale->is($current)) aria-current="true" @endif data-localization-locale="{{ $locale->code }}">{{ $label($locale) }}</button>
    </form>
    @endforeach
</nav>
@endif
@endif
