<?php
/**
 * Created by Janux. jianetti@hotmail.com
 * Date: 06/14
 */

class AclRepository
{
    protected $db;

    public function __construct(\Db $db)
    {
        $this->db = $db;
    }


    /********************
     * Common Functions *
     ********************/


    /**
     * Remove redundant permissions
     * Return as [module][action][action_filter_criteria][action_filter_value] = inherited
     * @param $acl
     * @return array
     */
    public function combine($acl)
    {
        // Build an assoc array to easier add and remove permissions
        $acl_combined = array();
        foreach ($acl as $permission) {
            // add to 'allow' and remove from 'deny' at the same time
            if ($permission['allow']) {
                $to_add = 'allow';
                $to_remove = 'deny';
            }
            // vice versa
            else {
                $to_add = 'deny';
                $to_remove = 'allow';
            }

            // all modules \ all actions
            if ($permission['module'] == 'all') {
                $acl_combined[$to_add] = array('all' => array('id' => @$permission['id'], 'inherited_id' => @$permission['inherited_id'], 'inherited_name' => @$permission['inherited_name']));
                $acl_combined[$to_remove] = null;
            }
            // module \ all actions
            elseif ($permission['action'] == 'all') {
                $acl_combined[$to_add][$permission['module']] = array('all' => array('id' => @$permission['id'], 'inherited_id' => @$permission['inherited_id'], 'inherited_name' => @$permission['inherited_name']));
                unset($acl_combined[$to_remove][$permission['module']]);
            }
            // module \ action
            else {
                if (!isset($acl_combined[$to_add][$permission['module']])) $acl_combined[$to_add][$permission['module']] = array();

                // all criteria
                if ($permission['action_filter_criteria'] == 'all') {
                    $acl_combined[$to_add][$permission['module']][$permission['action']] = array('all' => array('id' => @$permission['id'], 'inherited_id' => @$permission['inherited_id'], 'inherited_name' => @$permission['inherited_name']));
                    if (isset($acl_combined[$to_remove][$permission['module']])) {
                        unset($acl_combined[$to_remove][$permission['module']][$permission['action']]);
                        if (!$acl_combined[$to_remove][$permission['module']]) unset($acl_combined[$to_remove][$permission['module']]);
                    }
                }
                // criteria
                else {
                    if (!isset($acl_combined[$to_add][$permission['module']][$permission['action']])) $acl_combined[$to_add][$permission['module']][$permission['action']] = array();
                    if (!isset($acl_combined[$to_add][$permission['module']][$permission['action']][$permission['action_filter_criteria']])) $acl_combined[$to_add][$permission['module']][$permission['action']][$permission['action_filter_criteria']] = array();
                    $acl_combined[$to_add][$permission['module']][$permission['action']][$permission['action_filter_criteria']][$permission['action_filter_value']] = array('id' => @$permission['id'], 'inherited_id' => @$permission['inherited_id'], 'inherited_name' => @$permission['inherited_name'], 'action_filter_value_label' => @$permission['action_filter_value_label']);

                    if (isset($acl_combined[$to_remove][$permission['module']]) && isset($acl_combined[$to_remove][$permission['module']][$permission['action']]) && isset($acl_combined[$to_remove][$permission['module']][$permission['action']][$permission['action_filter_criteria']])) {
                        unset($acl_combined[$to_remove][$permission['module']][$permission['action']][$permission['action_filter_criteria']][$permission['action_filter_value']]);
                        if (!$acl_combined[$to_remove][$permission['module']][$permission['action']][$permission['action_filter_criteria']]) {
                            unset($acl_combined[$to_remove][$permission['module']][$permission['action']][$permission['action_filter_criteria']]);
                            if (!$acl_combined[$to_remove][$permission['module']][$permission['action']]) {
                                unset($acl_combined[$to_remove][$permission['module']][$permission['action']]);
                                if (!$acl_combined[$to_remove][$permission['module']]) unset($acl_combined[$to_remove][$permission['module']]);
                            }
                        }
                    }
                }
            }
        }

        return $acl_combined;
    }

