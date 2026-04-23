<x-mail::message>
# New user signup

Name: {{ $user->name }}

Email: {{ $user->email }}

Primary instrument: {{ $user->instrument_type ?? 'Not set' }}

Secondary instrument: {{ $user->secondary_instrument_type ?? 'Not set' }}

Signed up at: {{ $user->created_at->toDayDateTimeString() }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
