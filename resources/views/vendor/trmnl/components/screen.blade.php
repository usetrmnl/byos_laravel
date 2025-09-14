@props([
    'noBleed' => false,
    'darkMode' => false,
    'deviceVariant' => 'og',
    'deviceOrientation' => null,
    'colorDepth' => '1bit',
    'scaleLevel' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=Inter:300,400,500" rel="stylesheet"/>
    @if (config('trmnl-blade.framework_css_url'))
        <link rel="stylesheet"
              href="{{ config('trmnl-blade.framework_css_url') }}">
    @else
        <link rel="stylesheet"
              href="https://usetrmnl.com/css/{{ config('trmnl-blade.framework_version', '1.2.0') }}/plugins.css">
    @endif
    @if (config('trmnl-blade.framework_js_url'))
        <script src="{{ config('trmnl-blade.framework_js_url') }}"></script>
    @else
        <script src="https://usetrmnl.com/js/{{ config('trmnl-blade.framework_version', '1.2.0') }}/plugins.js"></script>
    @endif
    <title>{{ $title ?? config('app.name') }}</title>
</head>
<body class="environment trmnl">
<div class="screen {{$noBleed ? 'screen--no-bleed' : ''}} {{ $darkMode ? 'dark-mode' : '' }} {{$deviceVariant ? 'screen--' . $deviceVariant : ''}} {{ $deviceOrientation ? 'screen--' . $deviceOrientation : ''}} {{ $colorDepth ? 'screen--' . $colorDepth : ''}} {{ $scaleLevel ? 'screen--scale-' . $scaleLevel : ''}}">
    {{ $slot }}
</div>
</body>
</html>
