<?php

namespace Packages\Configuration\Enum;

enum SettingValueType: string
{
    case STRING = 'S';

    case NULL = 'NULL';

    case BOOLEAN = 'BOOL';

    case LIST = 'L';

    case MAP = 'M';
}
