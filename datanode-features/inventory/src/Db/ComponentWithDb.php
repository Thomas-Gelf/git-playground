<?php

namespace IcingaFeature\Inventory\Db;

interface ComponentWithDb
{
    public function setDb(?DbConnection $db): void;
}
