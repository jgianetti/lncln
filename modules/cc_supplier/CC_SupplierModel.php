<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 3/08/12
 * Time: 16:01
 */

namespace Jan_CC_Supplier;

class CC_SupplierModel extends \DbDataMapper
{
    protected $_table_name = 'cc_supplier';

    public function search($search_data, $order_by = null, $limit = null)
    {
        $search_data = $this->sanitizeSearchData($search_data);

        $where = ' 1=1 ';
        if ($search_data['name'])       $where .= ' AND name LIKE "%'.$search_data['name'].'%" ';
        if ($search_data['address'])    $where .= ' AND address LIKE "%'.$search_data['address'].'%" ';
        if ($search_data['phone'])      $where .= ' AND phone LIKE "%'.$search_data['phone'].'%" ';
        if ($search_data['email'])      $where .= ' AND email LIKE "%'.$search_data['email'].'%" ';
        if (!is_null($search_data['deleted']))  $where .= ' AND deleted="'.$search_data['deleted'].'"';

        return $this->fetchAll(
            'SELECT * ' .
                ' FROM cc_supplier ' .
                ($where ? 'WHERE ' . $where : '') .
                ($order_by ? ' ORDER BY ' . $order_by : '') .
                ($limit ? ' LIMIT ' . $limit : '')
        );
    }

    public function searchCount($search_data = null, $order_by = null, $limit = null)
    {
        $search_data = $this->sanitizeSearchData($search_data);

        $where = ' 1=1 ';

        if ($search_data['name'])       $where .= ' AND name LIKE "%'.$search_data['name'].'%" ';
        if ($search_data['address'])    $where .= ' AND address LIKE "%'.$search_data['address'].'%" ';
        if ($search_data['phone'])      $where .= ' AND phone LIKE "%'.$search_data['phone'].'%" ';
        if ($search_data['email'])      $where .= ' AND email LIKE "%'.$search_data['email'].'%" ';
        if (!is_null($search_data['deleted']))  $where .= ' AND deleted="'.$search_data['deleted'].'"';

        return $this->query(
            'SELECT COUNT(id) ' .
                ' FROM cc_supplier ' .
                ($where ? 'WHERE ' . $where : '')
        )->fetchColumn();
    }

    public function sanitizeSearchData($search_data = null)
    {
        if (!$search_data) $search_data = Array();

        if (!isset($search_data['name'])) $search_data['name'] = null;
        else $search_data['name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", trim(str_replace(array(',', '  '),' ', $search_data['name'])));

        if (!isset($search_data['address'])) $search_data['address'] = null;
        else $search_data['address'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", trim(str_replace(array(',', '  '),' ', $search_data['address'])));

        if (!isset($search_data['phone'])) $search_data['phone'] = null;
        if (!isset($search_data['email'])) $search_data['email'] = null;

        if (!isset($search_data['deleted']) || $search_data['deleted'] == "") $search_data['deleted'] =  null;

        return $search_data;
    }

}
