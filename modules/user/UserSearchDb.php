<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;

class UserSearchDb implements UserSearchInterface
{
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function get($select = 'full', $search_data = null, $order_by = null, $limit = null)
    {
        if ($select == 'full') $select = 'u.*,schedule';
        elseif ($select == 'search') $select = 'u[id,name,last_name,dni,rfid,email,in_school,comments,deleted, cat_ids, cat_names, cc_cat_ids, cc_cat_names],schedule';

        $this->buildWhere($search_data)
            ->buildOrderBy($order_by);

        // SCHEDULE table data
        if (strpos($select, 'schedule')) {
            foreach (days_str() as $day) $this->db->select(' DATE_FORMAT(u_s.'.$day.'_in, "%H:%i") AS '.$day.'_in ')->select(' DATE_FORMAT(u_s.'.$day.'_out, "%H:%i") AS '.$day.'_out ');
            $this->db->leftJoin('user_schedule AS u_s ON u_s.id = u.id');
            $select = str_replace(',schedule','',$select);
        }

        return $this->db->from('user AS u')
            ->select($select)
            ->limit($limit)
//            ->echoSql()
//            ->echoValues()
            ->prepareFetchAll();
    }

    public function getById($select,$id)
    {
        if (!($users = $this->get($select,array('id'=>$id),null,1))) return array();
        else return $users[0];
    }

    public function count($search_data = null)
    {
        $this->buildWhere($search_data);
        return $this->db->select('COUNT(u.id) AS cant')->from('user AS u')->prepareFetchColumn();
    }

    public function buildWhere($search_data)
    {
        if (!empty($search_data['id']))          $this->db->where('u.id',$search_data['id']);
        if (!empty($search_data['id_not']))      $this->db->where('u.id NOT IN('.implode(',', array_fill(0, count($search_data['id_not']), '?')).')')->stmt_values($search_data['id_not']);
        if (!empty($search_data['user']))        $this->db->where('u.user',$search_data['user']);
        if (!empty($search_data['pwd']))         $this->db->where('u.pwd',sha1($search_data['pwd']));
        if (!empty($search_data['dni']))         $this->db->where('u.dni LIKE ?')->stmt_values('%' . $search_data['dni'] . '%');
        if (!empty($search_data['rfid']))        $this->db->where('u.rfid LIKE ?')->stmt_values('%' . $search_data['rfid'] . '%');
        if (!empty($search_data['fullname']))    $this->db->where('(CONCAT(u.name," ", u.last_name) LIKE ? OR CONCAT(u.last_name," ", u.name) LIKE ?)')->stmt_values(array_fill(0,2,'%'.iconv("UTF-8", "ISO-8859-1//TRANSLIT", trim(str_replace(array(',', '  '),' ', $search_data['fullname']))).'%'));
        if (!empty($search_data['category']))    $this->db->where('( FIND_IN_SET(?, u.cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cat_ids) ', count($search_data['category'])-1) . ' )')->stmt_values($search_data['category']);
        if (!empty($search_data['cc_category'])) $this->db->where('( FIND_IN_SET(?, u.cc_cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cc_cat_ids) ', count($search_data['cc_category'])-1) . ' )')->stmt_values($search_data['cc_category']);
        if (!empty($search_data['email']))       $this->db->where('u.email LIKE ?')->stmt_values('%'.$search_data['email'].'%');
        if (!empty($search_data['barcode']))     $this->db->where('u.barcode LIKE ?')->stmt_values('%'.$search_data['barcode'].'%');
        if (isset($search_data['in_school']) && $search_data['in_school'] != '') $this->db->where('u.in_school',$search_data['in_school'] ? 1 : 0);
        if (isset($search_data['deleted'])) {
            if ($search_data['deleted']) $this->db->where('u.deleted', 1);
            else $this->db->where('(u.deleted = 0 OR u.deleted IS NULL)');
        }
        return $this;
    }

    public function buildOrderBy($order = null)
    {
        if (!$order) $order = 'fullname';
        list($order_by, $order_dir) = array_pad(explode(' ', $order),2,null);

        switch ($order_by) {
            case 'category'  : $order_by = 'u.cat_names '.($order_dir?:null); break;
            case 'cc_category'  : $order_by = 'u.cc_cat_names '.($order_dir?:null); break;
            case 'fullname'  : $order_by = 'u.last_name '.($order_dir?:null).', u.name '.($order_dir?:null); break;
            default          : $order_by = $order_by . ' ' . $order_dir;
        }

        $this->db->orderBy($order_by);
        return $this;
    }

}