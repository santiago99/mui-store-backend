<?php

namespace App\Enums;

enum ProductFieldType: string
{
    case Integer = 'integer';
    case Float = 'float';
    case String = 'string';
    case Enum = 'enum';
}
