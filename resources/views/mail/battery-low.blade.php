<x-mail::message>
# Battery Low

The battery of {{ $device->name }} is running below {{ $device->battery_percent }}%. Please charge your device soon.

{{ config('app.name') }}
</x-mail::message>
