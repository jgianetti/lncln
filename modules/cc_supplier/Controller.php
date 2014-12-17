<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 27/08/12
 * Time: 19:58
 */
namespace Jan_CC_Supplier;
use \Image;

class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $return = array();

        // datatable headers
        if (!$App->request->fetch('theaders')) $theaders = array('image', 'name', 'address', 'phone', 'email', 'comments', 'deleted');
        else $theaders = explode(',',$App->request->fetch('theaders'));

        $search_data = array();
        for ($i=0;$i<count($theaders);$i++) $search_data[$theaders[$i]] = $App->request->fetch('sSearch_'.$i);
        if ($App->request->fetch('name')) $search_data['name'] = $App->request->fetch('name');

        if ($order_by = $App->request->fetch('iSortCol_0')) $order_by = $theaders[$order_by] . ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        else  $order_by = 'name ASC';

        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', '.$App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', '.$App->request->fetch('iDisplayLength');

        $cc_supplierModel = new CC_SupplierModel($App->db->getPdo());
        $cc_suppliers = $cc_supplierModel->search($search_data, $order_by, $limit_by);

        $image_src = '_pub/img/cc_supplier/';
        foreach ($cc_suppliers as &$e) {
            // Mustache compatible
            $e = sanitizeToJson($e);
            $e['deleted'] = intval($e['deleted']);

            if (file_exists(APP_ROOT . 'uploads/cc_supplier/_pub/'.$e['id'].'.png')) $e['image_src'] = $App->cfg['base_url'] . '/uploads/cc_supplier/_pub/' . $e['id'].'.png';
            else $e['image_src'] = $App->cfg['base_url'] . '/modules/user/_pub/img/not_found.jpg';
        }

        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $cc_supplierModel->searchCount();
        $return['iTotalDisplayRecords'] = $cc_supplierModel->searchCount($search_data);
        $return['aaData'] = &$cc_suppliers;
        return $return;
    }

    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {

        // Process $_POST and Return textStatus
        if (!$_POST) return true;
        else {
            $cc_supplierModel = new CC_SupplierModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if ($form_valid) {
                $post = array_map('utf8_decode', $_POST);
                if (!$cc_supplierModel->add($post)) $errors[] = "add_error";
                else {
                    $id = $cc_supplierModel->lastInsertId();

                    if (isset($_FILES['image']) && $_FILES['image']['size'] && !$_FILES['image']['error']) {
                        $pic = new Image($_FILES['image']['tmp_name']);
                        $pic->scaleToTarget(200,200);
                        $pic->save(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png', IMAGETYPE_PNG, 100);
                    }
                }
            }

            if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
            else return array('textStatus' => 'ok', 'id' => $id);
        }

    }

    /*******
     * MOD *
     *******/
    public function mod(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_supplierModel = new CC_SupplierModel($App->db->getPdo());
        if (!($cc_supplier = $cc_supplierModel->getById($id)))  return array('fatal_error' => 'id_not_found');

        // Return db data to fill form
        if (!$_POST) {
            $cc_supplier = sanitizeToJson($cc_supplier);
            $cc_supplier['deleted'] = intval($cc_supplier['deleted']);

            // has an Image
            if (file_exists('_pub/img/cc_supplier/'.$id.'.png')) $cc_supplier['_has_image'] = true;
            return compact('cc_supplier');
        }
        // Process $_POST and Return textStatus
        else {
            $form_valid = true;
            $errors = array();

            if ($form_valid) {
                $post = array_map('utf8_decode', $_POST);

                // Delete old Picture
                if (isset($post['image_delete']) && $post['image_delete']) {
                    if (file_exists(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png')) unlink(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png');
                    unset($post['image_delete']);
                }

                // Not deleted
                if (!isset($post['deleted']) || !intval($post['deleted'])) {
                    $post['deleted'] = '0';
                    $post['deleted_on'] = null;
                    $post['deleted_by'] = null;
                }

                if (!$cc_supplierModel->modById($id, $post)) $errors[] = "mod_error";
                else {
                    if (isset($_FILES['image']) && $_FILES['image']['size'] && !$_FILES['image']['error']) {
                        $pic = new Image($_FILES['image']['tmp_name']);
                        $pic->scaleToTarget(200,200);
                        $pic->save(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png', IMAGETYPE_PNG, 100);
                    }
                }
            }

            if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
            else return array('textStatus' => 'ok');
        }
    }


    /********
     * VIEW *
     ********/
    public function view(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_supplierModel = new CC_SupplierModel($App->db->getPdo());
        if (!($cc_supplier = $cc_supplierModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // Mustache compatible
        $cc_supplier = sanitizeToJson($cc_supplier);

        if (file_exists(APP_ROOT . 'uploads/cc_supplier/_pub/'.$cc_supplier['id'].'.png')) $cc_supplier['image_src'] = $App->cfg['base_url'] . '/uploads/cc_supplier/_pub/' . $cc_supplier['id'].'.png';
        else $cc_supplier['image_src'] = $App->cfg['base_url'] . '/modules/user/_pub/img/not_found.jpg';

        $cc_supplier['comments']  = nl2br($cc_supplier['comments']);
        $cc_supplier['deleted']   = intval($cc_supplier['deleted']);

        return compact('cc_supplier');
    }

    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_supplierModel = new CC_SupplierModel($App->db->getPdo());
        if (!($cc_supplier = $cc_supplierModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) {
            $image_src = '_pub/img/cc_supplier/';
            $cc_supplier['image_src'] = $App->cfg['base_url'] . '/' . $image_src . (file_exists($image_src.$cc_supplier['id'].'.png') ? $cc_supplier['id'].'.png' : 'not_found.jpg');

            return compact('cc_supplier');
        }
        // Delete
        else {
            $errors = array();
            $textStatus = '';

            if (!$App->request->fetch('del_data')) {
                $session_user = $App->session->get('user');
                $cc_supplier['deleted'] = 1;
                $cc_supplier['deleted_on'] = date('Y-m-d H:i:s');
                $cc_supplier['deleted_by'] = $session_user['id'];

                if (!$cc_supplierModel->modById($cc_supplier['id'], $cc_supplier)) $textStatus = "error";
                else $textStatus = "ok";
            }
            // delete from DB
            else {
                if (!$cc_supplierModel->delByIds($id)) $textStatus = "error";
                else {
                    $textStatus = "ok";
                    if (file_exists(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png')) unlink(APP_ROOT . 'uploads/cc_supplier/_pub/'.$id.'.png');
                }
            }

            return array('textStatus' => $textStatus);
        }
    }

}