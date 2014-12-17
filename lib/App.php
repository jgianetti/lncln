<?php
/**
 * Object to inject into all Controllers to give them access to Sitewide objects
 *
 */
class App
{
    /**
     * @var Request
     */
    public $request;

    /**
     * @var Session
     */
    public $session;

    /**
     * @var Array
     */
    public $cfg;

    /**
     * @var Db
    */
    public $db;

    /**
     * @var Array
     */
    public $lang;

    public function __construct(array $objects = [])
    {
        $this->setObjects($objects);
    }
    
    public function setObjects(array $objects = [])
    {
        foreach (get_class_vars(__CLASS__) as $obj => $val) if (!empty($objects[$obj])) $this->{$obj} = $objects[$obj];
        return $this;
    }
}