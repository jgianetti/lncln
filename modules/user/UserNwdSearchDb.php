<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;

class UserNwdSearchDb
{
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function get($select, $search_data = null, $order_by = null, $limit = null)
    {
        $this->buildWhere($search_data);

        return $this->db->from('user_non_working_days AS u_nwd')
            ->select($select)
            ->orderBy($order_by)
            ->limit($limit)
//            ->echoSql()
//            ->echoValues()
            ->prepareFetchAll();
    }

    public function getById($select,$id)
    {
        if (!($rows = $this->get($select,array('id'=>$id),null,1))) return array();
        else return $rows[0];
    }

    public function count($search_data = null)
    {
        $this->buildWhere($search_data);
        return $this->db->select('COUNT(u_nwd.id) AS cant')->from('user_non_working_days AS u_nwd')->prepareFetchColumn();
    }

    public function buildWhere($search_data)
    {
        $search_data = $this->sanitizeSearchData($search_data);
        if (!empty($search_data['id']))          $this->db->where('u_nwd.id',$search_data['id']);
        if (!empty($search_data['user_id']))     $this->db->where('u_nwd.user_id',$search_data['user_id']);

        // from range
        if (!empty($search_data['from_from']) && !empty($search_data['from_to'])) $this->db->where('u_nwd.from BETWEEN (? AND ?)')->stmt_values($search_data['from_from'])->stmt_values($search_data['from_to']);
        elseif (!empty($search_data['from_from'])) $this->db->where('u_nwd.from > ?')->stmt_values($search_data['from_from']);
        elseif (!empty($search_data['from_to']))   $this->db->where('u_nwd.from < ?')->stmt_values($search_data['from_to']);
        elseif (!empty($search_data['from']))      $this->db->where('u_nwd.from',$search_data['from']);

        // to range
        if (!empty($search_data['to_from']) && !empty($search_data['to_to'])) $this->db->where('u_nwd.to BETWEEN (? AND ?)')->stmt_values($search_data['to_from'])->stmt_values($search_data['to_to']);
        elseif (!empty($search_data['to_from'])) $this->db->where('u_nwd.to > ?')->stmt_values($search_data['to_from']);
        elseif (!empty($search_data['to_to']))   $this->db->where('u_nwd.to < ?')->stmt_values($search_data['to_to']);
        elseif (!empty($search_data['to']))      $this->db->where('u_nwd.to',$search_data['to']);

        return $this;
    }

    public function isWorkingDay($user_id, $date = null)
    {
        if (!$date) $date = date('Y-m-d');
        elseif (is_int($date)) $date = date('Y-m-d', $date);
        elseif (strpos($date, '/')) $date = date('Y-m-d', strtotime(str_replace('/','-',$date)));

        return ($this->db->select('*')->from('user_non_working_days')->where('user_id', $user_id)->where('`from` <= ? AND `to` >= ?')->stmt_values(array($date,$date))->prepareFetchAll() ? 0 : 1);
    }

    public function sanitizeSearchData($search_data)
    {
        // From Range
        if (!empty($search_data['from_from']))  $search_data['from_from']   = date('Y-m-d', strtotime(str_replace('/','-',$search_data['from_from'])));
        if (!empty($search_data['from_to']))    $search_data['from_to']     = date('Y-m-d', strtotime(str_replace('/','-',$search_data['from_to'])));
        if (!empty($search_data['from']))       $search_data['from']        = date('Y-m-d', strtotime(str_replace('/','-',$search_data['from'])));

        // To Range
        if (!empty($search_data['to_from']))    $search_data['to_from'] = date('Y-m-d', strtotime(str_replace('/','-',$search_data['to_from'])));
        if (!empty($search_data['to_to']))      $search_data['to_to']   = date('Y-m-d', strtotime(str_replace('/','-',$search_data['to_to'])));
        if (!empty($search_data['to']))         $search_data['to']      = date('Y-m-d', strtotime(str_replace('/','-',$search_data['to'])));

        return $search_data;
    }
}