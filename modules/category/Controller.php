<?php
namespace Jan_Category;

use Jan_User\UserNwdRepositoryDb,
    Jan_User\UserRepositoryDb,
    Jan_User\UserAclRepositoryDb,
    Jan_User\UserSearchDb,
    Jan_Acl\AclSearchDb,
    Jan_Acl\AclHelper;
;

class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $aclSearch = new AclSearchDb($App->db);
        $acl = new AclHelper($App->db);

        $tabs = [];
        if ($acl->is_allowed($App->session->get('user')['acl'],'category.set_schedule')) $tabs['schedule'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'category.acl_add') || $acl->is_allowed($App->session->get('user')['acl'],'category.acl_del')) $tabs['acl'] = true;
        if ($acl->is_allowed($App->session->get('user')['acl'],'category.non_working_days')) $tabs['non_working_days'] = true;

        $categorySearch  = new CategorySearchDb($App->db);
        $categories = $categorySearch->indentNestedNames($categorySearch->get('full'));
        foreach ($categories as &$e) {
            $e['name'] = utf8_encode($e['name']);
            $e['_mod'] = intval($e['_mod']);
            $e['_del'] = intval($e['_del']);
        }

        return[
            'categories' => $categories,
            'tabs'       => $tabs,
            'acl_defs'   => $App->cfg['modules'],
            'category'   => $categories[0],
            'acl_data'   => $aclSearch->getCategory($categories[0]['id']),
        ];
    }

    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {
        if (!($parent_id = $App->request->fetch('parent_id')) || !ctype_xdigit($parent_id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $categorySearch = new CategorySearchDb($App->db);
        if (!($parent = $categorySearch->getById('full', $parent_id))) return ['textStatus' => 'error', 'errors' => ['not_found']];

        if (!$App->request->post()) return [
            'parent' => $parent,
        ];

        $categoryRepository = new CategoryRepositoryDb($App->db);
        $category           = new Category($App->request->post());

        if (($errors = $category->getErrors($category))) return ['textStatus' => 'error', 'errors' => $errors];

        if (($duplicated_data = $categoryRepository->duplicatedData(array_intersect_key($App->request->post(), array_flip(['name']))))) {
            foreach ($duplicated_data as $e) $errors[$e] = 'duplicated_data';
            return ['textStatus' => 'error', 'errors' => $errors];
        }

        if (!($id = $categoryRepository->insertAt($category->getArrayCopy(),$parent_id))) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_add']];

        return ['textStatus' => 'ok', 'id' => $id];
    }


    /*******
     * MOD *
     *******/
    public function mod(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $categorySearch        = new CategorySearchDb($App->db);
        $categoryRepository    = new CategoryRepositoryDb($App->db);

        if (!($category = $categorySearch->getById('*',$id))) return ['textStatus' => 'error', 'errors' => ['not_found']];
        $category = sanitizeToJson($category);

        $parent = sanitizeToJson($categorySearch->getParent($id));

        if (!$App->request->post()) return [
            'category'  => $category,
            'parent'    => $parent,
        ];

        $new_category = new Category($App->request->post());

        if (($errors = $new_category->getErrors())) return ['textStatus' => 'error', 'errors' => $errors];

        if (($duplicated_data = $categoryRepository->duplicatedData(array_intersect_key($App->request->post(), array_flip(['name'])), $id))) {
            foreach ($duplicated_data as $e) $errors[$e] = 'duplicated_data';
            return ['textStatus' => 'error', 'errors' => $errors];
        }

        if ($categoryRepository->save($new_category->getArrayCopy()) == false) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_mod']];

        return ['textStatus' => 'ok', 'id' => $id];

    }

    /********
     * VIEW *
     ********/
    public function view(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];
        $categorySearch = new CategorySearchDb($App->db);
        $aclSearch      = new AclSearchDb($App->db);

        if (!($category = $categorySearch->getById('*',$id))) return ['textStatus' => 'error', 'errors' => ['not_found']];

        return [
            'acl_data' => $aclSearch->getCategory($id),
        ];
    }


    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => ['id_invalid']];

        $categorySearch        = new CategorySearchDb($App->db);
        $categoryRepository    = new CategoryRepositoryDb($App->db);

        if (!($category = $categorySearch->getById('*',$id))) return ['textStatus' => 'error', 'errors' => ['not_found']];
        $category = sanitizeToJson($category);

        if (!$App->request->fetch('confirm')) return compact('category');

        if (!$categoryRepository->del($id)) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_del']];
        return array('textStatus' => 'ok');
    }

    /************
     * SCHEDULE *
     ************/
    public function set_schedule(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');
        $categoryModel = new CategoryModel($App->db->getPdo());
        if (!($category = $categoryModel->getById($id))) return array('fatal_error' => 'id_not_found');
        unset($_POST['id']);

        $userRepository = new UserRepositoryDb($App->db);
        $userSearch = new UserSearchDb($App->db);

        // Category is tree inclusive
        $category_ids = array_merge(array($id),$categoryModel->getChildrenIds($id));

        $user_ids = array();

        foreach ($userSearch->get('u.id',array('category' => $category_ids)) as $row) $user_ids[] = $row['id'];
        if ($user_ids && !$userRepository->setSchedule($user_ids,$_POST)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_mod'));
        return array('textStatus' => 'ok');
    }

    /***********
     * ACL ADD *
     ***********/
    public function acl_add(\App $App)
    {
        if (!$_POST) return [];

        return [];

        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');
        $categoryModel = new CategoryModel($App->db->getPdo());
        if (!($category = $categoryModel->getById($id))) return array('fatal_error' => 'id_not_found');
        $categoryAclRepository = new CategoryAclRepositoryDb($App->db->getPdo());

        // POST comes as JSON UTF-8 encoded
        $_POST = array_map('utf8_decode', $_POST);

        // Easiest way
        // get ACL
        $acl = $categoryAclRepository->getCategory($id, $categoryModel, false);
        // delete them from DB
        $categoryAclRepository->delCategory($id);

        // Append the new permission
        $acl[] = array(
            'category_id' => $id,
            'allow' => $_POST['allow'],
            'module' => $_POST['module'],
            'action' => @$_POST['module_action'],
            'action_filter_criteria' => @$_POST['action_filter_criteria'],
            'action_filter_value' => @$_POST['action_filter_value']
        );
        // Combine them and save them altogether to DB
        if (!$categoryAclRepository->saveCategory($id, $categoryAclRepository->assocToArray($categoryAclRepository->combine($acl)))) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_add'));

        return array('textStatus' => 'ok', 'acl_data' => $categoryAclRepository->assocToArray($categoryAclRepository->getCategory($id, $categoryModel)));
    }

    /***********
     * ACL DEL *
     ***********/
    public function acl_del(\App $App)
    {
        return [];

        if (!($id = $App->request->fetch('id')) || !is_numeric($id)) return array('fatal_error' => 'id_invalid');
        if (!($permission = $categoryAclRepository->getCategoryPermission($id))) return array('fatal_error' => 'id_not_found');

        if ($App->request->fetch('confirm')) {
            if (!$categoryAclRepository->delCategoryPermission($id)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_del'));
            return array('textStatus' => 'ok', 'acl_data' => $categoryAclRepository->assocToArray($categoryAclRepository->getCategory($permission['category_id'], new CategoryModel($App->db->getPdo()))));
        }
    }

    /************************
     * NON WORKING DAYS ADD *
     ************************/
    public function non_working_days_add(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');
        $categoryModel = new CategoryModel($App->db->getPdo());
        if (!($category = $categoryModel->getById($id))) return array('fatal_error' => 'id_not_found');
        unset($_POST['id']);

        $userNwdRepository = new UserNwdRepositoryDb($App->db);
        $userSearch = new UserSearchDb($App->db);

        // Category is tree inclusive
        $category_ids = array_merge(array($id),$categoryModel->getChildrenIds($id));

        $user_ids = array();

        unset($_POST['to_setter']);
        foreach ($userSearch->get('u.id',array('category' => $category_ids)) as $row) $user_ids[] = $row['id'];
        if ($user_ids && !$userNwdRepository->save($_POST, $user_ids)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_mod'));
        return array('textStatus' => 'ok');
    }

    /************************
     * NON WORKING DAYS DEL *
     ************************/
    public function non_working_days_del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');
        $categoryModel = new CategoryModel($App->db->getPdo());
        if (!($category = $categoryModel->getById($id))) return array('fatal_error' => 'id_not_found');
        unset($_POST['id']);

        $userNwdRepository = new UserNwdRepositoryDb($App->db);
        $userSearch = new UserSearchDb($App->db);

        // Category is tree inclusive
        $category_ids = array_merge(array($id),$categoryModel->getChildrenIds($id));

        $user_ids = array();

        foreach ($userSearch->get('u.id',array('category' => $category_ids)) as $row) $user_ids[] = $row['id'];

        if ($user_ids && !$userNwdRepository->delByUserIds($user_ids)) return array('textStatus' => 'error', 'errors' => array('error_db' => 'error_mod'));
        return array('textStatus' => 'ok');
    }
}
return;