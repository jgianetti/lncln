<?php
namespace Jan_CC_Delivery;
use Jan_CC_Product\CC_ProductModel,
    Jan_CC_Category\CC_CategorySearchDb;

class Controller
{
    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        $return = array();

        // datatable headers
        if (!$App->request->fetch('theaders')) $theaders = array('opened_on', 'closed_on', 'user_name', 'cc_category', 'cc_product_name', 'comments', 'deleted');
        else $theaders = explode(',',$App->request->fetch('theaders'));

        // datatable filters
        if (is_null($App->request->fetch('sEcho'))) {
            // cc_category datatable filter
            $cc_categoryModel = new CC_CategorySearchDb($App->db);
            foreach ($cc_categoryModel->get('id,name','`id`!="0000000000001"') as $e) $return['cc_categories'][] = Array('value' => $e['id'], 'label' => utf8_encode($e['name']));
        }

        $search_data = array();
        for ($i=0;$i<count($theaders);$i++) $search_data[$theaders[$i]] = $App->request->fetch('sSearch_'.$i);
        if ($App->request->fetch('user_id')) $search_data['user_id'] = $App->request->fetch('user_id');
        if ($App->request->fetch('cc_product_id')) $search_data['cc_product_id'] = $App->request->fetch('cc_product_id');
        if (!is_null($App->request->fetch('deleted'))) $search_data['deleted'] = $App->request->fetch('deleted');

        if (is_null($order_by = $App->request->fetch('iSortCol_0'))) $order_by = 'cc_d.closed_on DESC';
        else {
            $order_by = $theaders[$order_by];
            if (in_array($order_by, array('opened_on', 'closed_on', 'comments', 'deleted'))) $order_by = 'cc_d.'.$order_by;
            elseif ($order_by == 'user_name') $order_by = 'user_name';
            elseif ($order_by == 'cc_category') $order_by = 'cc_category_name';
            elseif ($order_by == 'cc_product_name') $order_by = 'cc_prod.' . $order_by;
            $order_by .= ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        }

        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', '.$App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', '.$App->request->fetch('iDisplayLength');

        $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
        $cc_deliveries = $cc_deliveryModel->search($search_data, $order_by, $limit_by);

        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $cc_deliveryModel->searchCount();
        $return['iTotalDisplayRecords'] = $cc_deliveryModel->searchCount($search_data);
        $return['aaData'] = &$cc_deliveries;

