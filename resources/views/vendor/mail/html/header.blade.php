@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@php($appName = config('app.name', 'Laravel'))
@php($localLogoPath = public_path('logo.png'))
@php($defaultLogoUrl = 'https://www.sobirentals.com/assets/images/logo/logo.png')
@if (trim($slot) === $appName || trim($slot) === 'Laravel')
    <img src="{{ $defaultLogoUrl }}" class="logo" alt="{{ $appName }} Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
