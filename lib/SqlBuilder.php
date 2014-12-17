<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
class SqlBuilder
{
    private $_insertInto = null,
        $_replaceInto = null,
        $_update = null,
        $_select = [],
        $_deleteFrom = null,
        $_from = [],
        $_left_join = [],
        $_join = [],
        $_set = [],
        $_where = [],
        $_group_by = '',
        $_having = [],
        $_order_by = '',
        $_limit = '',
        $_table_alias = [],
        $_stmt_values = []
    ;

   public function insertInto($table)
    {
        $this->_insertInto = $table;
        return $this;
    }

    public function replaceInto($table)
    {
        $this->_replaceInto = $table;
        return $this;
    }

    public function update($table)
    {
        $this->_update = $table;
        return $this;
    }

    public function deleteFrom($table)
    {
        $this->_deleteFrom = $table;
        return $this;
    }

    // Store table aliases
    public function tableAlias($str = null, $alias = null)
    {
        if (!is_null($alias)) $this->_table_alias[$str] = $alias;
        elseif (!($pos_as = stripos($str,' as '))) $this->_table_alias[$str] = $str;
        else {
            // table AS t1 ON ...
            if ($pos_on = stripos($str,' on ')) $this->_table_alias[substr($str,0,$pos_as)] = substr($str,$pos_as+4, ($pos_on - 4) - $pos_as);
            else $this->_table_alias[substr($str,0,$pos_as)] = substr($str,$pos_as+4);
        }

        return $this;
    }

    /**
     * @param null|string|array $select
     * @return $this
     */
    public function select($select = null)
    {
        if (is_null($select)) $this->_select = [];
        elseif (is_array($select)) $this->_select[] = $select;
        // t1[c1,c2,...],t2[c1,c2,...] =>
        // match[1] = t1
        // match[2] = c1,c2
        elseif (preg_match_all('/([_a-zA-Z]+)\[([^]]+)\]/', str_replace(' ', '',$select), $matches,  PREG_SET_ORDER)) {
            // first table[column,...] match is considered to be the primary table, i.e in FROM clause
            // Other table's columns are aliased as table2[column] => table2_column
            $this->_select[] = $matches[0][1].'.'.str_replace(',',', '.$matches[0][1].'.',$matches[0][2]);
            unset($matches[0]);

            foreach ($matches as $match) $this->_select[] = rtrim(preg_replace('/([^,]+),?/', $match[1].'.$1 AS '.array_flip($this->_table_alias)[$match[1]].'_$1,', $match[2]),','); // last column gets an extra comma
        }
        elseif (strpos($select, ',')) $this->_select = array_merge($this->_select, explode(',', $select));
        else $this->_select[] = $select;
        return $this;
    }

    public function from($from = null)
    {
        if (is_null($from)) $this->_from = [];
        else $this->_from[] = $from;
        return $this->tableAlias($from);
    }

    public function leftJoin($left_join = null, $on_table = null, $t1_has_fk = true)
    {
        if (is_null($left_join)) $this->_left_join = [];
        else {
            $this->tableAlias($left_join);

            if (is_null($on_table)) $this->_left_join[] = $left_join;
            else {
                if ($pos = stripos($left_join,' as ')) $table = substr($left_join,0,$pos);
                else $table = $left_join;

                // t1 ON t1.t2_id = t2.id
                if ($t1_has_fk) $this->_left_join[] = $left_join . ' ON ' . $this->_table_alias[$table] . '.' . $on_table . '_id = ' . $this->_table_alias[$on_table] . '.id';
                // t1 ON t1.id = t2.t1_id
                else $this->_left_join[] = $left_join . ' ON ' . $this->_table_alias[$on_table] . '.' . $table . '_id = ' . $this->_table_alias[$table] . '.id';
            }
        }
        return $this;
    }

    public function join($join = null, $on_table = null, $t1_has_fk = true)
    {
        if (is_null($join)) $this->_join = [];
        else {
            $this->tableAlias($join);

            if (is_null($on_table)) $this->_join[] = $join;
            else {
                if ($pos = stripos($join,' as ')) $table = substr($join,0,$pos);
                else $table = $join;

                // t1 ON t1.t2_id = t2.id
                if ($t1_has_fk) $this->_join[] = $join . ' ON ' . $this->_table_alias[$table] . '.' . $on_table . '_id = ' . $this->_table_alias[$on_table] . '.id';
                // t1 ON t1.id = t2.t1_id
                else $this->_join[] = $join . ' ON ' . $this->_table_alias[$on_table] . '.' . $table . '_id = ' . $this->_table_alias[$table] . '.id';
            }
        }
        return $this;
    }

