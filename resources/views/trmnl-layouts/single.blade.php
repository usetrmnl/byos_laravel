@props([
    'noBleed' => false,
    'darkMode' => false,
    'deviceVariant' => 'og',
    'deviceOrientation' => null,
    'colorDepth' => '1bit',
    'scaleLevel' => null,
])

@if(config('app.puppeteer_window_size_strategy') === 'v1')
    <x-trmnl::screen colorDepth="{{$colorDepth}}">
        {!! $slot !!}
    </x-trmnl::screen>
@else
    <x-trmnl::screen colorDepth="{{$colorDepth}}" no-bleed="{{$noBleed}}" dark-mode="{{$darkMode}}"
                     device-variant="{{$deviceVariant}}" device-orientation="{{$deviceOrientation}}"
                     scale-level="{{$scaleLevel}}">
        {!! $slot !!}
    </x-trmnl::screen>
@endif
