@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<img src="{{ asset('images/song_tipper_logo_light.png') }}" alt="{{ trim(strip_tags($slot)) }} logo" width="36" height="36" style="vertical-align: middle; margin-right: 10px; border: 0; display: inline-block; border-radius: 40px;">
<span style="vertical-align: middle; display: inline-block;">{!! $slot !!}</span>
</a>
</td>
</tr>
