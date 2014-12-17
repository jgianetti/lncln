<?php
namespace Jan_Category;

class Category extends \ModelObject
{
     protected $_props = [
        'id',
        'name',
        'comments',
    ]
    ;
    
    protected $_validator = [
        'name' => ['not_null'],
    ];
}