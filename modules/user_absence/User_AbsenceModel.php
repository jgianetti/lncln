<?php
namespace Jan_User_Absence;

class User_AbsenceModel extends \DbDataMapper
{
    protected $_table_name = 'user_absence';

    public function search($search_data, $order_by = null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET Users Absences
        $sql = 'SELECT u_a.*, ' .
                ' u.name AS name, u.last_name AS last_name, u.email AS email, u.cat_names AS cat_names, u.cc_cat_names AS cc_cat_names ' .
            ' FROM user_absence AS u_a ' .
            ' INNER JOIN user AS u ON u.id = u_a.user_id  ' .
            ' WHERE ' . $where['sql'] .
            ($order_by ? ' ORDER BY ' . $order_by : '') .
            ($limit ? ' LIMIT ' . $limit : '');

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        return $this->rowsToMustache($stmt->fetchAll($this->_fetch_mode));
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET Search num rows
        $sql = 'SELECT COUNT(DISTINCT u_a.id) ' .
            ' FROM user_absence AS u_a ' .
            ' JOIN user AS u ON u.id = u_a.user_id  ' .
            ' WHERE ' . $where['sql']
        ;

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchColumn();
    }

    public function getByIds($ids, $order_by = null, $limit = null)
    {
        return $this->search(array('id' => $ids), $order_by, $limit);
    }

    public function getById($id)
    {
        if ($row = $this->getByIds($id, null, 1)) return $row[0];
        else return array();
    }


    /**
     * @param null $search_data
     * @return array : [where]=Where clause;[values]=stmt_values
     */
    public function buildSearchWhere($search_data = null)
    {
        $search_data = $this->sanitizeSearchData($search_data);
        $stmt_values = array();
        $where = ' u.deleted = 0 ';

        if (isset($search_data['id'])) {
            // allows to search among multiple ids
            if (!is_array($search_data['id'])) $search_data['id'] = explode(",", str_replace(' ', '', $search_data['id']));

            $where .= ' AND u_a.id IN ('.implode(',', array_fill(0, count($search_data['id']), '?')).')';
            $stmt_values = array_merge($stmt_values, $search_data['id']);
        }

        if (isset($search_data['user_id'])) {
            $where .= ' AND u_a.user_id = ? ';
            $stmt_values[] = $search_data['user_id'];
        }
        elseif (isset($search_data['fullname'])) {
            $where .= ' AND CONCAT(u.name, " ",u.last_name) LIKE ?';
            $stmt_values[] = '%'.$search_data['fullname'].'%';
        }

        if (isset($search_data['date'])) {
            $where .= ' AND DATE(u_a.date) = ? ';
            $stmt_values[] = $search_data['date'];
        }

        if (isset($search_data['date_from']) && isset($search_data['date_to'])) {
            $where .= ' AND u_a.date BETWEEN ? AND ? ';
            $stmt_values[] = $search_data['date_from'];
            $stmt_values[] = $search_data['date_to'];
        }
        elseif (isset($search_data['date_from'])) {
            $where .= 'AND u_a.date >= ? ';
            $stmt_values[] = $search_data['date_from'];
        }
        elseif (isset($search_data['date_to'])) {
            $where .= 'AND u_a.date <= ? ';
            $stmt_values[] = $search_data['date_to'];
        }

        if (isset($search_data['category'])) {
            $where .= ' AND ( FIND_IN_SET(?, u.cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cat_ids) ', count($search_data['category'])-1) . ' ) ';
            $stmt_values = array_merge($stmt_values,$search_data['category']);
        }

