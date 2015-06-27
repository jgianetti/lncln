<?php
class Db extends SqlBuilder
{
    /** @var PDO */
    protected $_pdo;

    /** @var int */
    protected $_fetch_mode = PDO::FETCH_ASSOC;

    /** @var int */
    protected $_err_mode = PDO::ERRMODE_EXCEPTION;


    /**
     * @param PDO $pdo
     * @param array $options
     */
    public function __construct(PDO $pdo, array $options = null)
    {
        $this->setPdo($pdo);
        if (!$options) $options = [];
        if (empty($options['err_mode'])) $options['err_mode'] = $this->_err_mode;
        $this->setOptions($options);
    }


    /******************
     * Public Methods *
     ******************/


    /**
     * Fetch rows from Db using filters (assoc array)
     * filters : [field] = string - comma separated OR
     * filters : [field] = [value,value,...]
     *
     * @param string|array string $select
     * @param string $table
     * @param array $filters
     * @param string null $order_by
     * @param string null $limit
     * @return array
     */
    public function getBy($select, $table, $filters, $order_by = null, $limit = null)
    {
        return $this->select($select)->from($table)->where($filters)->orderBy($order_by)->limit($limit)->prepareFetchAll();
    }

    /**
     * Fetch rows from Db using Ids [table_name.id]
     *
     * @param string|array|null $select
     * @param string $table
     * @param string|array $ids
     * @param string|null $order_by
     * @return array
     */
    public function getByIds($select, $table, $ids, $order_by = null)
    {
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));
        return $this->getBy($select, $table, ['id' => $ids], $order_by, count($ids));
    }

    /**
     * Fetch row data from database using Id [table_name.id]
     *
     * @param string|array|null $select
     * @param string $table
     * @param string $id
     * @return array
     */
    public function getById($select, $table, $id)
    {
        if (!($row = $this->getByIds($select, $table, $id))) return [];
        return $row[0];
    }

    /**
     * @param string $table
     * @param array $row
     * @return bool|string
     */
    public function add($table, array $row)
    {
        if (!$this->insertInto($table)->set($row)->prepareExec()) return false;
        if (!isset($row['id'])) return $this->lastInsertId();
        return $row['id'];
    }

    /**
     * @param string $table
     * @param array $row
     * @param string|array $ids
     * @return bool|int
     */
    public function modByIds($table, array $row, $ids)
    {
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));
        return $this->update($table)
            ->set($row)
            ->where(['id' => $ids])
            ->limit(count($ids))
            //->echoSql()->echoValues()
            ->prepareExec()
        ;
    }

    /**
     * @param string $table
     * @param array $row
     * @return bool|int
     */
    public function mod($table, array $row)
    {
        return $this->modByIds($table, $row, [$row['id']]);
    }
    
    /**
     * @param string $table
     * @param array|string $ids
     * @param int $deleted_by
     * @return bool|int
     */
    public function softDel($table, $ids, $deleted_by)
    {
        return $this->modByIds($table, [
            'deleted_at' => date('Y-m-d H:i:s'),
            'deleted_by' => $deleted_by            
        ], $ids);
    }

    /**
     * @param string $table
     * @param string|array $ids
     * @return bool|int
     */
    public function del($table, $ids)
    {
        if (!is_array($ids)) $ids = explode(',', str_replace(' ', '', $ids));
        return $this->deleteFrom($table)->where(['id' => $ids])->limit(count($ids))->prepareExec();
    }

    /**
     * Check if column[value] already exists in $table
     *
     * @param string $table
     * @param array $col_values
     * @param string $id
     * @return array
     */
    public function duplicatedData($table, $col_values, $id = null)
    {
        $col_values = array_filter($col_values);

        $this->select('*')->from($table)->where('(`' . implode('` = ? OR `', array_keys($col_values)) . '` = ?)')->stmt_values(array_values($col_values));
        if ($id) $this->where(' id != ? ')->stmt_values($id);
        $rows = $this->prepareFetchAll();

        // get $column where $value resides
        $existing_col_values = [];
        foreach ($rows as $row) foreach ($col_values as $column => $value) if ($row[$column] == $value) $existing_col_values[] = $column;
        return $existing_col_values;
    }

    /**
     * Prepare PdoStatement & fetch it
     *
     * @param null|string $sql
     * @param null|array $values
     * @return array
     */
    public function prepareFetchAll($sql = null, $values = null, $fetch_mode = null)
    {
        if (!$sql && !$this->getSql()) return [];
        elseif (!$sql) $sql = $this->getSql();
        
        if (!$values) $values = $this->getValues();
        
        $this->resetSql();
        $stmt = $this->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll($fetch_mode ?: $this->_fetch_mode);
    }

    /**
     * Prepare PdoStatement, execute it and return number of affected rows
     *
     * @param null|string $sql
     * @param null|array $values
     * @return array
     */
    public function prepareExec($sql = null, $values = null)
    {
        if (!$sql && !$this->getSql()) return false;
        elseif (!$sql) $sql = $this->getSql();

        if (!$values) $values = $this->getValues();

        $this->resetSql();

        $stmt = $this->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Prepare PdoStatement, execute it and return first column
     * Useful to COUNT() (e.g on Module\SearchInterfaces)
     *
     * @param null|string $sql
     * @param null|array $values
     * @return array
     */
    public function prepareFetchColumn($sql = null, $values = null)
    {
        return $this->prepareFetchAll($sql, $values, PDO::FETCH_COLUMN);
    }


    /*********************
     * Setters & Getters *
     *********************/


    /**
     * @param $pdo
     * @return $this
     */
    public function setPdo($pdo)
    {
        $this->_pdo = $pdo;
        return $this;
    }

    /**
     * @return PDO
     */
    public function getPdo()
    {
        return $this->_pdo;
    }

    /**
     * @param int $fetch_mode
     * @return $this
     */
    public function setFetchMode($fetch_mode)
    {
        $this->_fetch_mode = $fetch_mode;
        return $this;
    }

    /**
     * @return int
     */
    public function getFetchMode()
    {
        return $this->_fetch_mode;
    }

    /**
     * @param int $err_mode
     * @return $this
     */
    public function setErrMode($err_mode)
    {
        $this->_err_mode = $err_mode;
        $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, $err_mode);
        return $this;
    }

    /**
     * @return int
     */
    public function getErrMode()
    {
        return $this->_err_mode;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (isset($options['fetch_mode'])) $this->setFetchMode($options['fetch_mode']);
        if (isset($options['err_mode'])) $this->setErrMode($options['err_mode']);
        return $this;
    }


    /*********************
     * Wrapper functions *
     *********************/


    /**
     * @param string $sql
     * @return PdoStatement
     */
    public function prepare($sql)
    {
        return $this->_pdo->prepare($sql);
    }

    /**
     * @param string $sql
     * @return int
     */
    public function exec($sql)
    {
        return $this->_pdo->exec($sql);
    }

    /**
     * @param string $sql
     * @param int $fetch_mode
     * @return PDOStatement
     */
    public function query($sql, $fetch_mode = null)
    {
        return $this->_pdo->query($sql, $fetch_mode ?: $this->_fetch_mode);
    }

    /**
     * @param string $sql
     * @return array
     */
    public function fetchAll($sql, $fetch_mode = null)
    {
        return $this->query($sql, $fetch_mode)->fetchAll();
    }

    /**
     * @return string
     */
    public function lastInsertId()
    {
        return $this->_pdo->lastInsertId();
    }

    /**
     * @return bool
     */
    public function beginTransaction()
    {
        return $this->_pdo->beginTransaction();
    }

    /**
     * @return bool
     */
    public function commit()
    {
        return $this->_pdo->commit();
    }

    /**
     * @return bool
     */
    public function rollBack()
    {
        return $this->_pdo->rollBack();
    }
}