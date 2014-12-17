<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 3/08/12
 * Time: 16:01
 */

namespace Jan_CC_Delivery;

class CC_DeliveryModel extends \DbDataMapper
{
    protected $_table_name = 'cc_delivery';

    public function search($search_data, $order_by = null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Delivery's
        $sql = 'SELECT cc_d.*,
                    CONCAT(u.name, " ", u.last_name) AS user_name,
                    CONCAT(opener.name, " ", opener.last_name) as opened_by_name,
                    CONCAT(closer.name, " ", closer.last_name) as closed_by_name,
                    u.cc_cat_names AS cc_category_name
                FROM cc_delivery AS cc_d
                LEFT JOIN user AS u ON u.id = cc_d.user_id
                LEFT JOIN user AS opener ON opener.id = cc_d.opened_by
                LEFT JOIN user AS closer ON closer.id = cc_d.closed_by
                LEFT JOIN cc_delivery_purchase_product AS cc_deliv_purc_prod ON cc_deliv_purc_prod.cc_delivery_id = cc_d.id
                LEFT JOIN cc_purchase_product AS cc_purc_prod ON cc_purc_prod.id = cc_deliv_purc_prod.cc_purchase_product_id
                LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
                WHERE ' . $where['sql'] . '
                GROUP BY cc_d.id ' .
                ($order_by ? ' ORDER BY ' . $order_by : '') .
                ($limit ? ' LIMIT ' . $limit : '')
        ;

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        $cc_deliveries = $stmt->fetchAll($this->_fetch_mode);
        
        // GET CC_Delivery_product's
        $sql = 'SELECT cc_prod.*, 
                    cc_deliv_purc_prod.quantity AS quantity,
                    cc_purc_prod.unit_price AS unit_price,
                    (cc_deliv_purc_prod.quantity*cc_purc_prod.unit_price) AS subtotal,
                    cc_purc_prod.cc_purchase_id AS cc_purchase_id
                FROM cc_delivery_purchase_product AS cc_deliv_purc_prod
                LEFT JOIN cc_purchase_product AS cc_purc_prod ON cc_purc_prod.id = cc_deliv_purc_prod.cc_purchase_product_id
                LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
                WHERE cc_deliv_purc_prod.cc_delivery_id = ?'
        ;

        $stmt = $this->prepare($sql);

        foreach ($cc_deliveries as $i => $cc_d) {
            //Mustache compatible
            $cc_deliveries[$i] = $this->deliveryToMustache($cc_deliveries[$i]);

            $stmt->execute(array($cc_d['id']));
            $cc_deliveries[$i]['products'] = $stmt->fetchAll($this->_fetch_mode);

            $total = 0;
            foreach ($cc_deliveries[$i]['products'] as $j => $cc_p_p) {
                // Mustache compatible
                $cc_deliveries[$i]['products'][$j] = $this->productToMustache($cc_p_p);

                $total += intval($cc_p_p['quantity']) * $cc_p_p['unit_price'];
            }
            $cc_deliveries[$i]['_total'] = $total;
        }

        return $cc_deliveries;
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Delivery's
        $sql = 'SELECT COUNT(DISTINCT cc_d.id)
            FROM cc_delivery AS cc_d
            LEFT JOIN user AS u ON u.id = cc_d.user_id
            LEFT JOIN user AS opener ON opener.id = cc_d.opened_by
            LEFT JOIN user AS closer ON closer.id = cc_d.closed_by
            LEFT JOIN cc_delivery_purchase_product AS cc_deliv_purc_prod ON cc_deliv_purc_prod.cc_delivery_id = cc_d.id
            LEFT JOIN cc_purchase_product AS cc_purc_prod ON cc_purc_prod.id = cc_deliv_purc_prod.cc_purchase_product_id
            LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
            WHERE ' . $where['sql']
        ;

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchColumn();
    }
    
    public function getByIds($ids, $order_by = null, $limit = null)
    {
        return $this->search(array('id'=>$ids), $order_by, $limit);
    }

    public function getById($id)
    {
        if (($row = $this->getByIds($id, null, 1))) return $row[0];
        else return array();
    }


