<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 3/08/12
 * Time: 16:01
 */

namespace Jan_CC_Purchase;
use PDO;

class CC_PurchaseModel extends \DbDataMapper
{
    protected $_table_name = 'cc_purchase';

    public function search($search_data, $order_by = null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Purchase's
        $sql = 'SELECT cc_p.*,
                    cc_s.name AS cc_supplier_name,
                    CONCAT(opener.name, " ", opener.last_name) as opened_by_name,
                    CONCAT(closer.name, " ", closer.last_name) as closed_by_name
                FROM cc_purchase AS cc_p
                LEFT JOIN cc_supplier AS cc_s ON cc_s.id = cc_p.cc_supplier_id
                LEFT JOIN user AS opener ON opener.id = cc_p.opened_by
                LEFT JOIN user AS closer ON closer.id = cc_p.closed_by
                LEFT JOIN cc_purchase_product AS cc_purc_prod ON cc_purc_prod.cc_purchase_id = cc_p.id
                LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
                WHERE ' . $where['sql'] . '
                GROUP BY cc_p.id ' .
                ($order_by ? ' ORDER BY ' . $order_by : '') .
                ($limit ? ' LIMIT ' . $limit : '')
        ;

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        $cc_purchases = $stmt->fetchAll($this->_fetch_mode);

        // GET CC_Purchase_product's
        $sql = 'SELECT cc_prod.*,
                    cc_purc_prod.quantity,
                    cc_purc_prod.unit_price,
                    (cc_purc_prod.quantity*cc_purc_prod.unit_price) AS subtotal,
                    SUM( IF (cc_deliv.deleted="0" AND cc_deliv.closed_on IS NOT NULL, cc_deliv_purc_prod.quantity, 0) ) AS quantity_delivered,
                    SUM( IF (cc_deliv.deleted="0" AND cc_deliv.closed_on IS NOT NULL, cc_deliv_purc_prod.quantity*cc_purc_prod.unit_price, 0) ) AS total_price_delivered
                FROM cc_purchase_product AS cc_purc_prod
                LEFT JOIN cc_purchase AS cc_purc ON cc_purc.id = cc_purc_prod.cc_purchase_id
                LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
                LEFT JOIN cc_delivery_purchase_product AS cc_deliv_purc_prod ON cc_deliv_purc_prod.cc_purchase_product_id = cc_purc_prod.id
                LEFT JOIN cc_delivery AS cc_deliv ON cc_deliv.id = cc_deliv_purc_prod.cc_delivery_id
                WHERE cc_purc_prod.cc_purchase_id = ?
                GROUP BY cc_purc_prod.cc_product_id'
        ;

        $stmt = $this->prepare($sql);

        foreach ($cc_purchases as $i => $cc_p) {
            //Mustache compatible
            $cc_purchases[$i] = $this->purchaseToMustache($cc_purchases[$i]);

            $stmt->execute(array($cc_p['id']));
            $cc_purchases[$i]['products'] = $stmt->fetchAll($this->_fetch_mode);

            $total = 0;
            foreach ($cc_purchases[$i]['products'] as $j => $cc_p_p) {
                $total += intval($cc_p_p['quantity']) * $cc_p_p['unit_price'];

                //Mustache compatible
                $cc_purchases[$i]['products'][$j] = $this->productToMustache($cc_p_p);
            }
            $cc_purchases[$i]['_total'] = $total;
        }

        return $cc_purchases;
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);

        // GET CC_Purchase's
        $sql = 'SELECT COUNT(DISTINCT cc_p.id)
                FROM cc_purchase AS cc_p
                LEFT JOIN cc_supplier AS cc_s ON cc_s.id = cc_p.cc_supplier_id
                LEFT JOIN user AS opener ON opener.id = cc_p.opened_by
                LEFT JOIN user AS closer ON closer.id = cc_p.closed_by
                LEFT JOIN cc_purchase_product AS cc_purc_prod ON cc_purc_prod.cc_purchase_id = cc_p.id
                LEFT JOIN cc_product AS cc_prod ON cc_prod.id = cc_purc_prod.cc_product_id
                WHERE ' . $where['sql'];

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
        if ($row = $this->getByIds($id, null, 1)) return $row[0];
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