        return $return;
    }

    /********
     * VIEW *
     ********/
    public function view(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
        if (!($cc_delivery = $cc_deliveryModel->getById($id))) return array('fatal_error' => 'id_not_found');

        $cc_delivery['comments'] = nl2br($cc_delivery['comments']);

        return compact('cc_delivery');
    }

    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {
        // Process $_POST and Return textStatus
        if (!$_POST) {
            $cc_productModel = new CC_ProductModel($App->db->getPdo());

            // search form datalist - name + internal barcode + barcode
            $cc_product_datalist = array();
            $cc_product = $cc_productModel->get();
            foreach ($cc_product as $e) {
                $cc_product_datalist[] = utf8_encode($e['name']);
                $cc_product_datalist[] = $e['deposit'].$e['family'].$e['item'].$e['brand'].$e['size'].$e['color'];
                $cc_product_datalist[] = $e['barcode'];
            }

            return compact('cc_product_datalist');
        }
        else {
            $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if (!$App->request->fetch('user_id')) {
                $form_valid = false;
                $errors['user_name'] = 'null';
            }

            if (!($products = $App->request->fetch('products'))) {
                $form_valid = false;
                $errors['products'] = 'null';
            }
            else {
                // Validate quantity and unit_price
                foreach ($products as $id => $cc_p) {
                    if (!is_numeric($cc_p['quantity']) || !$cc_p['quantity'] || ($cc_p['quantity'] > $cc_p['stock'])) {
                        if (!is_numeric($cc_p['quantity'])) $errors['quantity'] = 'not_num';
                        elseif (!$cc_p['quantity']) $errors['quantity'] = 'not_min';
                        elseif ($cc_p['quantity'] > $cc_p['stock']) $errors['quantity_max'] = '';
                        $form_valid = false;
                        break;
                    }
                }
            }

            if ($form_valid) {
                // POST comes as JSON UTF-8 encoded
                $post = $_POST;
                $session_user = $App->session->get('user');
                $post['opened_by'] = $session_user['id'];
                if ($post['closed_on']) $post['closed_by'] = $session_user['id'];
                else $post['closed_on'] = $post['closed_by'] = null;

                $post['comments'] = utf8_decode($post['comments']);

                if (!$cc_deliveryModel->add($post)) $errors["add_error"] = '';
            }

            if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
            else return array('textStatus' => 'ok');
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
        $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
        if (!($cc_delivery = $cc_deliveryModel->getById($id))) return array('fatal_error' => 'id_not_found');


        if (!$_POST) {
            $image_src = '_pub/img/cc_product/';
            foreach ($cc_delivery['products'] as $i => $cc_p) {
                if (!$cc_p['quantity_delivered']) $cc_delivery['products'][$i]['_removable'] = true;
                $cc_delivery['products'][$i]['image_src'] = $App->cfg['base_url'] . '/' . $image_src . (file_exists($image_src.$cc_p['id'].'.png') ? $cc_p['id'].'.png' : 'not_found.jpg');
            }

            $cc_productModel = new CC_ProductModel($App->db->getPdo());

            // search form datalist - name + internal barcode + barcode
            $cc_product_datalist = array();
            $cc_product = $cc_productModel->get();
            foreach ($cc_product as $e) {
                $cc_product_datalist[] = utf8_encode($e['name']);
                $cc_product_datalist[] = $e['deposit'].$e['family'].$e['item'].$e['brand'].$e['size'].$e['color'];
                $cc_product_datalist[] = $e['barcode'];
            }

            return compact('cc_delivery', 'cc_product_datalist');
        }
        else {
            $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if (!$App->request->fetch('user_id')) {
                $form_valid = false;
                $errors['user_name'] = 'null';
            }

            if (!($products = $App->request->fetch('products'))) {
                $form_valid = false;
                $errors['products'] = 'null';
            }
            else {
                // Validate quantity
                foreach ($products as $id => $cc_p) {
                    if (!is_numeric($cc_p['quantity']) || !$cc_p['quantity'] || ($cc_p['quantity'] > $cc_p['stock'])) {
                        if (!is_numeric($cc_p['quantity'])) $errors['quantity'] = 'not_num';
                        elseif (!$cc_p['quantity']) $errors['quantity'] = 'not_min';
                        elseif ($cc_p['quantity'] > $cc_p['stock']) $errors['quantity_max'] = '';
                        $form_valid = false;
                        break;
                    }
                }
            }

            if ($form_valid) {
                $post = $_POST;
                $session_user = $App->session->get('user');

                // update opened_by
                if ($cc_delivery['opened_on'] != $post['opened_on']) $post['opened_by'] = $session_user['id'];
                else $post['opened_by'] = $cc_delivery['opened_by'];

                if (!$post['closed_on']) $post['closed_on'] = $post['closed_by'] = null;
                // update closed_by
                elseif ($cc_delivery['closed_on'] != $post['closed_on']) $post['closed_by'] = $session_user['id'];
                else $post['closed_by'] = $cc_delivery['closed_by'];

                if (!$cc_deliveryModel->mod($post)) $errors["mod_error"] = '';
            }

            if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
            else return array('textStatus' => 'ok');
        }
    }

    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
        if (!($cc_delivery = $cc_deliveryModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) return compact('cc_delivery');
        else {
            $errors = array();
            $textStatus = '';

            if (!$App->request->fetch('del_data')) {
                $session_user = $App->session->get('user');
                $cc_delivery['deleted'] = 1;
                $cc_delivery['deleted_on'] = date('Y-m-d H:i:s');
                $cc_delivery['deleted_by'] = $session_user['id'];

                // set 'deleted' = '1'
                if (!$cc_deliveryModel->softDel($cc_delivery)) $textStatus = "error";
                else $textStatus = "ok";
            }
            // delete from DB
            else {
                if (!$cc_deliveryModel->delByIds($id)) $textStatus = "error";
                else $textStatus = "ok";
            }

            return array('textStatus' => $textStatus);

        }
    }

    /*********
     * UNDEL *
     *********/
    public function undel(\App $App)
    {
        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_deliveryModel = new CC_DeliveryModel($App->db->getPdo());
        if (!($cc_delivery = $cc_deliveryModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) return compact('cc_delivery');
        else {
            $errors = array();
            $textStatus = '';

            $session_user = $App->session->get('user');
            $cc_delivery['deleted'] = 0;

            // set 'deleted' = '1'
            if (!$cc_deliveryModel->undel($cc_delivery)) $textStatus = "error";
            else $textStatus = "ok";

            return array('textStatus' => $textStatus);

        }
    }
}