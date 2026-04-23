<x-mail::message>
# Usage warning

Hi {{ $user->name }},

{{ $warningMessage }}

Plan: {{ strtoupper((string) data_get($usagePayload, 'plan.tier', '')) }}

Storage used: {{ number_format(((int) data_get($usagePayload, 'storage.used_bytes', 0)) / 1024 / 1024, 2) }} MB

AI operations this month: {{ number_format((int) data_get($usagePayload, 'ai.operations_used', 0)) }}

Review state: {{ str_replace('_', ' ', (string) data_get($usagePayload, 'review.state', 'clear')) }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
