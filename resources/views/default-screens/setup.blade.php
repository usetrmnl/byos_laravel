@props([
    'noBleed' => false,
    'darkMode' => false,
    'deviceVariant' => 'og',
    'deviceOrientation' => null,
    'colorDepth' => '1bit',
    'scaleLevel' => null,
])

<x-trmnl::screen colorDepth="{{$colorDepth}}" no-bleed="{{$noBleed}}" dark-mode="{{$darkMode}}"
                 device-variant="{{$deviceVariant}}" device-orientation="{{$deviceOrientation}}"
                 scale-level="{{$scaleLevel}}">
    <x-trmnl::view>
        <x-trmnl::layout>
            <x-trmnl::richtext gapSize="large" align="center">
                <x-trmnl::title>Welcome to BYOS Laravel!</x-trmnl::title>
                <x-trmnl::content>Your device is connected.</x-trmnl::content>
            </x-trmnl::richtext>
        </x-trmnl::layout>
        <x-trmnl::title-bar title="byos_laravel"/>
    </x-trmnl::view>
</x-trmnl::screen>
