<?php
/** Created by Janux. jianetti@hotmail.com */
namespace Jan_Category;

//class CategoryAclRepositoryDb implements CategoryAclRepositoryInterface
class CategoryAclRepositoryDb extends \AclRepository
{
    public function getCategoryPermission($id)
    {
        return $this->db->getById('*','category_acl',$id);
    }

    public function delCategoryPermission($id)
    {
        return $this->db->del('category_acl', $id);
    }

    /**
     * Get Categories's ACL
     * @param $category_id
     * @param CategoryModel $categoryModel
     * @param bool $combined
     * @return array
     */
    public function getCategory($category_id, CategoryModel $categoryModel, $combined = true) // TODO: CategoryModelInterface
    {
        // Prepend parents
        $all_ids = $categoryModel->getParentsIds($category_id);
        $all_ids[] = $category_id;
        $all_ids_params = implode(',', array_fill(0, count($all_ids), '?'));

        $sql = 'SELECT a_c.*,
                    IF (a_c.category_id = ?, NULL, inherited.id) AS inherited_id,
                    IF (a_c.category_id = ?, NULL, inherited.name) AS inherited_name,
                    IF (action_filter_criteria = "category_id", cat.name, NULL) AS action_filter_value_label
                FROM category_acl AS a_c
                LEFT JOIN category AS inherited ON inherited.id = a_c.category_id
                LEFT JOIN category AS cat ON cat.id = a_c.action_filter_value
                WHERE
                    a_c.category_id IN('.$all_ids_params.')
                ORDER BY module, action, action_filter_criteria, action_filter_value_label
        ';
        $values = array($category_id, $category_id);
        $values = array_merge($values, $all_ids);

        if ($combined) return $this->combine($this->db->prepareFetchAll($sql, $values));
        return $this->db->prepareFetchAll($sql, $values);
    }

    public function saveCategory($category_id, array $acl)
    {
        $ret = true;
        foreach ($acl as $permission) {
            if ($permission['inherited_id']) continue;
            unset($permission['inherited_id'], $permission['inherited_name'], $permission['action_filter_value_label']);
            if (empty($permission['id'])) unset($permission['id']);

            $permission['category_id'] = $category_id;

            // Set empty values to NULL
            foreach ($permission as $key => $value) if (($key != 'allow') && !$value) $permission[$key] = null;

            if (!$this->db->add('category_acl', $permission)) $ret = false; // INSERT
        }
        return $ret;
    }

    public function delCategory($id)
    {
        $this->db->deleteFrom('category_acl')->where('category_id', $id)->prepareExec();
    }
}