<?php

namespace Icinga\Module\Idbdash;

use Icinga\Module\Icingadb\Model\Host;
use ipl\Sql\Expression;

class IcingaHost extends Host
{
    public function getColumns()
    {
        return parent::getColumns() + [
            'len' => new Expression(
                'LENGTH(host.name)'
            ),
        ];
    }

    public function getColumnDefinitions()
    {
        return parent::getColumnDefinitions() + [
                'len' => t('Host Name Length'),
        ];
    }
}