    public function set($set = null)
    {
        if (is_null($set)) $this->_set = [];
        elseif (!is_array($set)) $this->_set[] = $set;
        else {
            foreach ($set as $column => $value) {
                $this->_set[] = '`'.$column.'` = ?';
                $this->_stmt_values[] = $value;
            }
        }
        return $this;
    }

    public function where($where = null, $value = null)
    {
        if (!is_null($value)) $where = array($where => $value);

        if (is_null($where)) $this->_where = [];
        elseif (!is_array($where)) $this->_where[] = $where;
        else {
            foreach ($where as $column => $values) {
                // [column]=string TO [column]=[value,value]
                if (!is_array($values)) $values = explode(',', str_replace(' ', '',$values));

                if (count($values) == 1) $this->_where[] = $column . ' = ? ';
                else $this->_where[] = $column.' IN('.implode(',', array_fill(0, count($values), '?')).')';
                $this->_stmt_values = array_merge($this->_stmt_values, $values);
            }
        }
        return $this;
    }

    public function groupBy($group_by = null)
    {
        if (is_null($group_by)) $this->_group_by = '';
        else $this->_group_by = $group_by;
        return $this;
    }

    public function having($having = null, $value = null)
    {
        if (!is_null($value)) $having = array($having => $value);

        if (is_null($having)) $this->_having = [];
        elseif (!is_array($having)) $this->_having[] = $having;
        else {
            foreach ($having as $column => $values) {
                // [column]=string TO [column]=[value,value]
                if (!is_array($values)) $values = explode(',', str_replace(' ', '',$values));

                if (count($values) == 1) $this->_having[] = $column . ' = ? ';
                else $this->_having[] = $column.' IN('.implode(',', array_fill(0, count($values), '?')).')';
                $this->_stmt_values = array_merge($this->_stmt_values, $values);
            }
        }
        return $this;
    }

    public function limit($limit = null)
    {
        if (is_null($limit)) $this->_limit = '';
        else $this->_limit = $limit;
        return $this;
    }

    public function orderBy($order_by = null)
    {
        if (is_null($order_by)) $this->_order_by = '';
        else $this->_order_by = $order_by;
        return $this;
    }

    public function stmt_values($value = null)
    {
        if (is_null($value)) $this->_stmt_values = [];
        elseif (is_array($value)) $this->_stmt_values = array_merge($this->_stmt_values, $value);
        else $this->_stmt_values[] = $value;
        return $this;
    }

    public function getSql()
    {
        $sql = '';
        if ($this->_insertInto)     $sql .= 'INSERT INTO '  . $this->_insertInto .' ';
        elseif ($this->_replaceInto)$sql .= 'REPLACE INTO '  . $this->_replaceInto .' ';
        elseif ($this->_update)     $sql .= 'UPDATE '       . $this->_update .' ';
        elseif ($this->_deleteFrom) $sql .= 'DELETE FROM '  . $this->_deleteFrom .' ';
        else $sql .= 'SELECT '.($this->_select ? implode(',',$this->_select) : '*').' FROM ' . ($this->_from ? implode(', ',$this->_from) : '') .' ';

        return $sql .
            ($this->_left_join  ? ' LEFT JOIN ' . implode(' LEFT JOIN ',$this->_left_join) : '') .
            ($this->_join       ? ' JOIN ' . implode(' JOIN ',$this->_join) : '') .
            ($this->_set        ? ' SET ' . implode(', ',$this->_set) : '') .
            ($this->_where      ? ' WHERE ' . implode(' AND ',$this->_where) : '') .
            ($this->_group_by   ? ' GROUP BY ' . $this->_group_by : '') .
            ($this->_having     ? ' HAVING ' . implode(' AND ',$this->_having) : '') .
            ($this->_order_by   ? ' ORDER BY ' . $this->_order_by : '') .
            ($this->_limit      ? ' LIMIT ' . $this->_limit : '')
            ;
    }

    public function getValues()
    {
        return $this->_stmt_values;
    }

    public function resetSql()
    {
        $this->_insertInto = $this->_update = $this->_deleteFrom = $this->_group_by = $this->_order_by = $this->_limit = null;
        $this->_select = $this->_from = $this->_left_join = $this->_join = $this->_set = $this->_where = $this->_having = $this->_table_alias = $this->_stmt_values = [];
        return $this;
    }

    public function getWhere()
    {
        return $this->_where;
    }

    public function getOrderBy()
    {
        return $this->_order_by;
    }

    // Simple debugger
    public function echoSql()
    {
        echo $this->getSql();
        return $this;
    }

    public function echoValues()
    {
        print_r($this->_stmt_values);
        return $this;
    }
}