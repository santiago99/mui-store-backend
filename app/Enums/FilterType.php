<?php

namespace App\Enums;

enum FilterType: string
{
    case Textfield = 'textfield';
    case Range = 'range';
    case Checkboxes = 'checkboxes';
    case Select = 'select';
    case SingleCheckbox = 'single checkbox';
}
