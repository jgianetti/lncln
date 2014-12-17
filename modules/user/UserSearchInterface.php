<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;

interface UserSearchInterface
{
    /**
     * @param string|array $select
     * @param array $search_data
     * @param string $order_by
     * @param string $limit
     * @return array
     */
    public function get($select, $search_data, $order_by = null, $limit = null);

    /**
     * @param array $search_data
     * @return int
     */
    public function count($search_data = null);
}