@props(['url' => null, 'default' => true])
{{--
    <link rel="alternate" hreflang> for every supported locale, plus
    x-default (the address where the visitor's preferences decide). Renders nothing for one locale.
--}}
@php($localization = app(\Atannex\Localization\Localization::class))
@if ($localization->isMultilingual())
@foreach ($localization->alternates($url) as $code => $href)
<link rel="alternate" hreflang="{{ $localization->get($code)->tag() }}" href="{{ $href }}">
@endforeach
@if ($default)
<link rel="alternate" hreflang="x-default" href="{{ $localization->neutralUrl($url) }}">
@endif
@endif
