<?php
namespace Jan_User;

class User extends \ModelObject
{
    protected $_props = [
        'id',
        'type',
        'user',
        'pwd',
        'rfid',
        'barcode',
        'name',
        'last_name',
        'dni',
        'file_number',
        'email',
        'last_login',
        'homepage',
        'in_school',
        'last_movement',
        'comments',
        'cat_ids',
        'cat_names',
        'cc_cat_ids',
        'cc_cat_names',
        'deleted',
        'deleted_on',
        'deleted_by',
    ]
    ;

    protected $_validator = [
        'name'      => ['not_null'],
        'last_name' => ['not_null'],
        'dni'       => ['not_null'],
        'rfid'      => ['numeric'],
    ];

    /**
     * @param array $cats   : [id,name]
     * @return $this
     */
    public function setCats($cats)
    {
        $ids = $names = '';
        foreach ($cats as $cat) {
            $ids   .= $cat['id'] .',';
            $names .= $cat['name'] . ',';
        }
        $this['cat_ids'] = trim($ids, ',');
        $this['cat_names'] = trim($names, ',');
    }

    /**
     * @param array $cats   : [id,name]
     * @return $this
     */
    public function setCCCats($cats)
    {
        $ids = $names = '';
        foreach ($cats as $cat) {
            $ids   .= $cat['id'] .',';
            $names .= $cat['name'] . ',';
        }
        $this['cc_cat_ids'] = trim($ids, ',');
        $this['cc_cat_names'] = trim($names, ',');
    }
}