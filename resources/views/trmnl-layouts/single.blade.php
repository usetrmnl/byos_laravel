@props([
    'noBleed' => false,
    'darkMode' => false,
    'deviceVariant' => 'og',
    'deviceOrientation' => null,
    'colorDepth' => '1bit',
    'scaleLevel' => null,
    'cssVariables' => null,
])

@if(config('app.puppeteer_window_size_strategy') === 'v2')
    <x-trmnl::screen colorDepth="{{$colorDepth}}" no-bleed="{{$noBleed}}" dark-mode="{{$darkMode}}"
                     device-variant="{{$deviceVariant}}" device-orientation="{{$deviceOrientation}}"
                     scale-level="{{$scaleLevel}}"
                     :css-variables="$cssVariables">
        {!! $slot !!}
    </x-trmnl::screen>
@else
    <x-trmnl::screen colorDepth="{{$colorDepth}}" no-bleed="{{$noBleed}}" dark-mode="{{$darkMode}}"
                     device-variant="{{$deviceVariant}}" device-orientation="{{$deviceOrientation}}"
                     scale-level="{{$scaleLevel}}"
                     :css-variables="$cssVariables">
        {!! $slot !!}
    </x-trmnl::screen>
@endif
