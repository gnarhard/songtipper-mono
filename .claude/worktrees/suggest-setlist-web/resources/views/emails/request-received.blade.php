<x-mail::message>
@if ($isTipOnly)
# You Got Tipped!
@elseif ($isOriginal)
# New Original Request
@else
# New Song Request
@endif

Hi {{ Str::before($songRequest->project->owner->name, ' ') }},

**{{ $songRequest->project->name }}** received a new {{ $isTipOnly ? 'tip' : 'request' }}.

@if ($isOriginal)
**Request:** Play an original
@elseif (!$isTipOnly)
**Song:** {{ $songRequest->song->title }} by {{ $songRequest->song->artist }}
@endif

@if ($tipDisplay)
**Tip:** {{ $tipDisplay }}
@endif

@if ($songRequest->audienceProfile?->display_name)
**From:** {{ $songRequest->audienceProfile->display_name }}
@endif

@if ($songRequest->note)
**Note:** {{ $songRequest->note }}
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
