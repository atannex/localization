@props(['url' => null, 'hreflang' => true])
{{-- og:locale + og:locale:alternate, and (by default) the hreflang links. --}}
@php($localization = app(\Atannex\Localization\Localization::class))
<meta property="og:locale" content="{{ $localization->current()->regional() }}">
@foreach ($localization->others() as $alternate)
<meta property="og:locale:alternate" content="{{ $alternate->regional() }}">
@endforeach
@if ($hreflang)
<x-localization::hreflang :url="$url" />
@endif
