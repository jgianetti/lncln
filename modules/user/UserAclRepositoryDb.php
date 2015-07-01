<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */
namespace Jan_User;
use Jan_Category\CategoryModel;
use Jan_Category\CategorySearchDb ;

//class UserAclRepositoryDb implements UserAclRepositoryInterface
class UserAclRepositoryDb extends \AclRepository
{
    public function getUserPermission($id)
    {
        return $this->db->getById('*','user_acl',$id);
    }

    public function delUserPermission($id)
    {
        return $this->db->del('user_acl', $id);
    }

    /**
     * Get User's ACL
     * @param $user_id
     * @param $combined
     * @return array
     */
    public function getUser($user_id, $combined = true)
    {
        $sql = 'SELECT a_u.*,
                    NULL AS inherited_id,
                    NULL AS inherited_name,
                    IF (action_filter_criteria = "category_id", cat.name, NULL) AS action_filter_value_label
                FROM user_acl AS a_u
                LEFT JOIN category AS cat ON cat.id = a_u.action_filter_value
                WHERE
                    a_u.user_id = ?
                ORDER BY module, action, action_filter_criteria, action_filter_value_label
        ';
        $values = array($user_id);

        if ($combined) return $this->combine($this->db->prepareFetchAll($sql, $values));
        return $this->db->prepareFetchAll($sql, $values);
    }

    public function saveUser($user_id, array $acl)
    {
        $ret = true;
        foreach ($acl as $permission) {
            if (!empty($permission['inherited_id'])) continue;
            unset($permission['inherited_id'], $permission['inherited_name'], $permission['action_filter_value_label']);
            if (empty($permission['id'])) unset($permission['id']);

            $permission['user_id'] = $user_id;

            // Set empty values to NULL
            foreach ($permission as $key => $value) if ($value !== 0 && $value !== '0' && !$value) $permission[$key] = null;
            if (!$this->db->add('user_acl', $permission)) $ret = false;
        }

        return $ret;
    }

    public function delUser($user_id)
    {
        $this->db->deleteFrom('user_acl')->where('user_id', $user_id)->prepareExec();
    }

    /**
     * Get User Categories's ACL
     * A user may belong to multiple categories
     * @param $user_id
     * @param CategorySearchDb $categoryModel
     * @param bool $combined
     * @return array
     */
    public function getUserCategories($user_id, CategorySearchDb  $categoryModel, $combined = true) // TODO: CategoryModelInterface
    {
        $category_ids = array();
        foreach ($this->db->select('category_id')->from('user_category AS u_c')->leftJoin('category AS cat', 'user_category', false)->where('u_c.user_id', $user_id)->prepareFetchAll() as $row) $category_ids[] = $row['category_id'];

        // Prepend parents from all categories
        $category_ids = array_merge($categoryModel->getParentsIds($category_ids), $category_ids);
        $category_ids_params = implode(',', array_fill(0, count($category_ids), '?'));

        $sql = 'SELECT a_c.*,
                    inherited.id AS inherited_id,
                    inherited.name AS inherited_name,
                    IF (action_filter_criteria = "category_id", cat.name, NULL) AS action_filter_value_label
                FROM category_acl AS a_c
                LEFT JOIN category AS inherited ON inherited.id = a_c.category_id
                LEFT JOIN category AS cat ON cat.id = a_c.action_filter_value
                WHERE
                    a_c.category_id IN('.$category_ids_params.')
                ORDER BY module, action, action_filter_criteria, action_filter_value_label
        ';
        $values = array_merge($category_ids);

        if ($combined) return $this->combine($this->db->prepareFetchAll($sql, $values));
        return $this->db->prepareFetchAll($sql, $values);
    }

    /**
     * Get Categories & User's ACL combined as an associative array
     * @param $user_id
     * @param CategorySearchDb $CategorySearchDb - needed to get parents
     * @return array
     */
    public function getCombinedAssoc($user_id, CategorySearchDb $categoryModel)
    {
        return $this->combine(array_merge($this->getUserCategories($user_id, $categoryModel, false), $this->getUser($user_id, false)));
    }