    /**
     * @param null $search_data
     * @return array : [where]=Where clause;[values]=stmt_values
     */
    public function buildSearchWhere($search_data = null)
    {
        $search_data = $this->sanitizeSearchData($search_data);
        $stmt_values = array();
        $where = ' 1=1 ';

        if (isset($search_data['id'])) {
            // allows to search among multiple ids
            if (!is_array($search_data['id'])) $search_data['id'] = explode(",", str_replace(' ', '', $search_data['id']));

            $where .= ' AND cc_d.id IN (' . implode(',', array_fill(0, count($search_data['id']), '?')) . ')';
            $stmt_values = array_merge($stmt_values, $search_data['id']);
        }

        if (isset($search_data['opened_on_from'])) {
            $stmt_values[] = $search_data['opened_on_from'];

            if (!isset($search_data['opened_on_to'])) $where .= 'AND cc_d.opened_on > ? ';
            else {
                $where .= ' AND cc_d.opened_on BETWEEN ? AND ? ';
                $stmt_values[] = $search_data['opened_on_to'];
            }
        }

        if (isset($search_data['closed_on_from'])) {
            $stmt_values[] = $search_data['closed_on_from'];

            if (!isset($search_data['closed_on_to'])) $where .= 'AND cc_d.closed_on > ? ';
            else {
                $where .= ' AND cc_d.closed_on BETWEEN ? AND ? ';
                $stmt_values[] = $search_data['closed_on_to'];
            }
        }

        if (isset($search_data['user_id'])) {
            $where .= ' AND u.id = ? ';
            $stmt_values[] = $search_data['user_id'];
        }
        elseif (isset($search_data['user_name'])) {
            $where .= ' AND CONCAT(u.name, " ",u.last_name) LIKE ?';
            $stmt_values[] = '%'.$search_data['user_name'].'%';
        }

        if (isset($search_data['cc_category_id'])) {
            $where .= ' AND ( FIND_IN_SET(?, u.cc_cat_ids) ' . str_repeat(' OR FIND_IN_SET(?, u.cc_cat_ids) ', count($search_data['cc_category_id'])-1) . ' ) ';
            $stmt_values = array_merge($stmt_values,$search_data['cc_category_id']);
        }

        if (isset($search_data['cc_product_id'])) {
            $where .= ' AND cc_purc_prod.cc_product_id = ? ';
            $stmt_values[] = $search_data['cc_product_id'];
        }
        elseif (isset($search_data['cc_product_name'])) {
            $where .= ' AND cc_prod.name LIKE ? ';
            $stmt_values[] = '%'.$search_data['cc_product_name'].'%';
        }

        if (isset($search_data['deleted'])) {
            $where .= ' AND cc_d.deleted = ? ';
            $stmt_values[] = $search_data['deleted'];
        }

        return array('sql' => $where, 'values' => $stmt_values);
    }

    public function add($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // cc_delivery table
            $stmt_values = array($row['user_id'], $row['opened_on'], $row['opened_by'], $row['closed_on'], $row['closed_by'], $row['comments']);

            $stmt = $this->prepare('INSERT INTO `cc_delivery` (`user_id`, `opened_on`, `opened_by`, `closed_on`, `closed_by`, `comments`) VALUES (?,?,?,?,?,?)');
            $stmt->execute($stmt_values);

            $row['id'] = $this->lastInsertId();

            // SQL to get CC_Purchase_products
            // deliver in order from oldest to newest
            $sql_cc_purc_prod = '
                SELECT `cc_purc_prod`.*,
                    IFNULL((
                        SELECT SUM(`cc_d_p_p`.`quantity`)
                        FROM `cc_delivery_purchase_product` AS `cc_d_p_p`
                        WHERE `cc_d_p_p`.`cc_purchase_product_id` = `cc_purc_prod`.`id`
                        GROUP BY `cc_purc_prod`.`id`
                    ),0) as quantity_delivered
                FROM `cc_purchase_product` AS `cc_purc_prod`
                LEFT JOIN `cc_purchase` AS `cc_purc` ON `cc_purc_prod`.`cc_purchase_id` = `cc_purc`.`id`
                LEFT JOIN `cc_product` AS `cc_prod` ON `cc_purc_prod`.`cc_product_id` = `cc_prod`.`id`
                WHERE
                    `cc_purc`.`closed_on` IS NOT NULL
                    AND `cc_purc_prod`.`cc_product_id` = ?
                HAVING (`cc_purc_prod`.`quantity` - quantity_delivered) > 0
                ORDER BY `cc_purc`.`closed_on`
            ';
            $stmt_cc_purc_prod = $this->prepare($sql_cc_purc_prod);

            // SQL to add CC_Delivery_purchase_products
            $stmt_add_cc_deliv_purc_prod = $this->prepare('INSERT INTO `cc_delivery_purchase_product` (`cc_delivery_id`, `cc_purchase_product_id`, `quantity`) VALUES (?,?,?)');

            // for each product to deliver
            foreach ($row['products'] as $id => $cc_prod) {
                $to_deliver = $cc_prod['quantity'];

                // get CC_Purchase_products
                $stmt_cc_purc_prod->execute(array($id));
                $purchase_products = $stmt_cc_purc_prod->fetchAll($this->_fetch_mode);

                // for each CC_Purchase_product
                foreach ($purchase_products as $j => $cc_purc_prod) {
                    $cc_purc_prod_remain = $cc_purc_prod['quantity'] - $cc_purc_prod['quantity_delivered'];

                    // can be all delivered
                    if ($cc_purc_prod_remain >= $to_deliver) {
                        // Deliver all
                        $stmt_add_cc_deliv_purc_prod->execute(array($row['id'], $cc_purc_prod['id'], $to_deliver));
                        // all delivered
                        break;
                    }
                    // deliver max and continue
                    else {
                        // Deliver remaining
                        $stmt_add_cc_deliv_purc_prod->execute(array($row['id'], $cc_purc_prod['id'], $cc_purc_prod_remain));
                        $to_deliver -= $cc_purc_prod_remain;
                    }
                }
            }
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            $this->rollBack();
            return false;
        }

