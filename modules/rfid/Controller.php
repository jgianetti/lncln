<?php
namespace Jan_Rfid;
use Jan_Category\CategoryModel,
    Jan_User\UserRepositoryDb,
    Jan_User\UserAclRepositoryDb,
    Jan_User\UserSearchDb,
    Jan_User\UserNwdSearchDb,
    Jan_Acl\AclHelper
;


class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $userRepository     = new UserRepositoryDb($App->db);
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $userSearch         = new UserSearchDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $acl                = new AclHelper($App->db);

        $session_user       = $App->session->get('user');

        // ACL FILTERS
        $acl_filters = $acl->get_filters($App->session->get('user')['acl'], 'rfid.search');
        if (isset($acl_filters['category_id'])) {
            $categories = array_merge($categoryModel->getChildrenIds($acl_filters['category_id']), [$acl_filters['category_id']]);
        }
        else $categories = $categoryModel->get();

        $entrances = array('Ferreyra', 'Parana', 'D. Pileta', 'D. Asc.');

        $return = array();
        
        // jQuery datatable headers
        if (!$App->request->fetch('theaders')) $theaders = ['image', 'fullname', 'date', 'time', 'is_early', 'is_entering', 'entrance', 'category', 'deleted', 'comments'];

        else $theaders = explode(',', $App->request->fetch('theaders'));
        
        $filters = array();
        
        // jQuery datatable filters
        if (is_null($App->request->fetch('sEcho')))
        {
            $return['categories'] = array();
            foreach ($categories as $row) $return['categories'][] = array('value' => $row['id'], 'label' => utf8_encode($row['name']));
            $return['entrances'] = $entrances;
            $filters['date_from'] = date('Y-m-d', strtotime('-1 day'));
        }

        // filters
        for ($i = 0; $i < count($theaders); $i++) {
            $filters[$theaders[$i]] = $App->request->fetch('sSearch_' . $i);
        }

        // User movements
        if ($App->request->fetch('user_id')) {
            $filters['user_id'] = $App->request->fetch('user_id');
        }

        if (!is_null($App->request->fetch('is_entering'))) {
            $filters['is_entering'] = $App->request->fetch('is_entering');
        }

        if (!is_null($App->request->fetch('deleted'))) {
            $filters['deleted'] = $App->request->fetch('deleted');
        }
        
        if (is_null($order_by = $App->request->fetch('iSortCol_0'))) {
            $order_by = 'u_m.date DESC';
        }
        else {
            $order_by = $theaders[$order_by];
            if (in_array($order_by, array('date', 'comments'))) $order_by = 'u_m.' . $order_by;
            elseif ($order_by == 'fullname') $order_by = 'user_name';
            elseif ($order_by == 'category') $order_by = 'user_cat_names';
            $order_by .= ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        }
        
        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', ' . $App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', ' . $App->request->fetch('iDisplayLength');
        
        
        if (!empty($filters['category'])) $filters['category'] = array_merge(array($filters['category']), $categoryModel->getChildrenIds($filters['category']));

        // ACL FILTERS
        //$filters = $userAclRepository->getFilters($filters, $categories);
        //if ($userAclRepository->selfOnly($session_user['acl'], 'rfid', 'search')) $filters['user_id'] = $session_user['id'];

        $rfidModel = new RfidModel($App->db);
        $rfids = $rfidModel->rowsToMustache($rfidModel->search($filters, $order_by, $limit_by));
        
        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $rfidModel->searchCount();
        $return['iTotalDisplayRecords'] = $rfidModel->searchCount($filters);
        $return['aaData'] = &$rfids;
        
        return $return;
    }
    
    public function add(\App $App)
    {
        $userRepository     = new UserRepositoryDb($App->db);
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $userSearch         = new UserSearchDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $acl                = new AclHelper($App->db);

        $session_user       = $App->session->get('user');

        if ($App->request->fetch('clear_entrance')) $App->session->destroy('entrance');

        if (!$App->request->fetch('rfid')) {
            if ($App->session->get('entrance')) return ['entrance' => $App->session->get('entrance')];
            return ['entrances' => ['Ferreyra', 'Parana', 'D. Pileta', 'D. Asc.']];
        }

        $App->session->set('entrance', $_POST['entrance'])->save();

        if (!($users = $userSearch->get('u[id,name,last_name,in_school,last_movement,cat_ids,cat_names],schedule',array("rfid" => $_POST['rfid'], "deleted" => '0')))) return array('textStatus' => 'error', 'errors' => array('id_not_found' => '#'.$_POST['rfid']));
        $user = $users[0];
    
        $rfidModel = new RfidModel($App->db);
        $work_shiftModel = new Work_ShiftModel($App->db);
    
        // CHECKing RFID Tag
        if (@$_POST['checking'] == 'true') {
            $user_movement['_checking']          = true;
            $user_movement['user_id']            = $user['id'];
            $user_movement['user_name']          = $user['last_name'].', '.$user['name'];
            $user_movement['user_cat_names']     = $user['cat_names'];
            return array('textStatus' => 'ok', 'user_movement' => $rfidModel->rowToMustache($user_movement));
        }
    
    
        /**************
         * WORK SHIFT *
         **************
         * Only on working days
         * If uesr is Entering: Update current workshift or create one
         * If user is leaving: Update current workshift
         */
        $userNwdSearch = new UserNwdSearchDb($App->db);
        if ($userNwdSearch->isWorkingDay($user['id'])) {
            $shift_data = array();
    
            // Entering
            if (!$user['in_school']) {
                $shift_time = $rfidModel->work_shift_time($user);
    
                if ($shift_time['expected_start'] && $shift_time['expected_end']) {
                    $shift_data['expected_start'] = date('Y-m-d H:i:s', $shift_time['expected_start']);
                    $shift_data['expected_end']   = date('Y-m-d H:i:s', $shift_time['expected_end']);
    
                    // Check if user has created this shift
                    if ($current_shift = $work_shiftModel->search(
                        array(
                            'user_id'       => $user['id'],
                            'expected_start'=> $shift_data['expected_start'],
                            'expected_end'  => $shift_data['expected_end']
                        ),
                        'id DESC',
                        '1'
                    )) {
                        $shift_data['id'] = $current_shift[0]['id'];
    
                        // clear 'ended_on'
                        $current_shift[0]['ended_on'] = null;
                        $work_shiftModel->mod($current_shift[0]);
                    }
                    // Create shift
                    else $shift_data['id'] = $work_shiftModel->add(
                        array(
                            'user_id' => $user['id'],
                            'expected_start' => $shift_data['expected_start'],
                            'started_on' => date('Y-m-d H:i:s'),
                            'expected_end' => $shift_data['expected_end']
                        )
                    );
                }
            }
            // Leaving
            else {
                // Verify if last check-in was inside a shift
                // Update shift: [ended_on, total_time]
                if (($rfid_last_in = $rfidModel->search(
                        array(
                            'user_id'       => $user['id'],
                            'is_entering'   => '1',
                            'deleted'       => '0'
                        ),
                        'u_m.date DESC',
                        '1'
                    )) && $rfid_last_in[0]['user_work_shift_id']
                ) {
    
                    $rfid_last_in = $rfid_last_in[0];
                    $shift_data['id']             = $rfid_last_in['user_work_shift_id'];
                    $shift_data['expected_start'] = $rfid_last_in['shift_expected_start'];
                    $shift_data['expected_end']   = $rfid_last_in['shift_expected_end'];
    
                    $work_shiftModel->mod(
                        array(   // Avoids - $work_shiftModel->getById();
                            'id'             => $shift_data['id'],
                            'user_id'        => $user['id'],
                            'expected_start' => $rfid_last_in['shift_expected_start'],
                            'started_on'     => $rfid_last_in['shift_started_on'],
                            'expected_end'   => $rfid_last_in['shift_expected_end'],
                            'ended_on'       => date('Y-m-d H:i:s'),
                            'time_worked'    => date('H:i:s', strtotime($rfid_last_in['shift_time_worked'] . '+' . (time() - strtotime($rfid_last_in['date'])) . ' sec')),
                            'comments'       => $rfid_last_in['comments']
                        )
                    );
                }
            }
        }
    
    
        $user['last_movement'] = date("Y-m-d H:i:s");
        $user['in_school']     = $user['in_school'] ? '0' : '1';
        $userRepository->save(array_intersect_key($user, array_flip(array('id', 'last_movement', 'in_school'))));
    
        $user_movement['user_work_shift_id']   = isset($shift_data['id']) ? $shift_data['id'] : null;
        $user_movement['shift_expected_start'] = isset($shift_data['id']) ? $shift_data['expected_start'] : null;
        $user_movement['shift_expected_end']   = isset($shift_data['id']) ? $shift_data['expected_end'] : null;
        $user_movement['user_id']              = $user['id'];
        $user_movement['date']                 = $user['last_movement'];
        $user_movement['is_entering']          = $user['in_school'];
        $user_movement['entrance']             = $_POST['entrance'];
        $user_movement['deleted']              = "0";
        $user_movement['comments']             = '';
        $user_movement['id']                   = $rfidModel->add($user_movement);
        $user_movement['_checking']            = false;
        $user_movement['user_name']            = $user['last_name'].', '.$user['name'];
        $user_movement['user_cat_names']       = $user['cat_names'];
    
        return array('textStatus' => 'ok', 'user_movement' => $rfidModel->rowToMustache($user_movement));
    }

    public function mod(\App $App)
    {
        $userRepository     = new UserRepositoryDb($App->db);
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $userSearch         = new UserSearchDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $acl                = new AclHelper($App->db);

        $session_user       = $App->session->get('user');
        // ACL FILTERS

        $acl_filters = $acl->get_filters($App->session->get('user')['acl'], 'rfid.search');
        if (isset($acl_filters['category_id'])) {
            $categories = array_merge($categoryModel->getChildrenIds($acl_filters['category_id']), [$acl_filters['category_id']]);
        }
        else $categories = $categoryModel->get();

        $entrances = array('Ferreyra', 'Parana', 'D. Pileta', 'D. Asc.');

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $rfidModel = new RfidModel($App->db);
        if (!($user_movement = $rfidModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$_POST) return array('user_movement' => $rfidModel->rowToMustache($user_movement));

        // POST comes as JSON UTF-8 encoded
        $_POST = array_map('utf8_decode', $_POST);

        // array_merge() maintains $user[x] not present in $_POST
        if (!$rfidModel->mod(array_merge($user_movement, $_POST))) return array('textStatus' => 'error', 'errors' => array('db_mod'));

        /*
        // Update user_work_shift started_on time
        if (($user_movement['date'] == $user_movement['shift_started_on']) && $_POST['date'] && ($_POST['date'] != $user_movement['date'])) {
            $work_shiftModel = new Work_ShiftModel($App->db);
            $work_shift = $work_shiftModel->modById($user_movement['user_work_shift_id'],array('started_on' => $_POST['date']));
        }

        // Update user_work_shift ended_on time
        if (($user_movement['date'] == $user_movement['shift_ended_on']) && $_POST['date'] && ($_POST['date'] != $user_movement['date'])) {
            $work_shiftModel = new Work_ShiftModel($App->db);
            $work_shift = $work_shiftModel->modById($user_movement['user_work_shift_id'],array('ended_on' => $_POST['date']));
        }
        */

        return array('textStatus' => 'ok', 'user_movement' => $user_movement);
    }

    public function del(\App $App)
    {
        $userRepository     = new UserRepositoryDb($App->db);
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $userSearch         = new UserSearchDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $acl                = new AclHelper($App->db);

        $session_user       = $App->session->get('user');

        $entrances = array('Ferreyra', 'Parana', 'D. Pileta', 'D. Asc.');

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $rfidModel = new RfidModel($App->db);
        if (!($user_movement = $rfidModel->getByid($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) return array('user_movement' => $user_movement);

        $user_movement['deleted']   = "1";
        $user_movement['comments']  = @$_POST['comments'];

        if (!$rfidModel->mod($user_movement)) return array('textStatus' => 'error', 'errors' => array('db_mod'));

        // update user in-school status to reflect last movement
        if ($last_movement = $rfidModel->search(
            array(
                'user_id' => $user_movement['user_id'],
                'deleted' => 0
            )
            ,'date DESC', 1
        )) {
            // update user in-school status
            $user = $userSearch->getById('id',$user_movement['user_id']);

            $user['in_school']     = $last_movement[0]['is_entering'];
            $user['last_movement'] = $last_movement[0]['date'];

            if (!$userRepository->save($user)) return array('textStatus' => 'error', 'errors' => array('db_mod'));
        }

        return array('textStatus' => 'ok', 'user_movement' => $user_movement);
    }
}

