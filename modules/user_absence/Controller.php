<?php
namespace Jan_User_Absence;
use Jan_Category\CategoryModel,
    Jan_CC_Category\CC_CategorySearchDb,
    Jan_User\UserAclRepositoryDb,
    Jan_Acl\AclHelper
;

class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $cc_categoryModel   = new CC_CategorySearchDb($App->db);
        $acl                = new AclHelper($App->db);

        $session_user       = $App->session->get('user');

        // ACL FILTERS
        $acl_filters = $acl->get_filters($App->session->get('user')['acl'], 'rfid.search');
        if (isset($acl_filters['category_id'])) {
            $categories = array_merge($categoryModel->getChildrenIds($acl_filters['category_id']), [$acl_filters['category_id']]);
        }
        else $categories = $categoryModel->get();


        $return = array();

        // datatable headers
        if (!$App->request->fetch('theaders')) $theaders = array('image', 'fullname', 'date', 'category', 'cc_category', 'comments');
        else $theaders = explode(',',$App->request->fetch('theaders'));

        $filters = array();

        // datatable filters
        if (is_null($App->request->fetch('sEcho'))) {
            $return['categories'] = array();
            foreach ($categories as $row) $return['categories'][] = array('value' => $row['id'], 'label' => utf8_encode($row['name']));
            foreach ($cc_categoryModel->get('id,name') as $row) $return['cc_categories'][] = array('value' => $row['id'], 'label' => utf8_encode($row['name']));

            $filters['date_from'] = date('Y-m-d', strtotime('-1 day'));
        }

        for ($i=0;$i<count($theaders);$i++) $filters[$theaders[$i]] = $App->request->fetch('sSearch_'.$i);
        if ($App->request->fetch('user_id')) $filters['user_id'] = $App->request->fetch('user_id');

        if (is_null($order_by = $App->request->fetch('iSortCol_0'))) $order_by = 'u_a.date DESC';
        else {
            $order_by = $theaders[$order_by];
            if (in_array($order_by, array('date', 'comments'))) $order_by = 'u_a.'.$order_by;
            elseif ($order_by == 'fullname') $order_by = 'u.last_name';
            elseif ($order_by == 'category') $order_by = 'cat_names';
            elseif ($order_by == 'cc_category') $order_by = 'cc_cat_names';
            $order_by .= ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        }

        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', '.$App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', '.$App->request->fetch('iDisplayLength');


        if (!empty($filters['category'])) $filters['category'] = array_merge(array($filters['category']),$categoryModel->getChildrenIds($filters['category']));

        /**
        // ACL FILTERS
        $filters = $userAclRepository->getFilters($filters, $categories);
        if ($userAclRepository->selfOnly($session_user['acl'],'user', 'set_all')) $filters['id'] = $session_user['id'];
         */

        $user_absenceModel = new User_AbsenceModel($App->db->getPdo());
        $user_absences = $user_absenceModel->search($filters, $order_by, $limit_by);

        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $user_absenceModel->searchCount();
        $return['iTotalDisplayRecords'] = $user_absenceModel->searchCount($filters);
        $return['aaData'] = &$user_absences;

        return $return;

    }

    /*******
     * MOD *
     *******/
    public function mod(\App $App)
    {
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $cc_categoryModel   = new CC_CategorySearchDb($App->db);

        $session_user       = $App->session->get('user');

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !is_numeric($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $user_absenceModel = new User_AbsenceModel($App->db->getPdo());
        if (!($user_absence = $user_absenceModel->getById($id))) return array('fatal_error' => 'id_not_found');

        if (!$_POST) return array('user_absence' => $user_absence);

        // POST comes as JSON UTF-8 encoded
        $_POST = array_map('utf8_decode', $_POST);
        $errors = array();

        // array_merge() maintains $row[x] not present in $_POST
        if (!$user_absenceModel->mod(array_merge($user_absence, $_POST))) $errors[] = "mod_error";

        if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
        else return array('textStatus' => 'ok');
    }

    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        $userAclRepository  = new UserAclRepositoryDb($App->db);
        $categoryModel      = new CategoryModel($App->db->getPdo());
        $cc_categoryModel   = new CC_CategorySearchDb($App->db);

        $session_user       = $App->session->get('user');

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !is_numeric($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $user_absenceModel = new User_AbsenceModel($App->db->getPdo());
        if (!($user_absence = $user_absenceModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) return array('user_absence' => $user_absence);

        // Delete
        if (!$user_absenceModel->del($id)) $textStatus = "error";
        else $textStatus = "ok";

        return array('textStatus' => $textStatus);
    }
}