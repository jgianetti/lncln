<?php
namespace Jan_CC_Product;
use \Image;

class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        // @todo: REMOVER
        $App->db->exec('UPDATE cc_purchase SET closed_on = opened_on, closed_by = opened_by, status = 2 WHERE closed_on IS NULL');

        $return = array();

        // datatable headers
        if (!($theaders=$App->request->fetch('theaders'))) $theaders = array('image', 'name', 'barcode_int', 'barcode', 'measurement_unit', 'stock', 'stock_price', 'comments', 'deleted');
        else $theaders = explode(',',$App->request->fetch('theaders'));

        $search_data = array();
        for ($i=0;$i<count($theaders);$i++) $search_data[$theaders[$i]] = $App->request->fetch('sSearch_'.$i);
        if ($App->request->fetch('name_barcode')) $search_data['name_barcode'] = $App->request->fetch('name_barcode');

        if (!($order_by = $App->request->fetch('iSortCol_0'))) $order_by = 'name ASC';
        else {
            $order_by = $theaders[$order_by];
            if ($order_by == 'stock') $order_by = '(total_quantity-total_quantity_delivered)';
            elseif ($order_by == 'stock_price') $order_by = '(total_price-total_price_delivered)';
            $order_by .= ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        }

        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', '.$App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', '.$App->request->fetch('iDisplayLength');

        $cc_productModel = new CC_ProductModel($App->db->getPdo());
        $cc_products = $cc_productModel->rowsToMustache($cc_productModel->search($search_data, $order_by, $limit_by));

        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $cc_productModel->searchCount();
        $return['iTotalDisplayRecords'] = $cc_productModel->searchCount($search_data);
        $return['aaData'] = &$cc_products;

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
            $cc_productModel = new CC_ProductModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if ($form_valid) {
                // POST comes as JSON UTF-8 encoded
                $post = array_filter(array_map('utf8_decode', $_POST));

                if (!$cc_productModel->add($post)) $errors[] = "add_error";
                else {
                    $id = $cc_productModel->lastInsertId();

                    if (isset($_FILES['image']) && $_FILES['image']['size'] && !$_FILES['image']['error']) {
                        $pic = new Image($_FILES['image']['tmp_name']);
                        $pic->scaleToTarget(200,200);
                        $pic->save(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png', IMAGETYPE_PNG, 100);
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
        $cc_productModel = new CC_ProductModel($App->db->getPdo());
        if (!($cc_product = $cc_productModel->getCCProductById($id))) return array('fatal_error' => 'id_not_found');

        // Return db data to fill form
        if (!$_POST) {
            $cc_product = $cc_productModel->rowToMustache($cc_product, $App->cfg);
            return compact('cc_product');
        }
        // Process $_POST and Return textStatus
        else {
            $form_valid = true;
            $errors = array();

            if ($form_valid) {
                // POST comes as JSON UTF-8 encoded
                $post = array_map('utf8_decode', $_POST);

                // Delete old Picture
                if (isset($post['image_delete']) && $post['image_delete']) {
                    if (file_exists(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png')) unlink(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png');
                    unset($post['image_delete']);
                }

                // Not deleted
                if (!isset($post['deleted']) || !intval($post['deleted'])) {
                    $post['deleted'] = '0';
                    $post['deleted_on'] = null;
                    $post['deleted_by'] = null;
                }

                if (!$cc_productModel->modById($id, $post)) $errors[] = "mod_error";
                else {
                    if (isset($_FILES['image']) && $_FILES['image']['size'] && !$_FILES['image']['error']) {
                        $pic = new Image($_FILES['image']['tmp_name']);
                        $pic->scaleToTarget(200,200);
                        $pic->save(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png', IMAGETYPE_PNG, 100);
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
        $cc_productModel = new CC_ProductModel($App->db->getPdo());
        if (!($cc_product = $cc_productModel->rowToMustache($cc_productModel->getCCProductById($id)))) return array('fatal_error' => 'id_not_found');

        return compact('cc_product');
    }

    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_productModel = new CC_ProductModel($App->db->getPdo());
        if (!($cc_product = $cc_productModel->getCCProductById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) {
            $cc_product = $cc_productModel->rowToMustache($cc_product, $App->cfg);
            return compact('cc_product');
        }
        // Delete
        else {
            $errors = array();
            $textStatus = '';

            if (!$App->request->fetch('del_data')) {
                if (!$cc_productModel->modById($cc_product['id'], [
                    'deleted'    => 1,
                    'deleted_on' => date('Y-m-d H:i:s'),
                    'deleted_by' => $App->session->get('user')['id'],
                ])) $textStatus = "error";
                else $textStatus = "ok";
            }
            // delete from DB
            else {
                if (!$cc_productModel->delByIds($id)) $textStatus = "error";
                else {
                    $textStatus = "ok";
                    if (file_exists(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png')) unlink(APP_ROOT . 'uploads/cc_product/_pub/'.$id.'.png');
                }
            }

            return array('textStatus' => $textStatus);
        }
    }
}