        if (isset($search_data['cc_category'])) {
            $where .= ' AND ( FIND_IN_SET(?, u.cc_cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cc_cat_ids) ', count($search_data['cc_category'])-1) . ' ) ';
            $stmt_values = array_merge($stmt_values,$search_data['cc_category']);
        }

        return array('sql' => $where, 'values' => $stmt_values);
    }

    public function add($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // user_absence
            $stmt = $this->prepare('INSERT INTO `user_absence` (`user_id`, `date`, `comments`) VALUES (?,?,?)');
            $stmt_values = array($row['user_id'], $row['date'], $row['comments']);
            $stmt->execute($stmt_values);

            $this->commit();

            return $this->lastInsertId();
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            return false;
        }
    }

    public function mod($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // user_absence
            $stmt = $this->prepare('UPDATE `user_absence` SET `date` = ?, `comments` = ? WHERE `id` = ? LIMIT 1');
            $stmt_values = array($row['date'], $row['comments'], $row['id']);
            $stmt->execute($stmt_values);

            $this->commit();

            return true;
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            return false;
        }
    }

    public function del($ids)
    {
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));

        $this->beginTransaction();

        try {
            $stmt = $this->prepare('DELETE FROM `user_absence` WHERE `id` IN ('.implode(',', array_fill(0, count($ids), '?')).') LIMIT ' . count($ids));
            $stmt->execute($ids);

            $this->commit();
            return true;
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            return false;
        }
    }


    public function sanitizeSearchData($search_data = null)
    {
        if (!$search_data) return array();
        $return = array();

        if (isset($search_data['id']) && $search_data['id']) $return['id'] = $search_data['id'];

        if (isset($search_data['user_id']) && ctype_xdigit($search_data['user_id'])) $return['user_id'] = $search_data['user_id'];
        elseif (isset($search_data['fullname']) && $search_data['fullname']) $return['fullname'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['fullname']);

        if (isset($search_data['date']) && $search_data['date']) {
            // from DataTables filter
            if ($search_data['date'] == '~') unset($search_data['date']);
            elseif (strpos($search_data['date'],'~') !== false) {
                $tmp = explode('~', $search_data['date']);
                if ($tmp[0]) $search_data['date_from'] = $tmp[0];
                if (isset($tmp[1]) && $tmp[1]) $search_data['date_to'] = $tmp[1];
            }
            else { // direct search
                $search_data['date'] = str_replace('/','-',$search_data['date']);
                if (!strtotime($search_data['date'])) $search_data['date'] = date('Y-m-d');
                // year only = year-01-01
                elseif (is_numeric($search_data['date'])) $search_data['date'] = date('Y-m-d', strtotime($search_data['date'].'-01-01'));
                else $search_data['date'] = date('Y-m-d', strtotime($search_data['date']));

                $return['date'] = $search_data['date'];
            }
        }

        // date_from
        if (isset($search_data['date_from'])) {
            $search_data['date_from'] = str_replace('/','-',$search_data['date_from']);
            if (!strtotime($search_data['date_from'])) $search_data['date_from'] = date('Y-m-d', strtotime('-1 month'));
            // year only = year-01-01
            elseif (is_numeric($search_data['date_from'])) $search_data['date_from'] = date('Y-m-d', strtotime($search_data['date_from'].'-01-01'));
            else $search_data['date_from'] = date('Y-m-d', strtotime($search_data['date_from']));

            $return['date_from'] = $search_data['date_from'];
        }

        // date_to
        if (isset($search_data['date_to'])) {
            $search_data['date_to'] = str_replace('/','-',$search_data['date_to']);
            if (!strtotime($search_data['date_to'])) $search_data['date_to'] = date('Y-m-d', strtotime('+1 day'));
            // year only = year-01-01
            elseif (is_numeric($search_data['date_to'])) $search_data['date_to'] = date('Y-m-d', strtotime($search_data['date_to'].'-01-01'));
            else $search_data['date_to'] = date('Y-m-d', strtotime($search_data['date_to']));

            $return['date_to'] = $search_data['date_to'];
        }

        if (!empty($search_data['category']) && is_array($search_data['category']))       $return['category'] = $search_data['category'];
        if (!empty($search_data['cc_category'])) $return['cc_category'] = is_array($search_data['cc_category']) ? $search_data['cc_category'] : array($search_data['cc_category']);

        return $return;
    }

    public function sanitizeRowData($row = null)
    {
        if (!$row) $row = array();

        // date - defaults to today
        if (!isset($row['date'])) $row['date'] = date('Y-m-d');
        else {
            $row['date'] = str_replace('/','-',$row['date']);
            if (!strtotime($row['date'])) $row['date'] = date('Y-m-d');
            // year only = year-01-01
            elseif (is_numeric($row['date'])) $row['date'] = date('Y-m-d', strtotime($row['date'].'-01-01'));
            else $row['date'] = date('Y-m-d', strtotime($row['date']));
        }

        if (!isset($row['comments'])) $row['comments'] = '';

        return $row;
    }

    /**
     * Parse rows to be Mustache compatible
     * @param array $rows
     * @return array
     */
    public function rowsToMustache($rows)
    {
        foreach ($rows as &$row) {
            //Mustache compatible
            $row['date'] = date('d/m/Y',strtotime($row['date']));
            $row['user_img'] = file_exists('_pub/img/user/'.$row['user_id'].'.png');
            $row = sanitizeToJson($row);
        }
        return $rows;
    }

    public function rowToMustache($row)
    {
        $rows = $this->rowsToMustache(array($row));
        return $rows[0];
    }
}