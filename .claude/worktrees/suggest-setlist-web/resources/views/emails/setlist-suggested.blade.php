<x-mail::message>
# New Suggested Setlist

Hi {{ Str::before($project->owner->name, ' ') }},

**{{ $project->name }}** received a suggested setlist.

**From:** {{ $submitterName }} ({{ $submitterEmail }})

@if ($eventName)
**Event:** {{ $eventName }}
@endif

@if ($note)
**Note:** {{ $note }}
@endif

**Songs ({{ $songs->count() }}):**

@foreach ($songs as $index => $projectSong)
{{ $index + 1 }}. {{ $projectSong->title }}{{ $projectSong->instrumental ? ' (instrumental)' : '' }} — {{ $projectSong->artist }}
@endforeach

You can reply directly to this email to respond to {{ $submitterName }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
