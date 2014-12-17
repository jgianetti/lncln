<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
// TODO: AI to determine intersected ranges

namespace Jan_User;

class UserNwdRepositoryDb
{
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function getById($id)
    {
        if (!($nwds = $this->db->select('*')->from('user_non_working_days')->where('id', $id)->prepareFetchAll())) return array();
        return $nwds[0];
    }


    /**
     * @param array $row
     * @param null $user_ids
     * @return bool
     */
    public function save(array $row, $user_ids = null)
    {
        if (!$user_ids && empty($row['user_id'])) return false;
        if (empty($row['from']) || empty($row['to'])) return false;

        foreach ($row as $key => $value) {
            // Set empty values to NULL
            if (!$value) $row[$key] = null;
            // Sanitize dates
            elseif (($key == 'from') || ($key == 'to')) $row[$key] = date('Y-m-d', strtotime(str_replace('/','-', $value)));
        }

        // UPDATE
        if (!empty($row['id'])) return $this->db->mod('user_non_working_days', $row);

        if (!$user_ids) $user_ids = array($row['user_id']);
        elseif (is_string($user_ids)) $user_ids = explode(',', $user_ids);
        unset($row['user_id']);

        // INSERT
        $sql = 'INSERT INTO user_non_working_days (`user_id`,`'.implode('`,`',array_keys($row)).'` ) VALUES ';
        $values = array();

        foreach ($user_ids as $user_id) {
            $sql .= '(?,'.implode(',', array_fill(0, count($row), '?')).'),';
            $values = array_merge($values, array($user_id), array_values($row));
        }
        $sql = trim($sql, ',');
        $this->db->prepareExec($sql, $values);

        return true;
    }

    public function del($id)
    {
        return $this->db->del('user_non_working_days', $id);
    }

    public function delByUserIds($user_ids)
    {
        return $this->db->deleteFrom('user_non_working_days')->where('user_id',$user_ids)->prepareExec();
    }
}