<?php
namespace Jan_CC_Purchase;
use Jan_CC_Product\CC_ProductModel;

class Controller
{
    private $_cfg;

    public function __construct()
    {
        $this->_cfg = include(__DIR__ . '/config.local.php');
    }

    /**********
     * SEARCH *
     **********/
    public function search(\App $App)
    {
        // @todo: REMOVER
        $App->db->exec('UPDATE cc_purchase SET closed_on = opened_on, closed_by = opened_by, status = 2 WHERE closed_on IS NULL');

        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

        $return = array();

        // datatable headers
        if (!$App->request->fetch('theaders')) $theaders = array('order_num', 'opened_on', 'closed_on', 'cc_supplier_name', 'cc_product_name', 'status', 'comments', 'deleted');
        else $theaders = explode(',',$App->request->fetch('theaders'));

        $search_data = array();
        for ($i=0;$i<count($theaders);$i++) $search_data[$theaders[$i]] = $App->request->fetch('sSearch_'.$i);
        if ($App->request->fetch('cc_supplier_id')) $search_data['cc_supplier_id'] = $App->request->fetch('cc_supplier_id');
        if ($App->request->fetch('cc_product_id')) $search_data['cc_product_id'] = $App->request->fetch('cc_product_id');
        if (!is_null($App->request->fetch('deleted'))) $search_data['deleted'] = $App->request->fetch('deleted');

        if (is_null($order_by = $App->request->fetch('iSortCol_0'))) $order_by = 'cc_p.closed_on DESC';
        else {
            $order_by = $theaders[$order_by];
            if (in_array($order_by, array('order_num', 'opened_on', 'closed_on', 'comments', 'deleted'))) $order_by = 'cc_p.'.$order_by;
            elseif ($order_by == 'cc_product_name') $order_by = 'cc_prod.' . $order_by;
            elseif ($order_by == 'cc_supplier_name') $order_by = 'cc_s.name';
            $order_by .= ' ' . strtoupper($App->request->fetch('sSortDir_0'));
        }

        if (!($limit_by = $App->request->fetch('iDisplayStart'))) $limit_by = 0;
        if (is_null($App->request->fetch('iDisplayLength'))) $limit_by .= ', '.$App->cfg['rows_per_pag'];
        elseif ($App->request->fetch('iDisplayLength') > 0) $limit_by .= ', '.$App->request->fetch('iDisplayLength');

        $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
        $cc_purchases = $cc_purchaseModel->search($search_data, $order_by, $limit_by);

        $return['sEcho'] = $App->request->fetch('sEcho');
        $return['iTotalRecords'] = $cc_purchaseModel->searchCount();
        $return['iTotalDisplayRecords'] = $cc_purchaseModel->searchCount($search_data);
        $return['aaData'] = &$cc_purchases;

        $return['_user'] = $user_permission;

        return $return;

    }

    /********
     * VIEW *
     ********/
    public function view(\App $App)
    {
        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
        if (!($cc_purchase = $cc_purchaseModel->getById($id))) return array('fatal_error' => 'id_not_found');

        $cc_purchase['comments'] = nl2br($cc_purchase['comments']);

        return array('cc_purchase' => $cc_purchase, '_user' => $user_permission);

    }

