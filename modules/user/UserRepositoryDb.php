<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;
use RepositoryDb,
    Db
;

class UserRepositoryDb extends RepositoryDb
{
    public function __construct(Db $db)
    {
        parent::__construct($db,'user');
    }

    public function save(array $user)
    {
        // Set empty values to NULL
        foreach ($user as $key => $value) if ($value !== 0 && !$value) $user[$key] = null;

        if (!empty($user['pwd'])) $user['pwd'] = sha1($user['pwd']);
        else unset($user['pwd']);

        // UPDATE
        if (!empty($user['id'])) return $this->db->mod('user', $user);
        else {
            // INSERT
            $user         = array_filter($user);
            $user['id']   = uniqid();
            $user['type'] = 'employee';
            return $this->db->add('user', $user);
        }
    }

    /**
     * Set users In or Out of School by updating it's status (i.e. not inserting a movement)
     *
     * @param bool $set '1|0' school
     * @param array $ids
     * @return bool
     */
    public function setInOut($set, array $ids = null)
    {
        if (!$ids) return true;
        return $this->db->modByIds('user',array('in_school' => $set,'last_movement'=>date('Y-m-d H:i:s')),$ids);
    }

    /**
     * @param string|array $user_ids
     * @param array $schedule : [monday_in,monday_out,...]
     * @return $this
     */
    public function setSchedule($user_ids, $schedule)
    {
        foreach ($schedule as $key => $value) if (!trim($value)) $schedule[$key] = null;

        // 1 user
        if (!is_array($user_ids)) {
            $schedule['id'] = $user_ids;
            $this->db->replaceInto('user_schedule')->set($schedule)->prepareExec();
            return $this;
        }

        // multiple users
        // update existing schedules
        $user_schedule_ids = array();
        foreach ($this->db->select('id')->from('user_schedule')->where('id', $user_ids)->prepareFetchAll() as $row) $user_schedule_ids[] = $row['id'];
        if ($user_schedule_ids) $this->db->update('user_schedule')->set($schedule)->where('id', $user_schedule_ids)->prepareExec();

        // insert new schedules
        $new_user_schedule_ids = array_diff($user_ids, $user_schedule_ids);
        if ($new_user_schedule_ids) {
            $sql = 'INSERT INTO user_schedule (id, '.implode(',',array_keys($schedule)).' ) VALUES ';
            $values = array();
            foreach ($new_user_schedule_ids as $user_id) {
                $sql .= '(?,'.implode(',', array_fill(0, count($schedule), '?')).'),';
                $values = array_merge($values, array($user_id), array_values($schedule));
            }
            $sql = trim($sql, ',');

            $this->db->prepareExec($sql, $values);
        }
        return $this;
    }


    /**
     * @param string $user_id
     * @param string $table         : category|cc_category
     * @param string|array $cat_ids
     * @param null|string|array $cat_ids_old
     * @return $this
     */
    public function setUserCats($user_id, $table, $cat_ids = null, $cat_ids_old = null)
    {
        if (!$cat_ids) return $this;
        if (($table != 'category') && ($table != 'cc_category')) return $this;

        if (!is_array($cat_ids)) $cat_ids = explode(',', $cat_ids);
        if (!$cat_ids_old) $cat_ids_old = array();
        elseif (!is_array($cat_ids_old)) $cat_ids_old = explode(',',$cat_ids_old);

        // DELETE old categories
        if ($cats_to_delete = array_diff_assoc($cat_ids_old,$cat_ids)) $this->db->deleteFrom('user_'.$table)->where(array('user_id' => $user_id, $table.'_id' => $cats_to_delete))->prepareExec();

        // INSERT new categories
        foreach (array_diff_assoc($cat_ids,$cat_ids_old) as $cat_id) {
            if (!$cat_id) continue;
            $sql = $this->db->insertInto('user_'.$table)->set(array('user_id' => $user_id,$table.'_id' => $cat_id));
            if ($table == 'cc_category') $sql->set(array('id' => $user_id)); // id is not autoincrement!
            $sql->prepareExec();
        }
        return $this;
    }

    public function setUserCategory($user_id, $cat_ids = null, $cat_ids_old = null)
    {
        return $this->setUserCats($user_id, 'category', $cat_ids, $cat_ids_old);
    }

    public function setUserCcCategory($user_id, $cc_cat_ids = null, $cc_cat_ids_old = null)
    {
        return $this->setUserCats($user_id, 'cc_category', $cc_cat_ids, $cc_cat_ids_old);
    }
}