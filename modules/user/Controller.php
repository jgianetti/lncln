<?php
namespace Jan_User;
use \Image,
    Jan_Category\CategorySearchDb,
    Jan_CC_Category\CC_CategorySearchDb,
    Jan_Acl\AclHelper,
    Jan_Acl\AclSearchDb;
;

class Controller {
    /*********
     * LOGIN *
     *********/
    public function login(\App $App)
    {
        if (!$App->request->post()) return true;

        $userSearch = new UserSearchDb($App->db);

        if (!($users = $userSearch->get('u.*',['user' => $_POST['user'],'pwd' => $_POST['pwd'], 'deleted' => 0], null, 1))) return ['textStatus' => 'error', 'errors' => ['login_not_found']];
        unset($users[0]['pwd']);

        $App->session->set('user', $users[0])->save();
        return ['textStatus' => 'ok', 'user' => $users[0]];
    }


    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $userSearch     = new UserSearchDb($App->db);
        $acl            = new AclHelper($App->db);
        $categorySearch = new CategorySearchDb($App->db);
        $cc_categorySearch = new CC_CategorySearchDb($App->db);

        $userAclRepository = new UserAclRepositoryDb($App->db);
        $categories = $userAclRepository->getCategoriesAllowance($App->session->get('user'), 'user', 'search', $categorySearch, 1);

        $search_params  = $App->request->get_search_params(['image', 'fullname', 'category', 'cc_category', 'email', 'in_school', 'comments', 'deleted']);

        // If filtered by category -> include category's children
        if (!empty($search_params['filters']['category'])) $search_params['filters']['category'] = array_merge([$search_params['filters']['category']], $categorySearch->getChildrenIds($search_params['filters']['category']));

        if (!isset($search_params['filters']['deleted'])) $search_params['filters']['deleted'] = 0;

        // ACL FILTERS
        // Categories filtered by user's custom filters + ACL filters
        $search_params['filters'] = $userAclRepository->getFilters($search_params['filters'], $categories);

