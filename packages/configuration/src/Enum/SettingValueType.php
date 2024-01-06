<?php

namespace Packages\Configuration\Enum;

enum SettingValueType: string
{
    case STRING = 'S';

    case BOOLEAN = 'BOOL';

    case LIST = 'L';
}
