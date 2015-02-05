<?php
namespace Jan_Rfid;

class RfidModel
{
    protected $_table_name = 'user_movement';
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function search($search_data, $order_by = null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET Users Movements
        $sql = 'SELECT u_m.*, ' .
                ' u.name AS user_name, u.last_name AS user_last_name, u.cat_names AS user_cat_names, ' .
                ' u_w_s.expected_start AS shift_expected_start, u_w_s.started_on AS shift_started_on, ' .
                ' u_w_s.expected_end AS shift_expected_end, u_w_s.ended_on AS shift_ended_on, ' .
                ' u_w_s.time_worked AS shift_time_worked, u_w_s.comments AS shift_comments ' .
            ' FROM user_movement AS u_m ' .
            ' INNER JOIN user AS u ON u.id = u_m.user_id ' .
            ' LEFT JOIN user_work_shift AS u_w_s ON u_w_s.id = u_m.user_work_shift_id ' .
            ' WHERE ' . $where['sql'] .
            ($order_by ? ' ORDER BY ' . $order_by : '') .
            ($limit ? ' LIMIT ' . $limit : '')
        ;

        //echo $sql;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchAll();
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Delivery's
        $sql = 'SELECT COUNT(u_m.id) ' .
                ' FROM user_movement AS u_m ' .
                ' LEFT JOIN user AS u ON (u.id = u_m.user_id AND u.deleted = 0)' .
                ' LEFT JOIN user_work_shift AS u_w_s ON u_w_s.id = u_m.user_work_shift_id ' .
                ' WHERE ' . $where['sql']
        ;
        $stmt = $this->db->prepare($sql);
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
        $where = ' (u.deleted = 0 || u.deleted IS NULL) ';

        if (isset($search_data['id'])) {
            // allows to search among multiple ids
            if (!is_array($search_data['id'])) $search_data['id'] = explode(",", str_replace(' ', '', $search_data['id']));

            $where .= ' AND u_m.id IN ('.implode(',', array_fill(0, count($search_data['id']), '?')).')';
            $stmt_values = array_merge($stmt_values, $search_data['id']);
        }

        if (isset($search_data['shift_id'])) {
            if ($search_data['shift_id'] == 'not_null') $where .= ' AND u_m.user_work_shift IS NOT NULL ';
            else {
                $where .= ' AND u_m.user_work_shift = ? ';
                $stmt_values[] = $search_data['shift_id'];
            }
        }

        if (isset($search_data['user_id'])) {
            $where .= ' AND u_m.user_id = ? ';
            $stmt_values[] = $search_data['user_id'];
        }
        elseif (isset($search_data['fullname'])) {
            $where .= ' AND CONCAT(u.name, " ",u.last_name) LIKE ?';
            $stmt_values[] = '%'.$search_data['fullname'].'%';
        }

        if (isset($search_data['date'])) {
            $where .= ' AND DATE(u_m.date) = ? ';
            $stmt_values[] = $search_data['date'];
        }

        if (isset($search_data['date_from']) && isset($search_data['date_to'])) {
            $where .= ' AND u_m.date BETWEEN ? AND ? ';
            $stmt_values[] = $search_data['date_from'];
            $stmt_values[] = $search_data['date_to'];
        }
        elseif (isset($search_data['date_from'])) {
            $where .= 'AND u_m.date >= ? ';
            $stmt_values[] = $search_data['date_from'];
        }
        elseif (isset($search_data['date_to'])) {
            $where .= 'AND u_m.date <= ? ';
            $stmt_values[] = $search_data['date_to'];
        }

        if (isset($search_data['is_entering'])) {
            $where .= ' AND u_m.is_entering = ? ';
            $stmt_values[] = $search_data['is_entering'];
        }

        if (isset($search_data['is_early'])) {
            if ($search_data['is_early']) $comp = '<='; else $comp = '>';
            $where .= ' AND u_m.user_work_shift_id IS NOT NULL AND ( (u_w_s.started_on = u_m.date && u_w_s.started_on '.$comp.' u_w_s.expected_start) OR (u_w_s.ended_on = u_m.date && u_w_s.ended_on '.$comp.' u_w_s.expected_end) )';
        }

        if (isset($search_data['entrance'])) {
            $where .= ' AND u_m.entrance LIKE ? ';
            $stmt_values[] = $search_data['entrance'];
        }

        if (isset($search_data['category'])) {
            $where .= ' AND ( FIND_IN_SET(?, u.cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cat_ids) ', count($search_data['category'])-1) . ' ) ';
            $stmt_values = array_merge($stmt_values,$search_data['category']);
        }