        // SET ALL action filtered by current user
        if ($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'set_all')) {
            $filters['id'] = $App->session->get('user')['id'];
        }

        $users = $userSearch->get($search_params['select'], $search_params['filters'], $search_params['order_by'], $search_params['limit']);

        foreach ($users as &$user) {
            $user              = sanitizeToJson($user);
            $user['in_school'] = intval($user['in_school']);
            $user['deleted']   = intval($user['deleted']);
            $user['_image']    = file_exists(APP_ROOT . 'uploads/user/_pub/'.$user['id'].'.png');
        }

        if (!$search_params['jqdt']) return['users' => &$users];

        $return = [
            'sEcho'                => $App->request->fetch('sEcho'),
            'iTotalRecords'        => $userSearch->count(),
            'iTotalDisplayRecords' => $userSearch->count($search_params['filters']),
            'aaData'               => &$users,
        ];

        // first access - default filters
        if (!($App->request->fetch('sEcho'))) {
            //$categories = $categorySearch->get('full', isset($acl_filters['category_id']) ? ['id', array_merge($categorySearch->getChildrenIds($acl_filters['category_id']), [$acl_filters['category_id']])] : null);
            foreach ($categories as $row)  if ($row['_allow']) $return['categories'][] = array('value' => $row['id'], 'label' => utf8_encode($row['name']));
            foreach (($cc_categories = $cc_categorySearch->get('id,name')) as $row) $return['cc_categories'][] = array('value' => $row['id'], 'label' => utf8_encode($row['name']));
        }

        return $return;
    }

    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {
        // return Categories && CC_Categories
        if (!$App->request->post()) return $this->getCategories($App);

        $result = $this->processForm($App);
        if ($result['textStatus'] == 'error') return $result;

        return array('textStatus' => 'ok', 'id' => $result['id']);
    }

    /*******
     * MOD *
     *******/
    public function mod(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userSearch = new UserSearchDb($App->db);

        /*
        $filters = array('id' => $id);
        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'mod') && $id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u[*]',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        */

        if (!($user = $userSearch->getById('full',$id))) return ['textStatus' => 'error', 'errors' => 'not_found'];

        if (!$App->request->post()) {
            // return Categories, CC_Categories, User
            $return = $this->getCategories($App);

            // pwd_old input
            if (!empty($user['pwd'])) { $user['_has_pwd'] = true; unset($user['pwd']); }

            $user           = sanitizeToJson($user);
            $user['_image'] = file_exists(APP_ROOT . 'uploads/user/_pub/'.$user['id'].'.png');

            return array_merge($return, [ 'user' => $user ]);
        }

        $result = $this->processForm($App, $user);
        if ($result['textStatus'] == 'error') return $result;

        return array('textStatus' => 'ok', 'id' => $result['id']);
    }



    /********
     * VIEW *
     ********/
    public function view(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userSearch = new UserSearchDb($App->db);
        $aclSearch  = new AclSearchDb($App->db);
        $acl        = new AclHelper($App->db);

        $userAclRepository = new UserAclRepositoryDb($App->db);
        $categorySearch    = new CategorySearchDb($App->db);
        $categories        = $userAclRepository->getCategoriesAllowance($App->session->get('user'), 'user', 'search', $categorySearch, 1);

        $filters = array('id' => $id);
        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'mod') && $id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u[*]',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));

        if (!($user = $userSearch->getById('full',$id))) return ['textStatus' => 'error', 'errors' => 'not_found'];

        $return = array();
        $user['comments']           = ($user['comments'] ? nl2br($user['comments']) : null);
        $return['user']             = sanitizeToJson($user);
        $return['user']['_image']   = file_exists(APP_ROOT . 'uploads/user/_pub/'.$user['id'].'.png');

        if ($acl->is_allowed($App->session->get('user')['acl'],'user.set_schedule')) $return['tabs']['schedule'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'user.acl_add') || $acl->is_allowed($App->session->get('user')['acl'],'user.acl_del')) $return['tabs']['acl'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'rfid.search'))       $return['tabs']['movements'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'non_working_days.search'))  $return['tabs']['non_working_days'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'user_absence.search')) $return['tabs']['absences'] = true;
        //if ($userAclRepository->is_allowed($App->session->get(user)['acl'],'cc_delivery')) $return['tabs']['cc_deliveries'] = true;

        $return['acl_defs'] = $App->cfg['modules'];

        $return = array_merge($return, $this->getCategories($App));

        $return['acl_data'] = $userAclRepository->getCombined($id, $categorySearch);

        $userNwdSearch = new UserNwdSearchDb($App->db);
        $return['nwd'] = $userNwdSearch->get('*', array('user_id' => $id));

        return $return;
    }



    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userSearch        = new UserSearchDb($App->db);
        $userRepository    = new UserRepositoryDb($App->db);

        if (!($user = $userSearch->getById('u[id,name,last_name]',$id))) return ['textStatus' => 'error', 'errors' => ['not_found']];
        $user = sanitizeToJson($user);

        if (!$App->request->fetch('confirm')) return compact('user');
        else {
            // soft delete
            if (!$App->request->fetch('del_data')) {
                $user['rfid']       =  null;
                $user['deleted']    = 1;
                $user['deleted_on'] = date('Y-m-d H:i:s');
                $user['deleted_by'] = $App->session->get('user')['id'];

                if (!$userRepository->save($user)) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_mod']];
            }
            // hard delete from DB
            else {
                if (!$userRepository->del($id)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_del'));
                if (file_exists(APP_ROOT . 'uploads/user/_pub/'.$user['id'].'.png')) unlink(APP_ROOT . 'uploads/user/_pub/'.$user['id'].'.png');
            }
        }

        return ['textStatus' => 'ok'];
    }


    /***********
     * SET ALL *
     ***********/
    public function set_all(\App $App)
    {
        $userSearch        = new UserSearchDb($App->db);
        $userRepository    = new UserRepositoryDb($App->db);
        $categorySearch    = new CategorySearchDb($App->db);

        $filters = $App->request->fetch('filters');
        if (!empty($filters['category'])) $filters['category'] = array_merge(array($filters['category']),$categorySearch->getChildrenIds($filters['category']));

        /*
        // ACL FILTERS
        $filters = $acl->getFilters($filters, $categories);
        if ($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'set_all')) $filters['id'] = $App->session->get('user')['id'];
        */

        $ids = array();
        foreach ($userSearch->get('u.id',$filters) as $e) $ids[] = $e['id'];

        if (!$userRepository->setInOut((($App->request->fetch('set')=='in') ? 1 : 0),$ids)) return array('textStatus' => 'error', 'errors' => array('db_data'));
        return array('textStatus' => 'ok');
    }


    /*******************
     * PRINT IN SCHOOL *
     * TODO: clean code
     *******************/
    public function print_in_school(\App $App)
    {
        $userSearch        = new UserSearchDb($App->db);
        $categorySearch    = new CategorySearchDb($App->db);

        $return = array();
        $return['_layout'] = 'print_in_school';
        $return['categories'] = array();

        // Get Employee sub-categories

        // Extreme dirty hotfix
        $categories = $App->db->prepareFetchAll("
            SELECT node.id, node.name, (COUNT(parent.name) - 1) AS depth
            FROM
              category AS node,
              category AS parent
            WHERE
              node.lft BETWEEN parent.lft AND parent.rgt
              AND node.id NOT IN( -- excluded categories by M. Olivera
                '4ebd7ff5c36fd', -- PF Primaria
                '4ebd8005696b8', -- PF Secundaria
                '53dcff757aee3', -- Permisos
                '53dd005e74145', -- Admin Ed Fisica
                '545c9f01d6055', -- Admin Plantas Funcionales
                '5913c45fdfb97' -- Visitas
              )
            GROUP BY node.id
            HAVING depth = 2
            ORDER BY node.lft
        ");
        //$categories = $categorySearch->getChildren('0000000000001'); // Empleados

        // Avoid duplicating users who belong to multiple categories
        $users_printed = array();

        foreach ($categories as $cat) {
            // Categories to skip - Requested by M.Olivera
            if (in_array($cat['name'], array(
                'Planta Funcional Primaria',
                'Planta Funcional Secundaria',
                'Permisos',
                'Admin Educacion Fisica',
                'Admin Plantas funcionales',
                'Visitas'
            ))) continue;

            if (!($users = $userSearch->get('u[id, name, last_name, cat_ids, cat_names, cc_cat_ids, cc_cat_names]', array(
                'deleted'   => '0',
                'in_school' => '1',
                'category'  => $cat['id'],
                'id_not'    => $users_printed
            ), 'last_name, name'))) continue;

            // Mustache compatible
            foreach ($users as &$user) {
                $user = sanitizeToJson($user);
                $users_printed[] = $user['id'];
            }

            $cat['users'] = $users;
            $return['categories'][] = $cat;
        }

        $return['total'] = count($users_printed);

        return $return;
    }


    /************
     * SCHEDULE *
     ************/
    public function set_schedule(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userSearch        = new UserSearchDb($App->db);
        $userRepository    = new UserRepositoryDb($App->db);

        if (!($user = $userSearch->getById('u[id]',$id))) return ['textStatus' => 'error', 'errors' => ['not_found']];

        /*
        $filters = array('id' => $id);
        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u.id',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];
        */

        if (!$userRepository->setSchedule($id,$App->request->post())) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_mod']];
        return array('textStatus' => 'ok');
    }
    

    /***********
     * ACL ADD *
     ***********/
    public function acl_add(\App $App)
    {
        if (!$_POST) return [];

        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userAclRepository = new UserAclRepositoryDb($App->db);
        $categoryModel     = new CategorySearchDb($App->db);

        /*
        $filters = array('id' => $id);

        $App->session->get('user')      = $App->session->get('user');
        $userSearch        = new UserSearchDb($App->db);

        // ACL FILTERS
        $categories        = $userAclRepository->getCategoriesAllowance($App->session->get('user'), 'user', $App->request->getAction(), $categoryModel, 1);

        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u[id,name,last_name]',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];
        */

        // POST comes as JSON UTF-8 encoded
        $_POST = array_map('utf8_decode', $_POST);

        // The easiest way is to
        // get user ACL -> delete them from DB -> Append the new permission -> Combine them and save them altogether to DB
        $acl = $userAclRepository->getUser($id, false);
        $userAclRepository->delUser($id);
        $acl[] = array(
            'allow' => @$_POST['allow'],
            'module' => @$_POST['module'],
            'action' => @$_POST['module_action'],
            'action_filter_criteria' => @$_POST['action_filter_criteria'],
            'action_filter_value' => @$_POST['action_filter_value']
        );
        if (!$userAclRepository->saveUser($id, $userAclRepository->assocToArray($userAclRepository->combine($acl)))) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_add'));

        return array('textStatus' => 'ok', 'acl_data' => $userAclRepository->getCombined($id, $categoryModel));
    }


    /***********
     * ACL DEL *
     ***********/
    public function acl_del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !is_numeric($id)) return array('fatal_error' => 'id_invalid');

        $userAclRepository = new UserAclRepositoryDb($App->db);

        if (!($permission = $userAclRepository->getUserPermission($id))) return array('fatal_error' => 'id_not_found');

        $categoryModel     = new CategorySearchDb($App->db);

        /*
        $filters = array('id' => $permission['user_id']);

        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u[id,name,last_name]',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];
        */

        if ($App->request->fetch('confirm')) {
            if (!$userAclRepository->delUserPermission($id)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_del'));
            return array('textStatus' => 'ok', 'acl_data' => $userAclRepository->getCombined($permission['user_id'], $categoryModel));
        }
    }



    /************************
     * NON WORKING DAYS ADD *
     ************************/
    public function non_working_days_add(\App $App)
    {
        if (!($user_id = $App->request->fetch('user_id')) || !ctype_xdigit($user_id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userSearch        = new UserSearchDb($App->db);
        $userNwdRepository = new UserNwdRepositoryDb($App->db);
        $userNwdSearch     = new UserNwdSearchDb($App->db);

        if (!($user = $userSearch->getById('u[id]',$user_id))) return ['textStatus' => 'error', 'errors' => ['not_found']];

        /*
        $filters = array('id' => $id);
        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $user_id != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u.id',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];
        */


        unset($_POST['to_setter']);
        if (!$userNwdRepository->save($_POST)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_add'));

        return array('textStatus' => 'ok', 'nwd' => $userNwdSearch->get('*', array('user_id' => $user_id)));
    }

    /************************
     * NON WORKING DAYS MOD *
     ************************/
    /*
    public function non_working_days_mod()
    {
        if (!($id = $App->request->fetch('id')) || !is_numeric($id)) return array('fatal_error' => 'id_invalid');
        $userNwdRepository = new UserNwdRepositoryDb($Db);

        if (!($nwd = $userNwdRepository->getById($id))) return array('fatal_error' => 'id_not_found');

        $filters = array('id' => $nwd['user_id']);

        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $nwd['user_id'] != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u.id',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];

        $userNwdSearch = new UserNwdSearchDb($Db);

        if (!$userNwdRepository->save($_POST)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_mod'));
        return array('textStatus' => 'ok', 'nwd' => $userNwdSearch->get('*', array('user_id' => $user['id'])));
    }
    */


    /************************
     * NON WORKING DAYS DEL *
     ************************/
    public function non_working_days_del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $userNwdRepository = new UserNwdRepositoryDb($App->db);
        $userNwdSearch     = new UserNwdSearchDb($App->db);
        $userSearch        = new UserSearchDb($App->db);


        if (!($nwd = $userNwdSearch->getById('id,user_id',$id))) return ['textStatus' => 'error', 'errors' => ['id_not_found']];

        /*
        $filters = array('id' => $nwd['user_id']);

        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if (($userAclRepository->selfOnly($App->session->get('user')['acl'],'user', 'del') && $nwd['user_id'] != $App->session->get('user')['id'])
            || (!($users = $userSearch->get('u.id',$filters)))
        ) return array('textStatus' => 'error', 'errors' => array('not_allowed_action'));
        $user = $users[0];
        */

        if (!$userNwdRepository->del($id)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_del'));
        return array('textStatus' => 'ok', 'nwd' => $userNwdSearch->get('*', array('user_id' => $nwd['user_id'])));
    }


    /***********************
     * PROCESS FORM (POST) *
     ***********************/
    private function processForm(\App $App, $old_row = null)
    {
        $userRepository    = new UserRepositoryDb($App->db);
        $categorySearch    = new CategorySearchDb($App->db);
        $cc_categorySearch = new CC_CategorySearchDb($App->db);

        $session_user = $App->session->get('user');

        $user = new User($old_row ? array_merge($old_row, $App->request->post()) : $App->request->post());
        $user['type'] = 'employee';

        if (!$old_row) { // Add
            if (is_null($App->request->post('pwd'))) return ['textStatus' => 'error', 'errors' => ['pwd'=>'null']];
        }
        else { // Mod
            // Matias puede modificar pwd
            if (($session_user['id'] != '4e31ab94dbe6d') && $App->request->post('pwd') && !empty($old_row['pwd']) && (!$App->request->post('pwd_old') || sha1($App->request->post('pwd_old')) != $old_row['pwd'])) return ['textStatus' => 'error', 'errors' => ['old_pwd_not_match']];
        }

        if (($errors = $user->getErrors())) return ['textStatus' => 'error', 'errors' => $errors];

        if (($duplicated_data = $userRepository->duplicatedData(array_intersect_key($App->request->post(), array_flip(['user', 'rfid', 'dni', 'file_number'])), ($old_row ? $old_row['id'] : null)))) {
            foreach ($duplicated_data as $e) $errors[$e] = 'duplicated_data';
            return ['textStatus' => 'error', 'errors' => $errors];
        }

        // Not deleted
        $deleted = $App->request->post('deleted');
        if (!$deleted || !intval($deleted)) {
            $user['deleted']    = '0';
            $user['deleted_on'] = null;
            $user['deleted_by'] = null;
        }

        // Category Ids and Names get cached in User table
        if (!empty($_POST['cat_ids']))    $user->setCats($categorySearch->getByIds('full', $App->request->fetch('cat_ids')));
        if (!empty($_POST['cc_cat_ids'])) $user->setCCCats($cc_categorySearch->getByIds('full', $App->request->fetch('cc_cat_ids')));

        if (!($id = $userRepository->save($user->getArrayCopy()))) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_add'));
        if ($old_row) $id = $old_row['id'];

        $userRepository->setUserCategory($id, ($App->request->fetch('cat_ids') ?: null), ($old_row ? $old_row['cat_ids'] : null))
            ->setUserCcCategory($id,($App->request->fetch('cc_cat_ids') ?: null), ($old_row ? $old_row['cc_cat_ids'] : null));

        // Delete old Picture
        if ($App->request->fetch('image_delete') && file_exists(APP_ROOT . 'uploads/user/_pub/'.$id.'.png')) unlink(APP_ROOT . 'uploads/user/_pub/'.$id.'.png');

        if (($image = $App->request->files('image')) && $image['name']) {
            if (!$this->saveImage($image, $id)) return ['textStatus' => 'error', 'errors' => ['image' => 'error_file_upload']];
        }
        return ['textStatus' => 'ok', 'id' => $id];
    }

    /*********************************
     * GET CATEGORIES (CAT & CC_CAT) *
     *********************************/
    private function getCategories(\App $App)
    {
        $acl               = new AclHelper($App->db);
        $categorySearch    = new CategorySearchDb($App->db);
        $cc_categorySearch = new CC_CategorySearchDb($App->db);
        $userAclRepository = new UserAclRepositoryDb($App->db);
        $categories        = $userAclRepository->getCategoriesAllowance($App->session->get('user'), 'user', 'search', $categorySearch, 0);

        $return = [];
        $return['categories'] = [];
        foreach ($categories as $row)  if ($row['_allow']) $return['categories'][$row['id']] = utf8_encode($row['name']);
        foreach ($cc_categorySearch->get('id,name', 'lft > 0') as $row) $return['cc_categories'][$row['id']] = utf8_encode($row['name']);

        return $return;
    }


    /**************
     * SAVE IMAGE *
     **************/
    private function saveImage($image = null, $id = null) {
        if (!$id || !$image || !$image['size'] || $image['error']) return false;

        $pic = new Image($image['tmp_name']);
        $pic->scaleToTarget(200,200);
        $pic->save(APP_ROOT . 'uploads/user/_pub/'.$id.'.png', IMAGETYPE_PNG, 100);

        return true;
    }
}