    /**
     * Get Categories & User's ACL combined as an indexed array
     * @param $user_id
     * @param CategorySearchDb $categoryModel
     * @return array
     */
    public function getCombined($user_id, CategorySearchDb $categoryModel)
    {
        return $this->assocToArray($this->getCombinedAssoc($user_id, $categoryModel));
    }

    /**
     * Check categories tree to set which ones are allowed
     * this->is_allowed() returns true/false without checking sub-categories
     * If not specified - 'allowance' is taken from parent category
     *
     * @param array $user           : needed to get 'self' categories
     * @param string $module
     * @param string $action
     * @param CategorySearchDb $categoryModel
     * @param string|bool $indent   : Correct indentation is possible only when the whole tree is analyzed - not afterwards
     * @return array
     */
    public function getCategoriesAllowance($user, $module, $action, CategorySearchDb $categoryModel, $indent = false)
    {
        if ($indent && !is_string($indent)) $indent = ' &nbsp; &nbsp; '; // default string

        // Allowed categories
        $categories_allowed = array();
        if (isset($user['acl']['allow'][$module]) && isset($user['acl']['allow'][$module][$action]) && isset($user['acl']['allow'][$module][$action]['category_id'])) {
            $categories_allowed = array_keys($user['acl']['allow'][$module][$action]['category_id']);
            // self categories - remove key and append user categories
            if (in_array('self', $categories_allowed)) $categories_allowed = array_merge(array_diff($categories_allowed, array('self')), explode(',', $user['cat_ids']));
        }

        // Denied categories
        $categories_denied = array();
        if (isset($user['acl']['deny'][$module]) && isset($user['acl']['deny'][$module][$action]) && isset($user['acl']['deny'][$module][$action]['category_id'])) {
            $categories_denied = array_keys($user['acl']['deny'][$module][$action]['category_id']);
            // self categories - remove key and append user categories
            if (in_array('self', $categories_denied)) $categories_denied = array_merge(array_diff($categories_denied, array('self')), explode(',', $user['cat_ids']));
        }

        $categories = $categoryModel->get();
        $nested_set = array();
        foreach ($categories as &$e) {
            // Root category (ie. "All")
            if (!count($nested_set)) $allow = $this->is_allowed($user['acl'], $module, $action, '*');
            else while (count($nested_set) && $e['rgt']>$nested_set[count($nested_set)-1]['rgt']) { array_pop($nested_set); $allow = $nested_set[count($nested_set)-1]['allow']; }

            if (count($nested_set) && $indent) $e['name'] = str_repeat(' '.$indent.' ',count($nested_set)-1).utf8_encode($e['name']);

            if (in_array($e['id'], $categories_allowed)) $allow = true;
            elseif (in_array($e['id'], $categories_denied)) $allow = false;
            $e['_allow'] = $allow;

            $nested_set[] = array('rgt' => $e['rgt'], 'allow' => $allow);
        }

        // root category (ie. "ALL") is not used anywhere
        array_shift($categories);
        return $categories;
    }

    /**
     * @param array $categories     : getCategoriesAllowance();
     * @return bool
     */
    public function allCategoriesAreAllowed($categories)
    {
        foreach ($categories as $row) if (!$row['_allow']) return false;
        return true;
    }

    /**
     * Get filters according to ACL (categories + self)
     *
     * @param array $filters
     * @param array $categories
     * @return array
     */
    public function getFilters($filters, $categories)
    {
        $allowed_categories_ids = array();
        if (!($all_categories_allowed = $this->allCategoriesAreAllowed($categories))) foreach ($categories as $row) if ($row['_allow']) $allowed_categories_ids[] = $row['id'];

        // User has not filtered but ACL does
        if (empty($filters['category'])) {
            if (!$all_categories_allowed) $filters['category'] = $allowed_categories_ids;
        }
        else {
            if (!$all_categories_allowed) $filters['category'] = array_intersect($filters['category'],$allowed_categories_ids);
        }
        return $filters;
    }

    /**
     * @param $acl
     * @param $module
     * @param $action
     * @return bool
     */
    public function selfOnly($acl, $module, $action)
    {
        return (isset($acl['allow'][$module]) && isset($acl['allow'][$module][$action]) && isset($acl['allow'][$module][$action]['self']) && (count($acl['allow'][$module][$action]) == 1));
    }
}