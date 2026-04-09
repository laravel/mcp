@props(['title' => null, 'libraryScripts' => ''])
@php
    $mcpSdk = app('mcp.sdk');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if($title)
    <title>{{ $title }}</title>
    @endif
    <script>{!! $mcpSdk !!}</script>
    {!! $libraryScripts !!}
    {{ $head ?? '' }}
</head>
<body {{ $attributes }}>
    {{ $slot }}
</body>
</html>