        $this->commit();
        return true;
    }

    // TODO
    public function mod($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // update cc_delivery
            $stmt_values = array($row['user_id'], $row['opened_on'], $row['opened_by'], $row['closed_on'], $row['closed_by'], $row['comments'], $row['deleted'], $row['id']);
            $stmt = $this->prepare('UPDATE `cc_delivery` SET `user_id`=?, `opened_on`=?, `opened_by`=?, `closed_on`=?, `closed_by`=?, `comments`=?, `deleted`=? WHERE `id`=?');
            $stmt->execute($stmt_values);

            // remove delivery_products without quantity
            foreach ($row['products'] as $cc_product_id => $e) if (!$e['quantity']) unset($row['products'][$cc_product_id]);


            // SQL to get CC_Purchase_products
            // deliver in order from oldest to newest
            $sql_cc_purc_prod = 'SELECT `cc_purc_prod`.*, IFNULL(('.$this->_sql_quantity_delivered .'),0) as quantity_delivered ' .
                ' FROM `cc_purchase_product` AS `cc_purc_prod` ' .
                ' LEFT JOIN `cc_purchase` AS `cc_purc` ON `cc_purc_prod`.`cc_purchase_id` = `cc_purc`.`id` ' .
                ' LEFT JOIN `cc_product` AS `cc_prod` ON `cc_purc_prod`.`cc_product_id` = `cc_prod`.`id` ' .
                ' WHERE ' .
                ' `cc_purc`.`closed_on` IS NOT NULL ' .
                ' AND `cc_purc_prod`.`cc_product_id` = ? ' .
                ' HAVING (`cc_purc_prod`.`quantity` - quantity_delivered) > 0 ' .
                ' ORDER BY `cc_purc`.`closed_on`';
            $stmt_cc_purc_prod = $this->prepare($sql_cc_purc_prod);

            // SQL to add CC_Delivery_purchase_products
            $stmt_add_cc_deliv_purc_prod = $this->prepare('INSERT INTO `cc_delivery_purchase_product` (`cc_delivery_id`, `cc_purchase_product_id`, `quantity`) VALUES (?,?,?)');

            // for each product to deliver
            foreach ($row['products'] as $id => $cc_prod) {
                $to_deliver = $cc_prod['quantity'];

                // get CC_Purchase_products
                $stmt_cc_purc_prod->execute(array($id));
                $purchase_products = $stmt_cc_purc_prod->fetchAll($this->_fetch_mode);

                // for each CC_Purchase_product
                foreach ($purchase_products as $j => $cc_purc_prod) {
                    $cc_purc_prod_remain = $cc_purc_prod['quantity'] - $cc_purc_prod['quantity_delivered'];

                    // can be all delivered
                    if ($cc_purc_prod_remain >= $to_deliver) {
                        // Deliver all
                        $stmt_add_cc_deliv_purc_prod->execute(array($row['id'], $cc_purc_prod['id'], $to_deliver));
                        // all delivered
                        break;
                    }
                    // deliver max and continue
                    else {
                        // Deliver remaining
                        $stmt_add_cc_deliv_purc_prod->execute(array($row['id'], $cc_purc_prod['id'], $cc_purc_prod_remain));
                        $to_deliver -= $cc_purc_prod_remain;
                    }
                }
            }
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            $this->rollBack();
            return false;
        }

        $this->commit();
        return true;
    }


    // set 'deleted' = '1'
    public function softDel($cc_delivery)
    {
        $this->beginTransaction();

        try {
            // cc_delivery table
            $stmt = $this->prepare('UPDATE `cc_delivery` SET `deleted`="1", `deleted_on`=?, `deleted_by`=? WHERE `id`=?');
            $stmt_values = array($cc_delivery['deleted_on'], $cc_delivery['deleted_by'], $cc_delivery['id']);
            $stmt->execute($stmt_values);
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            $this->rollBack();
            return false;
        }
        $this->commit();
        return true;
    }

    public function delByIds($ids)
    {
        if (!is_array($ids)) $ids = explode(",", str_replace(' ', '', $ids));
        $this->beginTransaction();

        try {
            // cc_delivery table
            $stmt = $this->prepare('DELETE FROM `cc_delivery` WHERE `id` IN ('.implode(',', array_fill(0, count($ids), '?')).')');
            $stmt->execute($ids);

            // cc_delivery_product_purchase table
            $stmt = $this->prepare('DELETE FROM `cc_delivery_purchase_product` WHERE `cc_delivery_id` IN ('.implode(',', array_fill(0, count($ids), '?')).')');
            $stmt->execute($ids);

        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            $this->rollBack();
            return false;
        }

        $this->commit();
        return true;
    }

    // set 'deleted' = '0'
    public function undel($cc_delivery)
    {
        $this->beginTransaction();

        try {
            // cc_delivery table
            $stmt = $this->prepare('UPDATE `cc_delivery` SET `deleted`="0" WHERE `id`=?');
            $stmt_values = array($cc_delivery['id']);
            $stmt->execute($stmt_values);
        }
        catch (\PDOException $e) {
            //echo $e->getMessage();
            $this->rollBack();
            return false;
        }
        $this->commit();
        return true;
    }


    public function sanitizeSearchData($search_data = null)
    {
        if (!$search_data) return array();
        $return = array();
        
        if (isset($search_data['id'])) $return['id'] = $search_data['id'];

        if (isset($search_data['opened_on']) && $search_data['opened_on'] && $search_data['opened_on'] != '~') {
            $tmp = explode('~', $search_data['opened_on']);
            $search_data['opened_on_from'] = $tmp[0];
            if (isset($tmp[1])) $search_data['opened_on_to'] = $tmp[1];
        }
        if (isset($search_data['closed_on']) && $search_data['closed_on'] && $search_data['closed_on'] != '~') {
            $tmp = explode('~', $search_data['closed_on']);
            $search_data['closed_on_from'] = $tmp[0];
            if (isset($tmp[1])) $search_data['closed_on_to'] = $tmp[1];
        }

        // opened_on_from
        if (isset($search_data['opened_on_from'])) {
            $search_data['opened_on_from'] = str_replace('/','-',$search_data['opened_on_from']);
            if (!strtotime($search_data['opened_on_from'])) $search_data['opened_on_from'] = date('Y-m-d', strtotime('-1 month'));
            // year only = year-01-01
            elseif (is_numeric($search_data['opened_on_from'])) $search_data['opened_on_from'] = date('Y-m-d', strtotime($search_data['opened_on_from'].'-01-01'));
            else $search_data['opened_on_from'] = date('Y-m-d', strtotime($search_data['opened_on_from']));

            $return['opened_on_from'] = $search_data['opened_on_from'];

            // opened_on_to
            if (isset($search_data['opened_on_to'])) {
                $search_data['opened_on_to'] = str_replace('/','-',$search_data['opened_on_to']);
                if (!strtotime($search_data['opened_on_to'])) $search_data['opened_on_to'] = date('Y-m-d', strtotime('+1 day'));
                // year only = year-01-01
                elseif (is_numeric($search_data['opened_on_to'])) $search_data['opened_on_to'] = date('Y-m-d', strtotime($search_data['opened_on_to'].'-01-01'));
                else $search_data['opened_on_to'] = date('Y-m-d', strtotime($search_data['opened_on_to']));

                $return['opened_on_to'] = $search_data['opened_on_to'];
            }
        }

        // closed_on_from
        if (isset($search_data['closed_on_from'])) {
            $search_data['closed_on_from'] = str_replace('/','-',$search_data['closed_on_from']);
            if (!strtotime($search_data['closed_on_from'])) $search_data['closed_on_from'] = date('Y-m-d', strtotime('+1 day'));
            // year only = year-01-01
            elseif (is_numeric($search_data['closed_on_from'])) $search_data['closed_on_from'] = date('Y-m-d', strtotime($search_data['closed_on_from'].'-01-01'));
            else $search_data['closed_on_from'] = date('Y-m-d', strtotime($search_data['closed_on_from']));

            $return['closed_on_from'] = $search_data['closed_on_from'];

            // closed_on_to
            if (isset($search_data['closed_on_to'])) {
                $search_data['closed_on_to'] = str_replace('/','-',$search_data['closed_on_to']);
                if (!strtotime($search_data['closed_on_to'])) $search_data['closed_on_to'] = date('Y-m-d', strtotime('+1 day'));
                // year only = year-01-01
                elseif (is_numeric($search_data['closed_on_to'])) $search_data['closed_on_to'] = date('Y-m-d', strtotime($search_data['closed_on_to'].'-01-01'));
                else $search_data['closed_on_to'] = date('Y-m-d', strtotime($search_data['closed_on_to']));

                $return['closed_on_to'] = $search_data['closed_on_to'];
            }
        }

        if (isset($search_data['user_id']) && ctype_xdigit($search_data['user_id'])) $return['user_id'] = $search_data['user_id'];
        elseif (isset($search_data['user_name']) && $search_data['user_name']) $return['user_name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['user_name']);

        if (!empty($search_data['cc_category'])) $return['cc_category_id'] = is_array($search_data['cc_category']) ? $search_data['cc_category'] : array($search_data['cc_category']);
        elseif (!empty($search_data['cc_category_id'])) $return['cc_category_id'] = is_array($search_data['cc_category_id']) ? $search_data['cc_category_id'] : array($search_data['cc_category_id']);

        if (isset($search_data['cc_product_id']) && is_numeric($search_data['cc_product_id'])) $return['cc_product_id'] = $search_data['cc_product_id'];
        elseif (isset($search_data['cc_product_name']) && $search_data['cc_product_name']) $return['cc_product_name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['cc_product_name']);

        if (isset($search_data['deleted']) && is_numeric($search_data['deleted'])) $return['deleted'] = $search_data['deleted'];

        return $return;
    }

    public function sanitizeRowData($row = null)
    {
        if (!$row) $row = array();

        // opened_on - defaults to today
        if (!isset($row['opened_on'])) $row['opened_on'] = date('Y-m-d H:i:s');
        else {
            $row['opened_on'] = str_replace('/','-',$row['opened_on']);
            if (!strtotime($row['opened_on'])) $row['opened_on'] = date('Y-m-d H:i:s');
            // year only = year-01-01
            elseif (is_numeric($row['opened_on'])) $row['opened_on'] = date('Y-m-d H:i:s', strtotime($row['opened_on'].'-01-01 '.date('H:i:s')));
            else $row['opened_on'] = date('Y-m-d H:i:s', strtotime($row['opened_on'].' '.date('H:i:s')));
        }

        // closed_on - defaults to null
        if (!isset($row['closed_on'])) $row['closed_on'] = null;
        else {
            $row['closed_on'] = str_replace('/','-',$row['closed_on']);
            if (!strtotime($row['closed_on'])) $row['closed_on'] = null;
            // year only = year-01-01
            elseif (is_numeric($row['closed_on'])) $row['closed_on'] = date('Y-m-d H:i:s', strtotime($row['closed_on'].'-01-01 '.date('H:i:s')));
            else $row['closed_on'] = date('Y-m-d H:i:s', strtotime($row['closed_on'].' '.date('H:i:s')));
        }

        if (!isset($search_data['deleted'])) $search_data['deleted'] = 0;

        return $row;

    }

    /**
     * Parse rows to be Mustache compatible
     * @param array $rows
     * @return array
     */
    public function deliveriesToMustache($rows)
    {
        foreach ($rows as &$row) {
            //Mustache compatible
            $row = sanitizeToJson($row);
            $row['opened_on'] = date('d/m/Y',strtotime($row['opened_on']));
            if (!$row['closed_on']) { $row['closed_on'] = ' '; $row['closed_by_name'] = ' '; }
            else $row['closed_on'] = date('d/m/Y',strtotime($row['closed_on']));
            $row['deleted'] = intval($row['deleted']);
        }
        return $rows;
    }

    public function deliveryToMustache($row)
    {
        $rows = $this->deliveriesToMustache(array($row));
        return $rows[0];
    }

    public function productsToMustache($rows)
    {
        foreach ($rows as &$row) {
            //Mustache compatible
            $row = sanitizeToJson($row);
            if (!$row['measurement_unit']) $row['measurement_unit'] = ' ';
            else $row['measurement_unit'] = '('.$row['measurement_unit'].')';
        }
        return $rows;
    }

    public function productToMustache($row)
    {
        $rows = $this->productsToMustache(array($row));
        return $rows[0];
    }
}
