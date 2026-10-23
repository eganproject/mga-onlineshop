@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<span class="brand">{!! $slot !!}</span><span class="brand-dot">.</span>
</a>
</td>
</tr>
