<?php
/**
 * Created by Janux
 * Date: 19/04/12
 * Time: 15:47
 * @todo: Historical Database Support
 * @todo: Log Support + Exception Handling
 */
class DbDataMapper
{
    /**
     * @var PDO
     */
    protected $_pdo;

    /**
     * @var string
     */
    protected $_table_name;

    /**
     * @var int
     */
    protected $_fetch_mode = PDO::FETCH_ASSOC;

    /**
     * @var bool
     */
    protected $_is_historical_table = false; // 'table'_archive must exist

    /**
     * @param PDO $pdo
     * @param array $options
     */
    public function __construct(PDO $pdo, $options = null)
    {
        $this->setPdo($pdo);
        if (!$options || !isset($options['errmode_exception'])) $options['errmode_exception'] = PDO::ERRMODE_EXCEPTION;
        $this->setOptions($options);
    }

    /**
     * Fetch specific columns from Db using a Where clause
     *
     * @param mixed  $columns   Column names
     * @param string $where     Where clause
     * @param string $order_by  Order by clause
     * @param string $limit     Limit clause
     * @param string $table_name
     * @return array            Rows
     */
    public function get($columns = '*', $where = null, $order_by =  null, $limit = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        // escape columns
        if (is_array($columns)) $columns = '`' . str_replace(' ','',implode('`,`',$columns)) . '`';
        elseif (strpos($columns, ',') === true) $columns = '`' . str_replace(array(' ',','), array('', '`,`'), $columns) . '`';

        $sql = 'SELECT ' . $columns . ' FROM `' . $table_name . '`' .
            ($where? ' WHERE ' . $where : '') .
            ($order_by? ' ORDER BY ' . $order_by : '') .
            ($limit? ' LIMIT ' . $limit : '');

        return $this->fetchAll($sql);
    }

    /**
     * Fetch rows from Db using a Where clause
     *
     * @param string $where     Where clause
     * @param string $order_by  Order by clause
     * @param string $limit     Limit clause
     * @param string $table_name
     * @return array            Rows
     */
    public function getWhere($where, $order_by = null, $limit = null, $table_name = null)
    {
        return $this->get('*', $where, $order_by, $limit, $table_name);
    }

    /**
     * Fetch rows from Db using Column[s] = Value[s]
     *
     * 1 column checks to every value.
     * >1 column checks each column with each value.
     *
     * @param mixed $columns  String or Array with column names
     * @param mixed $values     String or Array with column values
     * @param string $order_by  Order By clause
     * @param string $limit     Limit clause
     * @param string $table_name    Table Name
     * @return array
     */
    public function getBy($columns, $values, $order_by = null, $limit = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;
        if (is_string($columns)) $columns = explode(',', str_replace(' ', '', $columns));
        if (is_string($values)) $values = explode(',', str_replace(' ', '', $values));

        // 1 col_name to check with every value
        if (count($columns) == 1) {
            $where = '`'.$columns[0].'` IN('.implode(',', array_fill(0, count($values), '?')).')';
            $stmt_values = $values; // values to use in stmt->execute($stmt_values)
        }
        // each col_name = each value[s]
        else {
            $where = '';
            $stmt_values = array(); // values to use in stmt->execute($stmt_values)
            // take the smallest array count
            if (($max = count($columns)) > ($i = count($values))) $max = $i;
            for ($i=0;$i<$max;$i++) {
                // multiple values
                if (is_string($values[$i])) $values[$i] = explode(',', str_replace(' ', '', $values[$i]));
                $where .= ($i ? ' AND ':'') . '`'.$columns[$i].'` IN('.implode(',', array_fill(0, count($values[$i]), '?')).')';
                $stmt_values = array_merge($stmt_values, $values[$i]);
            }
        }

        $stmt = $this->prepare('SELECT * FROM `'.$table_name.'` WHERE ' . $where . ($order_by ? ' ORDER BY ' . $order_by : '') . ($limit ? ' LIMIT '. $limit : ''));
        $stmt->execute($stmt_values);
        return $stmt->fetchAll($this->_fetch_mode);
    }

    /**
     * Fetch rows from Db using Id's [table_name.id]
     *
     * @param mixed $ids
     * @param string $order_by      Order Clause
     * @param string $limit         Limit Clause
     * @param string $table_name    Table Name
     * @return array                Rows
     */
    public function getByIds($ids, $order_by = null, $limit = null, $table_name = null)
    {
        return $this->getBy('id', $ids, $order_by, $limit, $table_name);
    }

    /**
     * Fetch row data from database using Id [table_name.id]
     *
     * @param mixed $id
     * @param string $table_name
     * @return array    Row data
     */
    public function getById($id, $table_name = null)
    {
        if (!($row = $this->getByIds($id, null, 1, $table_name))) return array();
        return $row[0];
    }

    /**
     * Returns an array of column names if its value exists in Db.
     *
     * Useful to valid unique data on those columns
     *
     * @param array $col_values $col_values[column] = value
     * @param string $where
     * @param string $table_name
     * @return array            Column names that contain those values
     */
    public function getExistingColValues($col_values, $where = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        // fetch rows with that column values
        $stmt = $this->prepare('SELECT * FROM `'.$table_name.'` WHERE '.($where ? ($where.' AND ') : '').' (`' . implode('` = ? OR `', array_keys($col_values)) . '` = ?)');
        $stmt->execute(array_values($col_values));
        $rows = $stmt->fetchAll($this->_fetch_mode);

        // get $column where $value resides
        $existing_col_values = array();
        foreach ($rows as $row) foreach ($col_values as $column => $value) if ($row[$column] == $value) $existing_col_values[] = $column;
        return $existing_col_values;
    }

