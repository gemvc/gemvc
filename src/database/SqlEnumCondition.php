<?php

namespace Gemvc\Database;

enum SqlEnumCondition: string
{
    case Equal = ' = ';

    case Bigger = ' > ';

    case Less = ' < ';

    case BiggerEqual = ' >= ';

    case LessEqual = ' <= ';

    case IsNull = ' IS NULL ';

    case NotNull = 'IS NOT NULL';

    case Not = ' != ';

    case Like = ' LIKE ';

    case Descending = ' DESC ';

    case Ascending = ' ASC ';

    case Between = ' BETWEEN ';
}
