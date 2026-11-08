{{-- A mail built from an editable template (App\Support\MailTemplates). Markdown is whitespace sensitive: keep this unindented. --}}
<x-mail::message>
@foreach ($parts as $part)
@if ($part['type'] === 'button')
<x-mail::button :url="$part['url']">
{{ $part['label'] }}
</x-mail::button>
@elseif ($part['type'] === 'table')
<x-mail::table>
| | |
|:--|:--|
@foreach ($part['rows'] as $label => $value)
| {{ $label }} | {{ str_replace('|', '/', (string) $value) }} |
@endforeach
</x-mail::table>
@else
{!! $part['markdown'] !!}
@endif

@endforeach
</x-mail::message>
