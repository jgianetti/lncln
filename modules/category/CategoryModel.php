<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 3/08/12
 * Time: 16:01
 */

namespace Jan_Category;

class CategoryModel extends \DbDataMapper
{
    protected $_table_name = 'category';

    public function get($columns = '*', $where = null, $order_by =  null, $limit = null, $table_name = null)
    {
        return parent::get($columns, $where, ($order_by ? $order_by : '`lft` ASC'), $limit, $table_name);
    }

    public function getParents($ids)
    {
        if (!$ids) return array();
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));

        $sql = 'SELECT DISTINCT parent.id, parent.name, parent.lft, parent.rgt
                FROM category as node, category as parent
                WHERE (parent.lft < node.lft AND node.lft < parent.rgt)
                    AND node.id IN('.implode(',', array_fill(0, count($ids), '?')).')
                ORDER BY parent.lft DESC
        ';

        $stmt = $this->prepare($sql);
        $stmt->execute($ids);

        return $stmt->fetchAll($this->_fetch_mode);
    }

    public function getParentsIds($ids)
    {
        $ret = array();
        foreach ($this->getParents($ids) as $e) $ret[] = $e['id'];
        return $ret;
    }

    public function getChildren($id)
    {
        if (!$id) return array();

        $sql = 'SELECT child.id, child.name, child.lft, child.rgt' .
            ' FROM category as node, category as child' .
            ' WHERE (child.lft > node.lft AND child.lft < node.rgt)' .
            ' AND node.id = ?' .
            ' ORDER BY child.lft DESC'
        ;

        $stmt_values = array($id);
        $stmt = $this->prepare($sql);
        $stmt->execute($stmt_values);

        return $stmt->fetchAll($this->_fetch_mode);
    }

    public function getChildrenIds($id)
    {
        $ids = array();
        foreach ($this->getChildren($id) as $e) $ids[] = $e['id'];
        return $ids;
    }


    /**
     * Prepend $separator to indent a node according to it's parent
     * @param array $categories
     * @param string $indent
     * @return array
     */
    public function indentNestedNames($categories, $indent = ' &nbsp; &nbsp; ')
    {
        // indent according to parent--node
        $nested_set_rights = array();
        foreach ($categories as &$e) {
            if (count($nested_set_rights)) while (count($nested_set_rights) && $e['rgt']>$nested_set_rights[count($nested_set_rights)-1]) array_pop($nested_set_rights);
            $e['name'] = str_repeat(' '.$indent.' ',count($nested_set_rights)).utf8_encode($e['name']);
            $nested_set_rights[] = $e['rgt'];
        }

        return $categories;
    }

    /**
     * Add a sub-category
     *
     * @param array $row [parent_id, name]
     * @return bool
     */
    public function addCategory($row)
    {
        if (!($category_parent = $this->getById($row['id_parent']))) return false;

        $this->beginTransaction();

        try {
            // push parent's right and it's right siblings to the right
            $stmt_values = array($category_parent['rgt']);
            $stmt = $this->prepare('UPDATE `category` SET `rgt` = `rgt` + 2 WHERE `rgt` >= ?');
            $stmt->execute($stmt_values);

            $stmt_values = array($category_parent['rgt']);
            $stmt = $this->prepare('UPDATE `category` SET `lft` = `lft` + 2 WHERE `lft` > ?');
            $stmt->execute($stmt_values);

            // INSERT category
            $stmt_values = array($row['id'], $category_parent['rgt'], ($category_parent['rgt']+1), $row['name'], $row['comments']);
            $stmt = $this->prepare('INSERT INTO `category` (`id`, `lft`, `rgt`, `name`, `comments`) VALUES (?,?,?,?,?)');
            $stmt->execute($stmt_values);

            $this->commit();
            return true;
        }
        catch (\PDOException $e) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Del a sub-category
     *
     * @param string $id
     * @return bool
     */
    public function delCategory($id)
    {
        if (!($category = $this->getById($id))) return false;

        $this->beginTransaction();
        try {
            // push parent's right and it's right siblings to the left
            $stmt_values = array($category['rgt']);
            $stmt = $this->prepare('UPDATE `category` SET `lft` = `lft` - 2 WHERE `lft` > ?');
            $stmt->execute($stmt_values);

            $stmt_values = array($category['rgt']);
            $stmt = $this->prepare('UPDATE `category` SET `rgt` = `rgt` - 2 WHERE `rgt` >= ?');
            $stmt->execute($stmt_values);

            // DELETE category
            $stmt_values = array($id);
            $stmt = $this->prepare('DELETE FROM `category` WHERE `id`= ? LIMIT 1');
            $stmt->execute($stmt_values);

            $this->commit();
            return true;
        }
        catch (\PDOException $e) {
            $this->rollBack();
            return false;
        }
    }

}
