@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ config('app.url') }}/brand/logo.png" class="logo" alt="{{ $slot }}">
<br>
{{ $slot }}
</a>
</td>
</tr>
