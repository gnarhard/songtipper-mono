@php
    $themeMetaColorLight = config('songtipper_theme.meta.light');
    $themeMetaColorDark = config('songtipper_theme.meta.dark');
@endphp

<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $themeMetaColorLight }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $themeMetaColorDark }}">
<meta name="theme-color" content="{{ $themeMetaColorLight }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="16x16" media="(prefers-color-scheme: light)" href="{{ asset('favicon-light-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" media="(prefers-color-scheme: light)" href="{{ asset('favicon-light-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" media="(prefers-color-scheme: dark)" href="{{ asset('favicon-dark-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" media="(prefers-color-scheme: dark)" href="{{ asset('favicon-dark-32x32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
