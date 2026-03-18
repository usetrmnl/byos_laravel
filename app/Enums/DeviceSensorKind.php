<?php

namespace App\Enums;

enum DeviceSensorKind: string
{
    case CARBON_DIOXIDE = 'carbon_dioxide';
    case HUMIDITY = 'humidity';
    case PRESSURE = 'pressure';
    case TEMPERATURE = 'temperature';
}