        if (isset($search_data['deleted'])) {
            if ($search_data['deleted']) {
                $where .= ' AND u_m.deleted = ? ';
            }
            else {
                $where .= ' AND (u_m.deleted = ? OR u_m.deleted IS NULL)';
                $stmt_values[] = $search_data['deleted'];
            }
        }

        return array('sql' => $where, 'values' => $stmt_values);
    }

    /*
     * Calculate shift's expected start and expected end
     * Depending upon user_schedule
     * Using yesterday's, today's and tomorrow's time_in/time_out
     */
    function work_shift_time($user, $timestamp = null)
    {
        if (!$timestamp) $timestamp = time();
        $time_gap = 60*60*4;

        $time = strtotime(date('H:i:s', $timestamp));

        $today_name     = strtolower(date('l', $timestamp));
        $today_in       = strtotime($user[$today_name . '_in']);
        $today_out      = strtotime($user[$today_name . '_out']);

        $yesterday_name = strtolower(date('l', strtotime('-1 day', $timestamp)));
        $yesterday_in   = strtotime('yesterday ' . $user[$yesterday_name . '_in']);
        $yesterday_out  = strtotime('yesterday ' . $user[$yesterday_name . '_out']);

        $tomorrow_name  = strtolower(date('l', strtotime('+1 day', $timestamp)));
        $tomorrow_in    = strtotime('tomorrow ' . $user[$tomorrow_name . '_in']);
        $tomorrow_out   = strtotime('tomorrow ' . $user[$tomorrow_name . '_out']);

        //User last check-out
        $rfid_last_out_search_data = array(
            'shift_id'      => 'not_null',
            'user_id'       => $user['id'],
            'is_entering'   => '0',
            'deleted'       => '0'
        );
        $rfid_last_out = $this->search($rfid_last_out_search_data, 'u_m.date DESC', '1');
        if ($rfid_last_out) $rfid_last_out = $rfid_last_out[0];

        $work_shift_start = null;
        $work_shift_end = null;

        // post yesterday
        if ($yesterday_out && (($time - $yesterday_out) < $time_gap || ($rfid_last_out && ($time - $rfid_last_out['date']) < $time_gap))) {
            $work_shift_start = $yesterday_in;
            $work_shift_end = $yesterday_out;
        }

        // night shift
        if ($yesterday_in && (!$yesterday_out || ($yesterday_out < $yesterday_in))) {
            $work_shift_start = $yesterday_in;
            $work_shift_end = $today_out;
        }

        // pre today's
        if ($today_in && ($time < $today_in) && ($today_in - $time) < $time_gap) {
            $work_shift_start = $today_in;
            $work_shift_end = ($today_out && ($today_out > $today_in)) ? $today_out : $tomorrow_out;
        }

        // today's
        if ($today_in && ($today_in <= $time)) {
            $work_shift_start = $today_in;
            $work_shift_end = ($today_out && ($today_out > $today_in)) ? $today_out : $tomorrow_out;
        }

        // post today's
        if ($today_out && ($today_out < $time) && ((($time - $today_out) < $time_gap) || ($rfid_last_out && ($time - $rfid_last_out['date']) < $time_gap))) {
            $work_shift_start = ($today_in && ($today_in < $today_out)) ? $today_in : $yesterday_in;
            $work_shift_end = ($today_out && ($today_out > $today_in)) ? $today_out : $tomorrow_out;
        }

        // pre tomorrow's
        if ($tomorrow_in && ($tomorrow_in - $time) < $time_gap) {
            $work_shift_start = $tomorrow_in;
            $work_shift_end = $tomorrow_out;
        }

        return array('expected_start' => $work_shift_start, 'expected_end' => $work_shift_end);
    }

    public function add($row)
    {
        $row = $this->sanitizeRowData($row);
        $this->db->beginTransaction();

        try {
            $row['id'] = uniqid();
            $stmt = $this->db->prepare('INSERT INTO `user_movement` (`id`, `user_work_shift_id`, `user_id`, `date`, `is_entering`, `entrance`, `deleted`, `comments`) VALUES (?,?,?,?,?,?,?,?)');
            $stmt_values = array($row['id'], $row['user_work_shift_id'], $row['user_id'], $row['date'], $row['is_entering'], $row['entrance'], $row['deleted'], $row['comments']);
            $stmt->execute($stmt_values);

            $this->db->commit();

            return $row['id'];
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            return false;
        }
    }

    public function mod($row)
    {
        $row = $this->sanitizeRowData($row);
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare('UPDATE `user_movement` SET `user_work_shift_id` = ?, `user_id` = ?, `date` = ?, `is_entering` = ?, `entrance` =?, `deleted` = ?, `comments` = ? WHERE id = ? LIMIT 1');
            $stmt_values = array($row['user_work_shift_id'], $row['user_id'], $row['date'], $row['is_entering'], $row['entrance'], $row['deleted'], $row['comments'], $row['id']);
            $stmt->execute($stmt_values);

            $this->db->commit();

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

        if (isset($search_data['shift_id']) && is_numeric($search_data['shift_id']))    $return['shift_id'] = $search_data['shift_id'];

        if (isset($search_data['user_id']) && ctype_xdigit($search_data['user_id']))    $return['user_id'] = $search_data['user_id'];
        elseif (isset($search_data['fullname']) && $search_data['fullname'])            $return['fullname'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['fullname']);

        if (isset($search_data['is_early']) && is_numeric($search_data['is_early']))    $return['is_early'] = $search_data['is_early'];
        if (isset($search_data['category']) && is_array($search_data['category']))      $return['category'] = $search_data['category'];
        if (isset($search_data['is_entering']) && is_numeric($search_data['is_entering'])) $return['is_entering'] = $search_data['is_entering'];
        if (isset($search_data['entrance']) && ($search_data['entrance']))              $return['entrance'] = $search_data['entrance'];
        if (isset($search_data['deleted']) && is_numeric($search_data['deleted']))      $return['deleted'] = $search_data['deleted'];

        return $return;
    }

    public function sanitizeRowData($row = null)
    {
        if (!$row) $row = array();

        // opened_on - defaults to today
        if (!isset($row['date'])) $row['date'] = date('Y-m-d H:i:s');
        else {
            $row['date'] = str_replace('/','-',$row['date']);
            if (!strtotime($row['date'])) $row['date'] = date('Y-m-d H:i:s');
            // year only = year-01-01
            elseif (is_numeric($row['date'])) $row['date'] = date('Y-m-d H:i:s', strtotime($row['date'].'-01-01 '.date('H:i:s')));
            else $row['date'] = date('Y-m-d H:i:s', strtotime($row['date']));
        }

        if (!isset($row['is_entering'])) $row['is_entering'] = 0;
        if (!isset($row['entrance'])) $row['entrace'] = '';
        if (!isset($row['deleted'])) $row['deleted'] = 0;
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
            $row['date'] = ((isset($row['date']) && $row['date']) ? date('d/m/Y H:i:s',strtotime($row['date'])) : null);
            if (isset($row['shift_expected_start']) && $row['shift_expected_start']) $row['shift_expected_start'] = date('d/m/Y H:i:s',strtotime($row['shift_expected_start']));
            if (isset($row['shift_started_on']) && $row['shift_started_on'])         $row['shift_started_on'] = date('d/m/Y H:i:s',strtotime($row['shift_started_on']));
            if (isset($row['shift_expected_end']) && $row['shift_expected_end'])     $row['shift_expected_end'] = date('d/m/Y H:i:s',strtotime($row['shift_expected_end']));
            if (isset($row['shift_ended_on']) && $row['shift_ended_on'])             $row['shift_ended_on'] = date('d/m/Y H:i:s',strtotime($row['shift_ended_on']));

            $row['is_entering'] = ((isset($row['is_entering'])) ? intval($row['is_entering']) : null);
            $row['deleted']     = ((isset($row['deleted'])) ? intval($row['deleted']) : null);
            $row['user_img']    = file_exists(APP_ROOT . 'uploads/user/_pub/'.$row['user_id'].'.png');

            $row['comments']    = ((isset($row['comments']) && $row['comments']) ? nl2br($row['comments']) : null);
            $row                = sanitizeToJson($row);
        }
        return $rows;
    }

    public function rowToMustache($row)
    {
        $rows = $this->rowsToMustache(array($row));
        return $rows[0];
    }
}