    /**
     * Convert combined associative acl array (as returned by combine() )
     * to an array of arrays (i.e. to display in \view)
     * @param $acl
     * @return array
     */
    public function assocToArray($acl)
    {
        $acl_array = array();

        $permission = array();
        foreach ($acl as $allow => $modules) {
            $permission['allow'] = ($allow == 'allow' ? 1 : 0);
            if (!$modules) continue;
            foreach ($modules as $module => $actions) {
                $permission['module'] = $module;
                if ($module == 'all') {
                    $permission['action'] = $permission['action_filter_criteria'] = $permission['action_filter_value'] = $permission['action_filter_value_label'] = null;
                    $permission['id'] = $actions['id'];
                    $permission['inherited_id'] = $actions['inherited_id'];
                    $permission['inherited_name'] = $actions['inherited_name'];
                    $acl_array[] = $permission;
                    continue;
                }

                foreach ($actions as $action => $action_filter_criterias) {
                    $permission['action'] = $action;
                    if ($action == 'all') {
                        $permission['action_filter_criteria'] = $permission['action_filter_value'] = $permission['action_filter_value_label'] = null;
                        $permission['id'] = $action_filter_criterias['id'];
                        $permission['inherited_id'] = $action_filter_criterias['inherited_id'];
                        $permission['inherited_name'] = $action_filter_criterias['inherited_name'];
                        $acl_array[] = $permission;
                        continue;
                    }

                    foreach ($action_filter_criterias as $action_filter_criteria => $action_filter_values) {
                        $permission['action_filter_criteria'] = $action_filter_criteria;
                        if ($action_filter_criteria == 'all') {
                            $permission['action_filter_value'] = $permission['action_filter_value_label'] = null;
                            $permission['id'] = $action_filter_values['id'];
                            $permission['inherited_id'] = $action_filter_values['inherited_id'];
                            $permission['inherited_name'] = $action_filter_values['inherited_name'];
                            $acl_array[] = $permission;
                            continue;
                        }

                        foreach ($action_filter_values as $action_filter_value => $data) {
                            $permission['action_filter_value'] = $action_filter_value;
                            $permission['action_filter_value_label'] = $data['action_filter_value_label'];
                            $permission['id'] = $data['id'];
                            $permission['inherited_id'] = $data['inherited_id'];
                            $permission['inherited_name'] = $data['inherited_name'];
                            $acl_array[] = $permission;
                        }
                    }
                }
            }
        }
        return $acl_array;
    }

    public function is_allowed($acl, $module, $action = null, $action_filter_criteria = null, $action_filter_value = null)
    {
        if (!$acl) return false;

        // assoc array
        if (isset($acl['allow'])) {
            return (
                (isset($acl['allow']['all'])
                    && (!isset($acl['deny'][$module])
                        || (!isset($acl['deny'][$module]['all'])
                            && (!isset($acl['deny'][$module][$action])
                                || (!isset($acl['deny'][$module][$action]['all'])
                                    && (!$action_filter_criteria
                                        || !isset($acl['deny'][$module][$action][$action_filter_criteria])
                                        || !isset($acl['deny'][$module][$action][$action_filter_criteria][$action_filter_value])
                                    )
                                )
                            )
                        )
                    )
                )
                || (isset($acl['allow'][$module])
                    && (!$action
                        || ((isset($acl['allow'][$module]['all'])
                                && (!isset($acl['deny'][$module][$action])
                                    || (!isset($acl['deny'][$module][$action]['all'])
                                        && (!$action_filter_criteria
                                            || !isset($acl['deny'][$module][$action][$action_filter_criteria])
                                            || !isset($acl['deny'][$module][$action][$action_filter_criteria][$action_filter_value])
                                        )
                                    )
                                )
                            )
                            || (isset($acl['allow'][$module][$action])
                                && (!isset($acl['deny'][$module][$action]['all']))
                                && (!$action_filter_criteria
                                    || (isset($acl['allow'][$module][$action][$action_filter_criteria])
                                        && ($action_filter_criteria == 'all' || isset($acl['allow'][$module][$action][$action_filter_criteria][$action_filter_value]))
                                    )
                                )
                            )
                        )
                    )
                )
            );
        }

        // idx array
        $is_allowed = false;
        foreach ($acl as $permission) {
            if ($permission['module'] == 'all'
                || ($permission['module'] == $module
                    && ($permission['action'] == 'all'
                        || ($permission['action'] == $action
                            && ($permission['action_filter_criteria'] == 'all'
                                || !$action_filter_criteria
                                || ($permission['action_filter_criteria'] == $action_filter_criteria && $permission['action_filter_value'] == $action_filter_value)
                            )
                        )
                    )
                )
            ) $is_allowed = $permission['allow'];
        }
        return $is_allowed;
    }
}