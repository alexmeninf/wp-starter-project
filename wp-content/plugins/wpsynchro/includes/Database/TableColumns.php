<?php

namespace WPSynchro\Database;

/**
 * Class for a database table column data
 * @since 1.6.0
 */
class TableColumns
{

    public $string = [];
    public $numeric = [];
    public $bit = [];
    public $binary = [];
    public $unknown = [];

    public function __construct()
    {
    }

    public function isString($column)
    {
        return isset($this->string[$column]);
    }

    public function isNumeric($column)
    {
        return isset($this->numeric[$column]);
    }

    public function isBit($column)
    {
        return isset($this->bit[$column]);
    }

    public function isBinary($column)
    {
        return isset($this->binary[$column]);
    }
}
