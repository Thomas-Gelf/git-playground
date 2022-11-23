<?php

namespace IcingaFeature\Inventory\Db;

trait SimpleDbBasedComponent
{
    protected ?DbConnection $db= null;

    public function setDb(?DbConnection $db): void
    {
        $this->db = $db;
    }
}
