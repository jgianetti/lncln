<?php
namespace Jan_Category;
use RepositoryDb,
    Db;

class CategoryRepositoryDb extends RepositoryDb
{
    public function __construct(Db $db)
    {
        parent::__construct($db,'category');
    }

    /**
     * @param array $row
     * @param int $parent_id
     * @return bool
     *
     */
    public function insertAt($row, $parent_id = 1)
    {
        $categorySearch = new CategorySearchDb($this->db);

        $parent = $categorySearch->getById('*', $parent_id);

        $this->db->beginTransaction();

        $this->db->prepareExec('UPDATE `category` SET `rgt` = `rgt` + 2 WHERE `rgt` >= ?', [$parent['rgt']]);
        $this->db->prepareExec('UPDATE `category` SET `lft` = `lft` + 2 WHERE `lft` > ?', [$parent['rgt']]);

        $row['id']  = uniqid();
        $row['lft'] = $parent['rgt'];
        $row['rgt'] = $parent['rgt']+1;
        $this->db->add('category', array_filter($row));

        $this->db->commit();

        return $row['id'];
    }

    /**
     * @param int $id
     * @return $this
     *
     */
    public function del($id)
    {
        $categorySearch = new CategorySearchDb($this->db);
        $row = $categorySearch->getById('*', $id);

        $this->db->beginTransaction();

        $this->db->prepareExec('UPDATE `category` SET `lft` = `lft` - 2 WHERE `lft` > ?', [$row['rgt']]);
        $this->db->prepareExec('UPDATE `category` SET `rgt` = `rgt` - 2 WHERE `rgt` >= ?', [$row['rgt']]);
        parent::del($id);

        $this->db->commit();

        return true;
    }
}