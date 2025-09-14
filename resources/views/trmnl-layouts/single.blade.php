@props([
    'colorDepth' => '1bit',
])

<x-trmnl::screen colorDepth="{{$colorDepth}}">
    {!! $slot !!}
</x-trmnl::screen>
