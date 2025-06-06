@props(['mashupLayout' => '1Tx1B'])

<x-trmnl::screen>
    <x-trmnl::mashup mashup-layout="{{ $mashupLayout }}">
        {{-- The slot is used to pass the content of the mashup --}}
        {!! $slot !!}
    </x-trmnl::mashup>
</x-trmnl::screen>