    /**
     * @param string|null $where
     * @param string|null $table_name
     * @return int
     */
    public function getCount($where = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        $row = $this->fetchAll('SELECT COUNT(id) as count FROM `'.$table_name.'`'.($where ? ' WHERE ' . $where : ''));
        return intval($row['count']);
    }

    /**
     * Insert a row (associative array) into database
     *
     * @param array $row        row[column] = value
     * @param string $table_name
     * @return bool
     */
    public function add($row, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        $sql = 'INSERT INTO `' . $table_name . '`' .
                ' (`' . implode('`, `', array_keys($row)) . '`)' .
                ' VALUES (' . implode(',', array_fill(0, count($row), '?')) .')' ;

        $stmt = $this->_pdo->prepare($sql);
        return $stmt->execute(array_values($row));
    }

    /**
     * Update database using a Where clause
     *
     * @param array $col_values     $col_values[column] = value
     * @param string $where         Where clause
     * @param string $limit         Limit clause
     * @param string $table_name
     * @return bool
     */
    public function mod($col_values, $where, $limit = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        $sql = 'UPDATE `' . $table_name . '`' .
            ' SET `' . implode('` = ?, `', array_keys($col_values)) . '` = ?' .
            ($where? ' WHERE ' . $where:'') .
            ($limit? ' LIMIT ' . $limit:'');

        $stmt = $this->prepare($sql);
        return $stmt->execute(array_values($col_values));
    }

    /**
     * Update database using Id0s [table_name.id]
     *
     * @param mixed $ids
     * @param array $col_values   $col_values[column] = value
     * @param string $table_name
     * @return bool
     */
    public function modByIds($ids, $col_values, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;
        if (is_string($ids)) $ids = explode(',',str_replace(' ', '', $ids));

        $sql = 'UPDATE `'.$table_name.'`' .
            ' SET `' . implode('` = ?, `', array_keys($col_values)) . '` = ?' .
            ' WHERE id IN('.implode(',', array_fill(0, count($ids), '?')).') LIMIT '.count($ids);
        $stmt = $this->prepare($sql);
        return $stmt->execute(array_merge(array_values($col_values), $ids));
    }

    /**
     * Update database using an Id [table_name.id]
     *
     * @param mixed $id
     * @param array $data   $data[column] = value
     * @param string $table_name
     * @return bool
     */
    public function modById($id, $data, $table_name = null)
    {
        return $this->modByIds($id, $data, $table_name);
    }

    /**
     * Delete from database using a Where clause
     *
     * @param string $where     Where clause
     * @param string $limit     Limit clause
     * @param string $table_name
     * @return int|bool
     */
    public function del($where, $limit = null, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;

        $sql = 'DELETE FROM `' . $table_name . '`' .
            ($where? ' WHERE ' . $where:'') .
            ($limit? ' LIMIT ' . $limit:'');

        return $this->exec($sql);
    }

    /**
     * Delete from database using Id's [table_name.id]
     *
     * @param mixed $ids
     * @param string $table_name
     * @return bool
     */
    public function delByIds($ids, $table_name = null)
    {
        if (!$table_name) $table_name = $this->_table_name;
        if (is_string($ids)) $ids = explode(',',str_replace(' ', '', $ids));
        $stmt = $this->prepare('DELETE FROM `'.$table_name.'` WHERE id IN('.implode(',', array_fill(0, count($ids), '?')).') LIMIT '.count($ids));
        return $stmt->execute($ids);
    }

    /**
     * Delete from database using Id [table_name.id]
     *
     * @param mixed $id
     * @param string $table_name
     * @return bool
     */
    public function delById($id, $table_name = null)
    {
        return $this->delByIds($id, $table_name);
    }

    /**
     * Retrieve an array of values from one column in a result set
     *
     * @param string $column
     * @param array $rs     Result Set
     * @return array
     */
    public function getValuesFromRs($column, $rs)
    {
        $result = array();
        foreach ($rs as $row) if (isset($row[$column])) $result[] = $row[$column];
        return $result;
    }

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
    public function query($sql, $fetch_mode = PDO::FETCH_ASSOC)
    {
        return $this->_pdo->query($sql, $fetch_mode);
    }

    /**
     * @param string $sql
     * @return array
     */
    public function fetchAll($sql)
    {
        return $this->query($sql, $this->_fetch_mode)->fetchAll();
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

// setters & getters

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
     * @param string $table_name
     * @return $this
     */
    public function setTableName($table_name)
    {
        $this->_table_name = $table_name;
        return $this;
    }

    /**
     * @return string
     */
    public function getTableName()
    {
        return $this->_table_name;
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
     * @param bool $is_historical_table
     * @return $this
     */
    public function setIsHistoricalTable($is_historical_table)
    {
        $this->_is_historical_table = $is_historical_table;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsHistoricalTable()
    {
        return $this->_is_historical_table;
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (isset($options['table_name'])) $this->setTableName($options['table_name']);
        if (isset($options['fetch_mode'])) $this->setFetchMode($options['fetch_mode']);
        if (isset($options['errmode_exception'])) $this->_pdo->setAttribute(PDO::ATTR_ERRMODE, $options['errmode_exception']);
        if (isset($options['is_historical_table'])) $this->setIsHistoricalTable($options['is_historical_table']);
        return $this;
    }

}