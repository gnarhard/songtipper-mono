<x-mail::message>
# Admin usage alert

Account: {{ $user->email }}

Flag type: {{ $flag->type }}

Severity: {{ $flag->severity }}

Summary: {{ $flag->summary }}

Opened at: {{ $flag->opened_at?->toDayDateTimeString() }}

Auto blocked: {{ $flag->auto_blocked ? 'Yes' : 'No' }}

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
