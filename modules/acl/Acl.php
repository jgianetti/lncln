<?php
namespace Jan_Acl;

class Acl extends \ModelObject
{
     protected $_props = [
        'id',
        'name',
        'allow',
        'module',
        'action',
        'filter_criteria',
        'filter_value',
    ]
    ;
    
    protected $_validator = [
        'name'   => ['not_null'],
        'allow'  => ['not_null', 'numeric'],
        'module' => ['not_null'],
        'action' => ['not_null'],
    ];

}