<?php
class TreeModelDb extends RepositoryDb
{
    public function getParents($ids, $id_only = false)
    {
        if (!$ids) return [];
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));

        $sql = '
          SELECT DISTINCT parent.id, parent.name, parent.lft, parent.rgt
          FROM '.$this->table.' as node
          LEFT JOIN '.$this->table.' as parent
          WHERE (parent.lft < node.lft AND node.lft < parent.rgt)
              AND node.id IN('.implode(',', array_fill(0, count($ids), '?')).')
          ORDER BY parent.lft DESC
        ';

        if (!$id_only) return $this->db->prepareFetchAll($sql, $ids);
        return $this->db->prepareFetchColumn($sql, $ids);
    }

    public function getParentsIds($ids)
    {
        $ret = [];
        foreach ($this->getParents($ids) as $row) $ret[] = $row['id'];
        return $ret;
    }

    public function getChildren($id)
    {
        if (!$id) return [];

        $sql = '
            SELECT child.id, child.name, child.lft, child.rgt
            FROM '.$this->table.' as node
            LEFT JOIN '.$this->table.' as child
            WHERE (child.lft > node.lft AND child.lft < node.rgt)
            AND node.id = ?
            ORDER BY child.lft DESC
        ';

        return $this->db->prepareFetchAll($sql, [$id]);
    }

    public function getChildrenIds($id)
    {
        $ids = array();
        foreach ($this->getChildren($id) as $row) $ids[] = $row['id'];
        return $ids;
    }


    /**
     * Prepend $separator to indent a node according to it's parent
     * @param array $tree
     * @param string $indent
     * @return array
     */
    public function indentNestedNames($tree, $indent = ' &nbsp; &nbsp; ', $name_col = 'name')
    {
        // indent according to parent--node
        $nested_set_rights = array();
        foreach ($tree as &$row) {
            if (count($nested_set_rights)) while (count($nested_set_rights) && $row['rgt']>$nested_set_rights[count($nested_set_rights)-1]) array_pop($nested_set_rights);
            $row[$name_col] = str_repeat(' '.$indent.' ',count($nested_set_rights)).utf8_encode($row[$name_col]);
            $nested_set_rights[] = $row['rgt'];
        }

        return $tree;
    }

    /**
     * @param int $parent_rgt
     * @param array $row
     * @return bool
     */
    public function makeRoom($parent_rgt, $row)
    {
        $this->beginTransaction();

        try {
            // push parent's right and it's right siblings to the right
            $this->db->prepareExec('UPDATE `role` SET `rgt` = `rgt` + 2 WHERE `rgt` >= ?', $parent_rgt);
            $this->db->prepareExec('UPDATE `role` SET `lft` = `lft` + 2 WHERE `lft` > ?', $parent_rgt);

            // INSERT branch
            $row['lft'] = $parent_rgt;
            $row['rgt'] = $parent_rgt+1;

            $this->save($row);

            $this->commit();
            return true;
        }
        catch (\PDOException $row) {
            $this->rollBack();
            return false;
        }
    }

    /**
     * Del a sub-role
     *
     * @param string $id
     * @return bool
     */
    public function delRole($id)
    {
        if (!($role = $this->getById($id))) return false;

        $this->beginTransaction();
        try {
            // push parent's right and it's right siblings to the left
            $stmt_values = array($role['rgt']);
            $stmt = $this->prepare('UPDATE `role` SET `lft` = `lft` - 2 WHERE `lft` > ?');
            $stmt->execute($stmt_values);

            $stmt_values = array($role['rgt']);
            $stmt = $this->prepare('UPDATE `role` SET `rgt` = `rgt` - 2 WHERE `rgt` >= ?');
            $stmt->execute($stmt_values);

            // DELETE role
            $stmt_values = array($id);
            $stmt = $this->prepare('DELETE FROM `role` WHERE `id`= ? LIMIT 1');
            $stmt->execute($stmt_values);

            $this->commit();
            return true;
        }
        catch (\PDOException $row) {
            $this->rollBack();
            return false;
        }
    }

}