    /*******
     * ADD *
     *******/
    public function add(\App $App)
    {
        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

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
            $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if (!$App->request->fetch('cc_supplier_id')) {
                $form_valid = false;
                $errors['cc_supplier_name'] = 'null';
            }

            if (!($products = $App->request->fetch('products'))) {
                $form_valid = false;
                $errors['products'] = 'null';
            }
            else {
                // Validate quantity and unit_price
                foreach ($products as $id => $e) {
                    if (!is_numeric($products[$id]['quantity']) || !$products[$id]['quantity'] || !is_numeric($products[$id]['unit_price'])) {
                        $form_valid = false;
                        if (!is_numeric($products[$id]['quantity'])) $errors['quantity'] = 'not_num';
                        elseif (!$products[$id]['quantity']) $errors['quantity'] = 'not_min';
                        if (!is_numeric($products[$id]['unit_price'])) $errors['unit_price'] = 'not_num';
                        break;
                    }
                }
            }

            if ($form_valid) {
                $post = $_POST;
                // POST comes as JSON UTF-8 encoded
                $session_user = $App->session->get('user');
                $post['opened_by'] = $post['closed_by'] = $session_user['id'];
                $post['comments']  = utf8_decode($post['comments']);

                if (!$cc_purchaseModel->add($post)) $errors["add_error"] = '';

                /*
                else {
                    // Mailer
                    $mailer = new_PHPMailer($App->cfg['smtp']);
                    $mailer->isHTML(true);
                    $mailer->Subject = $this->_cfg['mailer'][0]['subject'];
                    $mailer->Body    = $this->_cfg['mailer'][0]['body'];
                    foreach ($this->_cfg['mailer'][0]['recipients'] as $recipient) {
                        $mailer->AddAddress($recipient['email'], (isset($recipient['name']) ? $recipient['name'] : null));
                    }
                    $mailer->Body .= '<a href="http://' . $_SERVER['SERVER_NAME'] . $App->cfg['base_url'] . '/cc_purchase/view/' . $id . '">Ver Compra</a>.';
                    $mailer->send();
                }
                */
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
        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
        if (!($cc_purchase = $cc_purchaseModel->getById($id))) return array('fatal_error' => 'id_not_found');

        if (!$_POST) {
            $image_src = '_pub/img/cc_product/';
            foreach ($cc_purchase['products'] as $i => $cc_p) {
                if (!$cc_p['quantity_delivered']) $cc_purchase['products'][$i]['_removable'] = true;
				if (file_exists(APP_ROOT . 'uploads/cc_product/_pub/' . $cc_p['id'].'.png')) {
					$cc_purchase['products'][$i]['image_src'] = $App->cfg['base_url'] . '/uploads/cc_product/_pub/' . $cc_p['id'].'.png';
				}
				else {
					$cc_purchase['products'][$i]['image_src'] = $App->cfg['base_url'] . '/modules/cc_product/_pub/img/not_found.jpg';
				}
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

            return array('cc_purchase' => $cc_purchase, 'cc_product_datalist' => $cc_product_datalist, '_user' => $user_permission);
        }
        else {
            $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
            $form_valid = true;
            $errors = array();

            if (!$App->request->fetch('cc_supplier_id')) {
                $form_valid = false;
                $errors['cc_supplier_name'] = 'null';
            }

            if (!($products = $App->request->fetch('products'))) {
                $form_valid = false;
                $errors['products'] = 'null';
            }
            else {
                // Validate quantity and unit_price
                foreach ($products as $id => $cc_p) {
                    if (!is_numeric($cc_p['quantity']) || !$cc_p['quantity'] ||!is_numeric($cc_p['unit_price'])) {
                        $form_valid = false;
                        if (!is_numeric($cc_p['quantity'])) $errors['quantity'] = 'not_num';
                        elseif (!$cc_p['quantity']) $errors['quantity'] = 'not_min';
                        if (!is_numeric($cc_p['unit_price'])) $errors['unit_price'] = 'not_num';
                        break;
                    }
                    elseif ($cc_p['quantity'] < $cc_p['quantity_delivered']) {
                        $form_valid = false;
                        $errors['quantity_min'] = '';
                        break;
                    }
                }
            }

            if ($form_valid) {
                $post = $_POST;
                $session_user = $App->session->get('user');

                // update opened_by
                if ($cc_purchase['opened_on'] != $post['opened_on']) $post['opened_by'] = $session_user['id'];
                else $post['opened_by'] = $cc_purchase['opened_by'];

                if (!$post['closed_on']) $post['closed_on'] = $post['closed_by'] = null;
                // update closed_by
                elseif ($cc_purchase['closed_on'] != $post['closed_on']) $post['closed_by'] = $session_user['id'];
                else $post['closed_by'] = $cc_purchase['closed_by'];

                if (!$cc_purchaseModel->mod($post)) $errors["mod_error"] = '';
                /*
                elseif ($post['status'] < 2) {
                    // Mailer
                    $mailer          = new_PHPMailer($App->cfg['smtp']);
                    $mailer->isHTML(true);
                    $mailer->Subject = $this->_cfg['mailer'][$post['status']]['subject'];
                    $mailer->Body    = $this->_cfg['mailer'][$post['status']]['body'];
                    foreach ($this->_cfg['mailer'][$post['status']]['recipients'] as $recipient) {
                        $mailer->AddAddress($recipient['email'], (isset($recipient['name'])?$recipient['name']:null));
                    }
                    $mailer->Body .= '<a href="http://' . $_SERVER['SERVER_NAME'] .$App->cfg['base_url'] . '/cc_purchase/view/' . $id . '">Ver Compra</a>.';
                    $mailer->send();
                }
                */
            }

            if ($errors) return array('textStatus' => 'error', 'errors' => $errors);
            else return array('textStatus' => 'ok');
        }

    }

    /**************
     * SET STATUS *
     **************/
    public function set_status(\App $App)
    {
        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
        $session_user = $App->session->get('user');

        $data = array();
        $data['status'] = $App->request->fetch('status');

        if ($data['status'] == 2) {
            $data['closed_on'] = date('Y-m-d H:i:s');
            $data['closed_by'] = $session_user['id'];
        }
        else {
            $data['closed_on'] = null;
            $data['closed_by'] = null;
        }

        if (!$cc_purchaseModel->set_status($id, $data)) return array('textStatus' => 'error', 'errors' => array("mod_error" => ''));

        /*
        if ($data['status'] == 1) {
            // Mailer
            $mailer          = new_PHPMailer($App->cfg['smtp']);
            $mailer->isHTML(true);
            $mailer->Subject = $this->_cfg['mailer'][1]['subject'];
            $mailer->Body    = $this->_cfg['mailer'][1]['body'];
            foreach ($this->_cfg['mailer'][1]['recipients'] as $recipient) {
                $mailer->AddAddress($recipient['email'], (isset($recipient['name'])?$recipient['name']:null));
            }
            $mailer->Body .= '<a href="http://' . $_SERVER['SERVER_NAME'] . $App->cfg['base_url'] . '/cc_purchase/view/' . $id . '">Ver Compra</a>.';
            $mailer->send();
        }
        */

        return array('textStatus' => 'ok', 'closed_on' => $data['closed_on'], 'closed_by' => $session_user['last_name'].', '.$session_user['name']);

    }

    /*******
     * DEL *
     *******/
    public function del(\App $App)
    {
        $session_user = $App->session->get('user');
        $user_permission = array();
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_approve'] = 1;
        // Olivera, Matias
        if ($session_user['id'] == '4e31ab94dbe6d') $user_permission['can_close'] = 1;

        // id == hex number
        if (!($id = $App->request->fetch('id')) || !ctype_xdigit($id)) return array('fatal_error' => 'id_invalid');

        // fetch data from db
        $cc_purchaseModel = new CC_PurchaseModel($App->db->getPdo());
        if (!($cc_purchase = $cc_purchaseModel->getById($id))) return array('fatal_error' => 'id_not_found');

        // return data
        if (!$App->request->fetch('confirm')) return compact('cc_purchase');
        else {
            $errors = array();
            $textStatus = '';

            if (!$App->request->fetch('del_data')) {
                $session_user = $App->session->get('user');
                $cc_purchase['deleted'] = 1;
                $cc_purchase['deleted_on'] = date('Y-m-d H:i:s');
                $cc_purchase['deleted_by'] = $session_user['id'];

                // set 'deleted' = '1'
                if (!$cc_purchaseModel->softDel($cc_purchase)) $textStatus = "error";
                else $textStatus = "ok";
            }
            // delete from DB
            else {
                if (!$cc_purchaseModel->delByIds($id)) $textStatus = "error";
                else $textStatus = "ok";
            }

            return array('textStatus' => $textStatus);

        }
    }
}