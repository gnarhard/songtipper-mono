<x-mail::message>
# Inactivity notice

Hi {{ $user->name }},

Your account has been inactive since {{ $lastActivityAt->toFormattedDateString() }}.

If no new activity is recorded, derived chart render images will be archived on {{ $archiveAt->toFormattedDateString() }}. Source PDFs and metadata stay intact, and renders can be regenerated on demand the next time you return.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
