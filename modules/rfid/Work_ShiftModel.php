<?php
namespace Jan_Rfid;

class Work_ShiftModel
{
    protected $_table_name = 'user_work_shift';
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function search($search_data, $order_by = null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);

        $sql = 'SELECT u_w_s.*, ' .
                ' CONCAT(u.last_name, ", ", u.name) AS user_name, ' .
                ' u.cat_names AS user_cat_names ' .
            ' FROM user_work_shift AS u_w_s ' .
            ' LEFT JOIN user AS u ON u.id = u_w_s.user_id ' .
            ' WHERE ' . $where['sql'] .
            ($order_by ? ' ORDER BY ' . $order_by : '') .
            ($limit ? ' LIMIT ' . $limit : '');

        $stmt = $this->db->prepare($sql);
        $stmt->execute($where['values']);
        
        return $stmt->fetchAll();
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Delivery's
        $sql = 'SELECT COUNT(DISTINCT u_w_s.id) ' .
            ' FROM user_work_shift AS u_w_s ' .
            ' LEFT JOIN user AS u ON u.id = u_w_s.user_id ' .
            ' WHERE ' . $where['sql'];

        $stmt = $this->db->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchColumn();
    }


    /**
     * Return work_shifts started late on specified date
     * @param $date
     * @return array
     */
    public function getLateInOn($date)
    {
        if (is_int($date)) $date = date('Y-m-d', $date);
        elseif (strpos($date,'/')) $date = date('Y-m-d', strtotime(str_replace('/', '-', $date)));
        return $this->search( array('expected_start' => $date.'~'.date('Y-m-d',strtotime($date.' +1 day')), 'started_early' => 0), 'user_name ASC' );
    }

    /**
     * Return work_shifts ended early on specified date
     * @param $date
     * @return array
     */
    public function getEarlyOutOn($date)
    {
        if (is_int($date)) $date = date('Y-m-d', $date);
        elseif (strpos($date,'/')) $date = date('Y-m-d', strtotime(str_replace('/', '-', $date)));
        return $this->search( array('expected_end' => $date.'~'.date('Y-m-d',strtotime($date.' +1 day')), 'ended_early' => 1 ), 'user_name ASC' );
    }

    /**
     * @param null $search_data
     * @return array : [where]=Where clause;[values]=stmt_values
     */
    public function buildSearchWhere($search_data = null)
    {
        $search_data = $this->sanitizeSearchData($search_data);
        $stmt_values = array();
        $where = ' 1=1 ';

        if (isset($search_data['id'])) {
            // allows to search among multiple ids
            if (!is_array($search_data['id'])) $search_data['id'] = explode(",", str_replace(' ', '', $search_data['id']));

            $where .= ' AND u_w_s.id IN ('.implode(',', array_fill(0, count($search_data['id']), '?')).')';
            $stmt_values = array_merge($stmt_values, $search_data['id']);
        }

        if (isset($search_data['user_id'])) {
            $where .= ' AND u.id = ? ';
            $stmt_values[] = $search_data['user_id'];
        }
        elseif (isset($search_data['fullname'])) {
            $where .= ' AND CONCAT(u.name, " ",u.last_name) LIKE ?';
            $stmt_values[] = '%'.$search_data['fullname'].'%';
        }

        if (isset($search_data['expected_start'])) {
            $where .= ' AND DATE(u_w_s.expected_start) = ? ';
            $stmt_values[] = $search_data['expected_start'];
        }

        if (isset($search_data['expected_start_from']) && isset($search_data['expected_start_to'])) {
            $where .= ' AND u_w_s.expected_start BETWEEN ? AND ? ';
            $stmt_values[] = $search_data['expected_start_from'];
            $stmt_values[] = $search_data['expected_start_to'];
        }
        elseif (isset($search_data['expected_start_from'])) {
            $where .= 'AND u_w_s.expected_start > ? ';
            $stmt_values[] = $search_data['expected_start_from'];
        }
        elseif (isset($search_data['expected_start_to'])) {
            $where .= 'AND u_w_s.expected_start < ? ';
            $stmt_values[] = $search_data['expected_start_to'];
        }

        if (isset($search_data['ended_on'])) {
            if ($search_data['ended_on'] == null) {
                $where .= 'AND u_w_s.ended_on IS NULL ';
            }
        }

        if (isset($search_data['expected_end'])) {
            $where .= ' AND DATE(u_w_s.expected_end) = ? ';
            $stmt_values[] = $search_data['expected_end'];
        }

        if (isset($search_data['expected_end_from']) && isset($search_data['expected_end_to'])) {
            $where .= ' AND u_w_s.expected_end BETWEEN ? AND ? ';
            $stmt_values[] = $search_data['expected_end_from'];
            $stmt_values[] = $search_data['expected_end_to'];
        }
        elseif (isset($search_data['expected_end_from'])) {
            $where .= 'AND u_w_s.expected_end > ? ';
            $stmt_values[] = $search_data['expected_end_from'];
        }
        elseif (isset($search_data['expected_end_to'])) {
            $where .= 'AND u_w_s.expected_end < ? ';
            $stmt_values[] = $search_data['expected_end_to'];
        }

        if (isset($search_data['is_early'])) {
            if ($search_data['is_early']) $comp = '<='; else $comp = '>';
            $where .= ' AND ( u_w_s.started_on '.$comp.' u_w_s.expected_start OR u_w_s.ended_on '.$comp.' u_w_s.expected_end )';
        }

        if (isset($search_data['started_early'])) {
            if ($search_data['started_early']) $comp = '<='; else $comp = '>';
            $where .= ' AND ( u_w_s.started_on '.$comp.' u_w_s.expected_start )';
        }

        if (isset($search_data['ended_early'])) {
            if ($search_data['ended_early']) $comp = '<='; else $comp = '>';
            $where .= ' AND ( u_w_s.ended_on '.$comp.' u_w_s.expected_end )';
        }

        if (isset($search_data['category'])) {
            $where .= ' AND ( FIND_IN_SET(?, u.cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cat_ids) ', count($search_data['category'])-1) . ' ) ';
            $stmt_values = array_merge($stmt_values,$search_data['category']);
        }

        return array('sql' => $where, 'values' => $stmt_values);
    }

    public function add($row)
    {
        $row = $this->sanitizeRowData($row);
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('INSERT INTO `user_work_shift` (`user_id`, `expected_start`, `started_on`, `expected_end`, `ended_on`, `time_worked`, `comments`) VALUES (?,?,?,?,?,?,?)');
            $stmt_values = array($row['user_id'], $row['expected_start'], $row['started_on'], $row['expected_end'], $row['ended_on'], $row['time_worked'], $row['comments']);
            $stmt->execute($stmt_values);

            $id = $this->db->lastInsertId();
            $this->db->commit();

            return $id;
        }
        catch (\PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    }

    public function mod($row)
    {
        $row = $this->sanitizeRowData($row);
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE `user_work_shift` SET `user_id` = ?, `expected_start` = ?, `started_on` = ?, `expected_end` = ?, `ended_on` = ?, `time_worked` = ?, `comments` = ? WHERE `id` = ? LIMIT 1');
            $stmt_values = array($row['user_id'], $row['expected_start'], $row['started_on'], $row['expected_end'], $row['ended_on'], $row['time_worked'], $row['comments'], $row['id']);
            $stmt->execute($stmt_values);

            $this->db->commit();

            return true;
        }
        catch (\PDOException $e) {
            echo $e->getMessage();
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

        if (isset($search_data['expected_start']) && $search_data['expected_start']) {
            // from DataTables filter
            if ($search_data['expected_start'] == '~') unset($search_data['expected_start']);
            elseif (strpos($search_data['expected_start'],'~') !== false) {
                $tmp = explode('~', $search_data['expected_start']);
                if ($tmp[0]) $search_data['expected_start_from'] = $tmp[0];
                if (isset($tmp[1]) && $tmp[1]) $search_data['expected_start_to'] = $tmp[1];
            }
            else { // direct search
                $search_data['expected_start'] = str_replace('/','-',$search_data['expected_start']);
                if (!strtotime($search_data['expected_start'])) $search_data['expected_start'] = date('Y-m-d');
                // year only = year-01-01
                elseif (is_numeric($search_data['expected_start'])) $search_data['expected_start'] = date('Y-m-d', strtotime($search_data['expected_start'].'-01-01'));
                else $search_data['expected_start'] = date('Y-m-d', strtotime($search_data['expected_start']));

                $return['expected_start'] = $search_data['expected_start'];
            }
        }

        // date_from
        if (isset($search_data['expected_start_from'])) {
            $search_data['expected_start_from'] = str_replace('/','-',$search_data['expected_start_from']);
            if (!strtotime($search_data['expected_start_from'])) $search_data['expected_start_from'] = date('Y-m-d', strtotime('-1 month'));
            // year only = year-01-01
            elseif (is_numeric($search_data['expected_start_from'])) $search_data['expected_start_from'] = date('Y-m-d', strtotime($search_data['expected_start_from'].'-01-01'));
            else $search_data['expected_start_from'] = date('Y-m-d', strtotime($search_data['expected_start_from']));

            $return['expected_start_from'] = $search_data['expected_start_from'];
        }

        // date_to
        if (isset($search_data['expected_start_to'])) {
            $search_data['expected_start_to'] = str_replace('/','-',$search_data['expected_start_to']);
            if (!strtotime($search_data['expected_start_to'])) $search_data['expected_start_to'] = date('Y-m-d', strtotime('+1 day'));
            // year only = year-01-01
            elseif (is_numeric($search_data['expected_start_to'])) $search_data['expected_start_to'] = date('Y-m-d', strtotime($search_data['expected_start_to'].'-01-01'));
            else $search_data['expected_start_to'] = date('Y-m-d', strtotime($search_data['expected_start_to']));

            $return['expected_start_to'] = $search_data['expected_start_to'];
        }

        if (isset($search_data['expected_end']) && $search_data['expected_end']) {
            // from DataTables filter
            if ($search_data['expected_end'] == '~') unset($search_data['expected_start']);
            elseif (strpos($search_data['expected_end'],'~') !== false) {
                $tmp = explode('~', $search_data['expected_end']);
                if ($tmp[0]) $search_data['expected_end_from'] = $tmp[0];
                if (isset($tmp[1]) && $tmp[1]) $search_data['expected_end_to'] = $tmp[1];
            }
            else { // direct search
                $search_data['expected_end'] = str_replace('/','-',$search_data['expected_end']);
                if (!strtotime($search_data['expected_end'])) $search_data['expected_end'] = date('Y-m-d');
                // year only = year-01-01
                elseif (is_numeric($search_data['expected_end'])) $search_data['expected_end'] = date('Y-m-d', strtotime($search_data['expected_end'].'-01-01'));
                else $search_data['expected_end'] = date('Y-m-d', strtotime($search_data['expected_end']));

                $return['expected_end'] = $search_data['expected_end'];
            }
        }

        // date_from
        if (isset($search_data['expected_end_from'])) {
            $search_data['expected_end_from'] = str_replace('/','-',$search_data['expected_end_from']);
            if (!strtotime($search_data['expected_end_from'])) $search_data['expected_end_from'] = date('Y-m-d', strtotime('-1 month'));
            // year only = year-01-01
            elseif (is_numeric($search_data['expected_end_from'])) $search_data['expected_end_from'] = date('Y-m-d', strtotime($search_data['expected_end_from'].'-01-01'));
            else $search_data['expected_end_from'] = date('Y-m-d', strtotime($search_data['expected_end_from']));

            $return['expected_end_from'] = $search_data['expected_end_from'];
        }

        // date_to
        if (isset($search_data['expected_end_to'])) {
            $search_data['expected_end_to'] = str_replace('/','-',$search_data['expected_end_to']);
            if (!strtotime($search_data['expected_end_to'])) $search_data['expected_end_to'] = date('Y-m-d', strtotime('+1 day'));
            // year only = year-01-01
            elseif (is_numeric($search_data['expected_end_to'])) $search_data['expected_end_to'] = date('Y-m-d', strtotime($search_data['expected_end_to'].'-01-01'));
            else $search_data['expected_end_to'] = date('Y-m-d', strtotime($search_data['expected_end_to']));

            $return['expected_end_to'] = $search_data['expected_end_to'];
        }

        if (isset($search_data['is_early']) && is_numeric($search_data['is_early']))    $return['is_early'] = $search_data['is_early'];
        if (isset($search_data['started_early']) && is_numeric($search_data['started_early']))    $return['started_early'] = $search_data['started_early'];
        if (isset($search_data['ended_early']) && is_numeric($search_data['ended_early']))    $return['ended_early'] = $search_data['ended_early'];
        if (isset($search_data['category']) && ctype_xdigit($search_data['category']))  $return['category'] = $search_data['category'];
        return $return;
    }

    public function sanitizeRowData($row = null)
    {
        if (!$row) $row = array();

        $row['expected_start']  = str_replace('/','-',$row['expected_start']);
        $row['expected_end']    = str_replace('/','-',$row['expected_end']);
        if (isset($row['started_on']))   $row['started_on'] = str_replace('/','-',$row['started_on']);
        else $row['started_on'] = $row['expected_start'];

        if (!isset($row['ended_on']))    $row['ended_on'] = null;
        if (!isset($row['time_worked'])) $row['time_worked'] = 0;
        if (!isset($row['comments']))    $row['comments'] = null;

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
            $row['expected_start'] = date('d/m/Y H:i',strtotime($row['expected_start'])).' hs';
            $row['started_on']     = date('d/m/Y H:i',strtotime($row['started_on'])).' hs';
            $row['expected_end']   = date('d/m/Y H:i',strtotime($row['expected_end'])).' hs';
            $row['ended_on']       = date('d/m/Y H:i',strtotime($row['ended_on'])).' hs';
            $row['image_src']      = file_exists('_pub/img/user/'.$row['user_id'].'.png');
            $row                   = array_map('mustacheCompatible', $row);
        }
        return $rows;
    }

    public function rowToMustache($row)
    {
        $rows = $this->rowsToMustache(array($row));
        return $rows[0];
    }
}