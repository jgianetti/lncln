<?php
namespace Jan_CC_Product;

class CC_ProductModel extends \DbDataMapper
{
    protected $_table_name = 'cc_product';

    public function search($search_data = null, $order_by =  null, $limit = null)
    {
        $where = $this->buildSearchWhere($search_data);
        
        $sql = 'SELECT cc_p.*,
                (
                    SELECT SUM(cc_p_p_2.quantity) as quantity
                    FROM cc_purchase_product AS cc_p_p_2
                    LEFT JOIN cc_purchase AS cc_p_2 ON cc_p_2.id = cc_p_p_2.cc_purchase_id
                    WHERE cc_p_2.deleted = "0" AND cc_p_2.closed_on IS NOT NULL AND cc_p_p_2.cc_product_id = cc_p.id
                    GROUP BY cc_p_p_2.cc_product_id
                ) AS quantity,
                (
                    SELECT SUM(cc_p_p_2.unit_price*cc_p_p_2.quantity) as total_price
                    FROM cc_purchase_product AS cc_p_p_2
                    LEFT JOIN cc_purchase AS cc_p_2 ON cc_p_2.id = cc_p_p_2.cc_purchase_id
                    WHERE cc_p_2.deleted = "0" AND cc_p_2.closed_on IS NOT NULL AND cc_p_p_2.cc_product_id = cc_p.id
                    GROUP BY cc_p_p_2.cc_product_id
                ) AS total_price,

                (
                    SELECT SUM(cc_d_p_p_2.quantity) as quantity_delivered
                    FROM cc_delivery_purchase_product AS cc_d_p_p_2
                    LEFT JOIN cc_purchase_product AS cc_p_p_2 ON cc_p_p_2.id = cc_d_p_p_2.cc_purchase_product_id
                    LEFT JOIN cc_delivery AS cc_d_2 ON cc_d_2.id = cc_d_p_p_2.cc_delivery_id
                    WHERE cc_d_2.deleted = "0" AND cc_d_2.closed_on IS NOT NULL AND cc_p_p_2.cc_product_id = cc_p.id
                    GROUP BY cc_p_p_2.cc_product_id
                ) AS quantity_delivered,

                (
                    SELECT SUM(cc_d_p_p_2.quantity*cc_p_p_2.unit_price) as total_price_delivered
                    FROM cc_delivery_purchase_product AS cc_d_p_p_2
                    LEFT JOIN cc_purchase_product AS cc_p_p_2 ON cc_p_p_2.id = cc_d_p_p_2.cc_purchase_product_id
                    LEFT JOIN cc_delivery AS cc_d_2 ON cc_d_2.id = cc_d_p_p_2.cc_delivery_id
                    WHERE cc_d_2.deleted = "0" AND cc_d_2.closed_on IS NOT NULL AND cc_p_p_2.cc_product_id = cc_p.id
                    GROUP BY cc_p_p_2.cc_product_id
                ) AS total_price_delivered
                FROM cc_product AS cc_p
                WHERE ' . $where["sql"] .
                'GROUP BY cc_p.id' .
                ($order_by ? ' ORDER BY ' . $order_by : '') .
                ($limit ? ' LIMIT ' . $limit : '')
        ;

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchAll($this->_fetch_mode);
    }

    public function searchCount($search_data = null)
    {
        $where = $this->buildSearchWhere($search_data);
        $sql = 'SELECT COUNT(id) ' .
            ' FROM cc_product AS cc_p ' .
            ' WHERE ' . $where['sql'];

        $stmt = $this->prepare($sql);
        $stmt->execute($where['values']);
        return $stmt->fetchColumn();
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
        
        if ($search_data['id']) {
            $where .= ' AND cc_p.id = ?';
            $stmt_values[] = $search_data['id'];
        }
        
        if ($search_data['name_barcode']) {
            $where .= ' AND cc_p.name LIKE ? OR CONCAT(cc_p.deposit,cc_p.family,cc_p.item,cc_p.brand,cc_p.size,cc_p.color) LIKE ? OR cc_p.barcode LIKE ? ';
            $stmt_values[] = '%'.$search_data['name_barcode'].'%';
            $stmt_values[] = '%'.$search_data['name_barcode'].'%';
            $stmt_values[] = '%'.$search_data['name_barcode'].'%';
        }
        else {
            if ($search_data['name']) {
                $where .= ' AND cc_p.name LIKE ? ';
                $stmt_values[] = '%'.$search_data['name'].'%';
            }
            if ($search_data['barcode_int']) {
                $where .= ' AND CONCAT(cc_p.deposit,cc_p.family,cc_p.item,cc_p.brand,cc_p.size,cc_p.color) LIKE ? ';
                $stmt_values[] = '%'.$search_data['barcode_int'].'%';
            }
            if ($search_data['barcode']) {
                $where .= ' AND cc_p.barcode LIKE ? ';
                $stmt_values[] = '%'.$search_data['barcode'].'%';
            }
        }
        if ($search_data['measurement_unit'])  {
            $where .= ' AND cc_p.measurement_unit LIKE ? ';
            $stmt_values[] = '%'.$search_data['measurement_unit'].'%';
        }
        if (!is_null($search_data['deleted'])) {
            $where .= ' AND cc_p.deleted=? ';
            $stmt_values[] = ''.$search_data['deleted'].'';
        }
        return array('sql'=>$where, 'values'=>$stmt_values);
    }

    public function getCCProductById($id)
    {
        if (!($cc_products = $this->search(array('id'=>$id), null, 1))) return array();
        return $cc_products[0];
    }

    public function sanitizeSearchData($search_data = null)
    {
        if (!$search_data) $search_data = Array();

        if (!isset($search_data['id'])) $search_data['id'] = null;
        if (!isset($search_data['name_barcode'])) $search_data['name_barcode'] = null;
        else $search_data['name_barcode'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['name_barcode']);

        if (!isset($search_data['name'])) $search_data['name'] = null;
        else $search_data['name'] = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $search_data['name']);

        if (!isset($search_data['barcode_int'])) $search_data['barcode_int'] = null;
        if (!isset($search_data['barcode'])) $search_data['barcode'] = null;
        if (!isset($search_data['measurement_unit'])) $search_data['measurement_unit'] = null;

        if (!isset($search_data['deleted']) || $search_data['deleted'] == "") $search_data['deleted'] =  null;

        return $search_data;
    }

    /**
     * Parse rows to be Mustache compatible
     * @param array $rows
     * @return array
     */
    public function rowsToMustache($rows)
    {
        foreach ($rows as &$row) {
            // Mustache compatible
            $row = sanitizeToJson($row);
            $row['barcode_int'] = $row['deposit'].$row['family'].$row['item'].$row['brand'].$row['size'].$row['color'];
            $row['stock']       = intval($row['quantity']) - intval($row['quantity_delivered']);
            $row['stock_price'] = intval($row['total_price']) - intval($row['total_price_delivered']);
            $row['deleted']     = intval($row['deleted']);
            $row['_image']      = file_exists(APP_ROOT . 'uploads/cc_product/_pub/'.$row['id'].'.png');
       }
        return $rows;
    }

    public function rowToMustache($row)
    {
        $rows = $this->rowsToMustache(array($row));
        return $rows[0];
    }

}