            $where .= ' AND cc_p.id IN ('.implode(',', array_fill(0, count($search_data['id']), '?')).')';
            $stmt_values = array_merge($stmt_values, $search_data['id']);
        }

        // search by order_num overrides dates
        if (isset($search_data['order_num'])) {
            $where .= ' AND cc_p.order_num LIKE ?';
            $stmt_values[] = '%'.$search_data['order_num'].'%';
        }

        if (isset($search_data['opened_on_from'])) {
            $stmt_values[] = $search_data['opened_on_from'];

            if (!isset($search_data['opened_on_to'])) $where .= 'AND cc_p.opened_on > ? ';
            else {
                $where .= ' AND cc_p.opened_on BETWEEN ? AND ? ';
                $stmt_values[] = $search_data['opened_on_to'];
            }
        }

        if (isset($search_data['closed_on_from'])) {
            $stmt_values[] = $search_data['closed_on_from'];

            if (!isset($search_data['closed_on_to'])) $where .= 'AND cc_p.closed_on > ? ';
            else {
                $where .= ' AND cc_p.closed_on BETWEEN ? AND ? ';
                $stmt_values[] = $search_data['closed_on_to'];
            }
        }

        if (isset($search_data['deleted'])) {
            $where .= ' AND cc_p.deleted=? ';
            $stmt_values[] = $search_data['deleted'];
        }

        if (isset($search_data['cc_product_id'])) {
            $where .= ' AND cc_purc_prod.cc_product_id= ? ';
            $stmt_values[] = $search_data['cc_product_id'];
        }
        elseif (isset($search_data['cc_product_name'])) {
            $where .= ' AND cc_prod.name LIKE ? ';
            $stmt_values[] = '%'.$search_data['cc_product_name'].'%';
        }

        if (isset($search_data['cc_supplier_id'])) {
            $where .= ' AND cc_p.cc_supplier_id= ? ';
            $stmt_values[] = $search_data['cc_supplier_id'];
        }
        elseif (isset($search_data['cc_supplier_name'])) {
            $where .= ' AND cc_s.name LIKE ?';
            $stmt_values[] = '%'.$search_data['cc_supplier_name'].'%';
        }

        return array('sql' => $where, 'values' => $stmt_values);
    }

    // Add CC_Purchase
    public function add($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // cc_purchase table
            $stmt_values = array($row['cc_supplier_id'], $row['opened_on'], $row['opened_by'], $row['closed_on'], $row['closed_by'], intval($row['order_num']), $row['comments']);

            $stmt = $this->prepare('INSERT INTO `cc_purchase` (`cc_supplier_id`, `opened_on`, `opened_by`, `closed_on`, `closed_by`, `order_num`, `comments`) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute($stmt_values);

            $row['id'] = $this->lastInsertId();

            //Add CC_Purchase_Products
            $stmt = $this->prepare('INSERT INTO `cc_purchase_product` (`cc_purchase_id`, `cc_product_id`, `quantity`, `unit_price`) VALUES (?,?,?,?) ');

            foreach ($row['products'] as $id => $cc_p) {
                if (!$cc_p['quantity']) continue;
                $stmt_values = array(
                    $row['id'],
                    $id,
                    $cc_p['quantity'],
                    $cc_p['unit_price']
                );

                $stmt->execute($stmt_values);
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

    public function mod($row) {
        $row = $this->sanitizeRowData($row);
        $this->beginTransaction();

        try {
            // update cc_purchase
            $stmt_values = array($row['cc_supplier_id'], $row['opened_on'], $row['opened_by'], $row['closed_on'], $row['closed_by'], $row['order_num'], $row['comments'], $row['deleted'], $row['id']);
            $stmt = $this->prepare('UPDATE `cc_purchase` SET `cc_supplier_id`=?, `opened_on`=?, `opened_by`=?, `closed_on`=?, `closed_by`=?, `order_num`=?, `comments`=?, `deleted`=? WHERE `id`=?');
            $stmt->execute($stmt_values);

            // remove products without quantity
            foreach ($row['products'] as $cc_product_id => $e) if (!$e['quantity']) unset($row['products'][$cc_product_id]);

            $row_cc_product_ids = array_keys($row['products']);

            // Delete products removed
            $stmt = $this->prepare('DELETE FROM `cc_purchase_product` WHERE `cc_purchase_id`= ? AND `cc_product_id` NOT IN ('.implode(',', array_fill(0, count($row_cc_product_ids), '?')).')');
            $stmt->execute(array_merge(array($row['id']), $row_cc_product_ids));

            // Get current_products from db - intersect with row[products]
            $stmt = $this->prepare('SELECT `cc_product_id` FROM `cc_purchase_product` WHERE `cc_purchase_id`= ? AND `cc_product_id` IN ('.implode(',', array_fill(0, count($row_cc_product_ids), '?')).')');
            $stmt->execute(array_merge(array($row['id']), $row_cc_product_ids));
            $db_cc_product_ids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            // Update current products
            $stmt = $this->prepare('UPDATE `cc_purchase_product` SET `quantity`=?, `unit_price`=? WHERE `cc_product_id`=? AND `cc_purchase_id`= ?');
            foreach ($db_cc_product_ids as $cc_product_id) {
                $stmt->execute(array($row['products'][$cc_product_id]['quantity'], $row['products'][$cc_product_id]['unit_price'], $cc_product_id, $row['id']));
            }

            // Add new products
            $stmt = $this->prepare('INSERT INTO `cc_purchase_product` (`cc_purchase_id`, `cc_product_id`, `quantity`, `unit_price`) VALUES (?,?,?,?) ');
            foreach ($row['products'] as $cc_product_id => $e) {
                // already in db || or no quantity
                if (in_array($cc_product_id, $db_cc_product_ids)) continue;

                $stmt->execute(array($row['id'], $cc_product_id, $e['quantity'], $e['unit_price']));
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
    public function softDel($cc_purchase)
    {
        $this->beginTransaction();

        try {
            // cc_purchase table
            $stmt = $this->prepare('UPDATE `cc_purchase` SET `deleted`="1", `deleted_on`=?, `deleted_by`=? WHERE `id`=?');
            $stmt_values = array($cc_purchase['deleted_on'], $cc_purchase['deleted_by'], $cc_purchase['id']);
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
            // cc_purchase table
            $stmt = $this->prepare('DELETE FROM `cc_purchase` WHERE `id` IN ('.implode(',', array_fill(0, count($ids), '?')).')');
            $stmt->execute($ids);

            // cc_product_purchase table
            $stmt = $this->prepare('DELETE FROM `cc_purchase_product` WHERE `cc_purchase_id` IN ('.implode(',', array_fill(0, count($ids), '?')).')');
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

        if (isset($search_data['order_num']) && is_numeric($search_data['order_num'])) $return['order_num'] = $search_data['order_num'];

        if (isset($search_data['deleted']) && is_numeric($search_data['deleted'])) $return['deleted'] = $search_data['deleted'];

        if (isset($search_data['cc_product_id']) && is_numeric($search_data['cc_product_id'])) $return['cc_product_id'] = $search_data['cc_product_id'];
        elseif (isset($search_data['cc_product_name']) && $search_data['cc_product_name']) $return['cc_product_name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['cc_product_name']);

        if (isset($search_data['cc_supplier_id']) && is_numeric($search_data['cc_supplier_id'])) $return['cc_supplier_id'] = $search_data['cc_supplier_id'];
        elseif (isset($search_data['cc_supplier_name']) && $search_data['cc_supplier_name']) $return['cc_supplier_name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['cc_supplier_name']);

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

        if (!isset($row['order_num']) || !is_numeric($row['order_num'])) $row['order_num'] = null;

        if (!isset($row['deleted'])) $row['deleted'] = 0;

        return $row;
    }

    /**
     * Parse rows to be Mustache compatible
     * @param array $rows
     * @return array
     */
    public function purchasesToMustache($rows)
    {
        foreach ($rows as &$row) {
            //Mustache compatible
            $row['opened_on'] = date('d/m/Y',strtotime($row['opened_on']));
            if ($row['closed_on']) $row['closed_on'] = date('d/m/Y',strtotime($row['closed_on']));
            $row['deleted'] = intval($row['deleted']);

            $row = sanitizeToJson($row);
       }
        return $rows;
    }

    public function purchaseToMustache($row)
    {
        $rows = $this->purchasesToMustache(array($row));
        return $rows[0];
    }

    public function productsToMustache($rows)
    {
        foreach ($rows as &$row) {
            //Mustache compatible
            $row = sanitizeToJson($row);
            $row['barcode_int'] = $row['deposit'].$row['family'].$row['item'].$row['brand'].$row['size'].$row['color'];
            $row['quantity'] = intval($row['quantity']);
            $row['quantity_delivered'] = intval($row['quantity_delivered']);
            if (!$row['measurement_unit']) $row['measurement_unit'] = '';
            else $row['measurement_unit'] = $row['measurement_unit'];
            
            $row['_image'] = file_exists('_pub/img/cc_product/'.$row['id'].'.png');
        }
        return $rows;
    }

    public function productToMustache($row)
    {
        $rows = $this->productsToMustache(array($row));
        return $rows[0];
    }

}