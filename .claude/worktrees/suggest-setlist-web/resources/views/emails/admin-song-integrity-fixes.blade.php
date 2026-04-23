<x-mail::message>
# Song integrity fixes applied

The following {{ count($fixes) }} fix(es) were automatically applied:

<x-mail::table>
| Song ID | Check | Field | Before | After |
|:--------|:------|:------|:-------|:------|
@foreach ($fixes as $fix)
| {{ $fix['song_id'] }} | {{ $fix['check'] }} | {{ $fix['field'] }} | {{ $fix['old_value'] }} | {{ $fix['new_value'] }} |
@endforeach
</x-mail::table>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
