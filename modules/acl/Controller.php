<?php
namespace Jan_Acl;
use RepositoryDb;

class Controller
{
    
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $aclSearch  = new AclSearchDb($App->db);

        $search_params = $App->request->get_search_params(['name', 'allow', 'module', 'action', 'filter_criteria', 'filter_value']);

        $acls = sanitizeToJson($aclSearch->get($search_params['select'], $search_params['filters'], $search_params['order_by'], $search_params['limit']));
        
        if (!$search_params['jqdt']) return['acls' => $acls];

        return [
            'sEcho'                => $App->request->fetch('sEcho'),
            'iTotalRecords'        => $aclSearch->count(),
            'iTotalDisplayRecords' => $aclSearch->count($search_params['filters']),
            'aaData'               => $acls,
        ];
    }
    
    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {
        if (!$App->request->post()) return [
            'modules' => $App->cfg['modules']
        ];

        $aclRepository = new RepositoryDb($App->db, 'acl');
        $acl           = new Acl($App->request->post());
        $acl['action'] = $App->request->post('module_action'); // hotfix - form.action

        if (($errors = $acl->getErrors($acl))) return ['textStatus' => 'error', 'errors' => $errors];

        if (($duplicated_data = $aclRepository->duplicatedData(array_intersect_key($App->request->post(), array_flip(['name']))))) {
            foreach ($duplicated_data as $e) $errors[$e] = 'duplicated_data';
            return ['textStatus' => 'error', 'errors' => $errors];
        }

        if (!($id = $aclRepository->save($acl->getArrayCopy()))) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_add']];
        return ['textStatus' => 'ok', 'id' => $id];
    }
    
    
    /*******
     * MOD *
     *******/
    public function mod(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => 'id_invalid'];

        $aclSearch        = new AclSearchDb($App->db);
        $aclRepository    = new RepositoryDb($App->db, 'acl');

        if (!($acl = $aclSearch->getById('*',$id))) return ['textStatus' => 'error', 'errors' => 'not_found'];
        $acl = sanitizeToJson($acl);

        if (!$App->request->post()) return [
            'acl' => $acl,
            'modules' => $App->cfg['modules'],
        ];

        $new_acl = new Acl($App->request->post());
        $new_acl['action'] = $App->request->post('module_action'); // hotfix - form.action

        if (($errors = $new_acl->getErrors())) return ['textStatus' => 'error', 'errors' => $errors];

        if (($duplicated_data = $aclRepository->duplicatedData(array_intersect_key($App->request->post(), array_flip(['name'])), $id))) {
            foreach ($duplicated_data as $e) $errors[$e] = 'duplicated_data';
            return ['textStatus' => 'error', 'errors' => $errors];
        }

        if ($aclRepository->save($new_acl->getArrayCopy()) == false) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_mod']];

        return ['textStatus' => 'ok', 'id' => $id];

    }

    
    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return ['textStatus' => 'error', 'errors' => 'id_invalid'];

        $aclSearch        = new AclSearchDb($App->db);
        $aclRepository    = new RepositoryDb($App->db, 'acl');

        if (!($acl = $aclSearch->getById('*',$id))) return ['textStatus' => 'error', 'errors' => 'not_found'];
        $acl = sanitizeToJson($acl);

        if (!$App->request->fetch('confirm')) return compact('acl');

        if (!$aclRepository->del($id)) return ['textStatus' => 'error', 'errors' => ['error_db' => 'error_del']];
        return array('textStatus' => 'ok');
    }
}