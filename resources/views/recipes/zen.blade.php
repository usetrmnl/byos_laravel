{{--@dump($data)--}}
@props(['size' => 'full'])
<x-trmnl::view size="{{ $size }}">
    <x-trmnl::layout>
        <x-trmnl::layout class="layout--col">
            <div class="b-h-gray-1">{{$data[0]['a']}}</div>
            @if (strlen($data[0]['q']) < 300 && $size != 'quadrant')
                <p class="value">{{ $data[0]['q'] }}</p>
            @else
                <p class="value--small">{{ $data[0]['q'] }}</p>
            @endif
        </x-trmnl::layout>
    </x-trmnl::layout>

    <div class="title_bar">
        <img class="image" src="https://img.icons8.com/books"/>
        <span class="title">Zen Quotes</span>
    </div>
</x-trmnl::view>
