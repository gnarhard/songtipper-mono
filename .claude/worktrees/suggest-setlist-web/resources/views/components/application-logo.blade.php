<picture>
    <source
        srcset="{{ asset('images/song_tipper_logo_dark.png') }}"
        media="(prefers-color-scheme: dark)"
    >
    <img
        src="{{ asset('images/song_tipper_logo_light.png') }}"
        alt="{{ config('app.name') }} logo"
        {{ $attributes->merge(['class' => 'block shrink-0 rounded-xl shadow-sm']) }}
    >
</picture>
