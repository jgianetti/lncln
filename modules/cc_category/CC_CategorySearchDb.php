<?php
namespace Jan_CC_Category;

class CC_CategorySearchDb
{
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }

    public function get($select = 'full', $search_data = null, $order_by = null, $limit = null)
    {
        if ($select == 'full') $select = '
            c.*,
            IF(lft = (rgt-1), 1, 0) AS _del,
            IF(id > 0, 1, 0) AS _mod
        ';

        $this->buildWhere($search_data)
            ->buildOrderBy($order_by);


        return $this->db->from('cc_category AS c')
            ->select($select)
            ->limit($limit)
            //->echoSql()->echoValues()
            ->prepareFetchAll();
    }

    public function getByIds($select, $id, $order_by = null, $limit = null)
    {
        return $this->get($select,['id'=>$id]);
    }

    public function getById($select, $id, $order_by = null)
    {
        if (!($rows = $this->getByIds($select, $id, $order_by ,1))) return [];
        else return $rows[0];
    }


    public function count($search_data = null)
    {
        $this->buildWhere($search_data);
        return $this->db->select('COUNT(c.id) AS cant')->from('cc_category AS c')->prepareFetchColumn();
    }

    public function buildWhere($search_data)
    {
        if (!empty($search_data['id'])) {
            if (!is_array($search_data['id'])) $search_data['id'] = [$search_data['id']];
            $this->db->where('c.id IN('.implode(',', array_fill(0, count($search_data['id']), '?')).')')->stmt_values($search_data['id']);
        }
        if (!empty($search_data['id_not']))     $this->db->where('c.id NOT IN('.implode(',', array_fill(0, count($search_data['id_not']), '?')).')')->stmt_values($search_data['id_not']);
        if (!empty($search_data['name']))       $this->db->where('c.name LIKE ?')->stmt_values('%'.$search_data['name'].'%');
        if (!empty($search_data['comments']))   $this->db->where('c.comments LIKE ?')->stmt_values('%'.$search_data['comments'].'%');
        return $this;
    }

    public function buildOrderBy($order_by = null) {
        if (!$order_by) $order_by = 'lft ASC'; // avoid space " " bug
        $order_by = explode(' ', $order_by);
        if (empty($order_by[1])) $order_by[1] = 'ASC';

        switch ($order_by[0]) {
            default: $this->db->orderBy($order_by[0] . ' ' . $order_by[1]);
        }
        return $this;
    }


    public function getParents($ids)
    {
        if (!$ids) return [];
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));

        return $this->db->prepareFetchAll('
            SELECT DISTINCT parent.id, parent.name, parent.lft, parent.rgt
            FROM category as node, category as parent
            WHERE (parent.lft < node.lft AND node.lft < parent.rgt)
                AND node.id IN('.implode(',', array_fill(0, count($ids), '?')).')
            ORDER BY parent.lft DESC
        ', $ids);
    }

    public function getParentsIds($ids)
    {
        $ret = [];
        foreach ($this->getParents($ids) as $e) $ret[] = $e['id'];
        return $ret;
    }

    public function getChildren($id)
    {
        if (!$id) return [];

        return $this->db->prepareFetchAll('
            SELECT child.id, child.name, child.lft, child.rgt
            FROM category as node, category as child
            WHERE (child.lft > node.lft AND child.lft < node.rgt)
            AND node.id = ?
            ORDER BY child.lft DESC
        ', [$id]);
    }

    public function getChildrenIds($id)
    {
        $ids = array();
        foreach ($this->getChildren($id) as $e) $ids[] = $e['id'];
        return $ids;
    }


    /**
     * Prepend $separator to indent a node according to it's parent
     * @param array $nodes
     * @param string $indent
     * @return array
     */
    public function indentNestedNames($nodes, $indent = ' &nbsp; &nbsp; ')
    {
        // indent according to parent--node
        $nested_set_rights = array();
        foreach ($nodes as &$e) {
            if (count($nested_set_rights)) while (count($nested_set_rights) && $e['rgt']>$nested_set_rights[count($nested_set_rights)-1]) array_pop($nested_set_rights);
            $e['name'] = str_repeat(' '.$indent.' ',count($nested_set_rights)) . $e['name'];
            $nested_set_rights[] = $e['rgt'];
        }

        return $nodes;
    }

}