<x-mail::message>
# Reward Earned!

Hi {{ Str::before($rewardThreshold->project->owner->name, ' ') }},

An audience member just earned a reward on **{{ $projectName }}**.

**Reward:** {{ $rewardThreshold->reward_label }}
**Threshold:** {{ $thresholdDisplay }} in cumulative tips

@if ($audienceProfile->display_name)
**From:** {{ $audienceProfile->display_name }}
@endif

@if ($isPhysicalReward)
The audience member has been instructed to approach you to receive their reward.
@else
This reward was automatically applied.
@endif

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
