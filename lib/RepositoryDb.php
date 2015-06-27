<?php
class RepositoryDb
{
    /**
     * @var Db
     */
    protected $db;

    /**
     * @var string
     */
    protected $table;

    /**
     * @param Db $db
     * @param string $table
     */
    public function __construct(\Db $db, $table = null)
    {
        $this->db = $db;
        if ($table) $this->table = $table;
    }

    public function save(array $row)
    {
        // UPDATE
        if (!empty($row['id'])) return $this->db->mod($this->table, $row);
        // INSERT
        else return $this->db->add($this->table, array_filter($row));
    }

    /**
     * Check if column[value] already exists in DB for a different $id
     *
     * @param array $col_values
     * @param string $id
     * @return array
     */
    public function duplicatedData($col_values, $id = null)
    {
        return $this->db->duplicatedData($this->table, $col_values, $id);
    }


    public function softDel($ids, $deleted_by)
    {
        return $this->db->softDel($this->table, $ids, $deleted_by);
    }

    public function del($id)
    {
        return $this->db->del($this->table, $id);
    }
}