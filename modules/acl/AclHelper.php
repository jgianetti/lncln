<?php
/**
 * Permissions are in the form of:
 *  [module.action]  = [ [allow => bool, filter_criteria => string, filter_value => string, inherited => string ] , [...], ... ]
 * This allows for multiple filter_criteria per [module.action]
 * And may include wildcards:
 *  module.*  || *.action
 */

namespace Jan_Acl;

class AclHelper
{
    /********************
     * Common Functions *
     ********************/
    
    /**
     * TODO
     * Remove redundant permissions
     * ie.:
     *   module.* - allow
     *   module.action - allow
     * 
     * Return as [module.action]  = [ [allow => bool, filter_criteria => string, filter_value => string, inherited => string ] , [...], ... ]
     * 
     * @param array $acl
     * @return array $return
     */
    public function purge(array $acl)
    {
        $return = [];
        foreach ($acl as $perm => $specs) {
            // check if 
            
        }
        return $return;
    }
    
    /**
     * Build an associative array
     * [module.action]  = [ [allow => bool, filter_criteria => string, filter_value => string, inherited => string ] , [...], ... ]
     * 
     * @param array $acl
     * @return array $return
     */
    public function toAssoc($acl)
    {
        $return = [];
        foreach ($acl as $perm) {
            $perm_name = $perm['module'] . ($perm['action'] ? '.'.$perm['action'] : null);

            if (!isset($return[$perm_name])) $return[$perm_name] = [];
            $return[$perm_name][] = [
                'allow'           => $perm['allow'],
                'action_filter_criteria' => $perm['action_filter_criteria'],
                'action_filter_value'    => $perm['action_filter_value'],
                'inherited'       => (!empty($perm['inherited']) ? $perm['inherited'] : ''),
                'inherited_name'  => (!empty($perm['inherited_name']) ? $perm['inherited_name'] : ''),
            ];
        }
        return $return;
    }
    
    /**
     * Convert associative acl array to indexed array
     * @param $acl
     * @return array
     */
    public function toIndexed($acl)
    {
        $return = [];
        foreach ($acl as $perm => $specs) {
            list($module, $action) = array_pad(explode('.', $perm), 2, null);

            foreach ($specs as $spec) {
                $return[] = [
                    'module'            => $module,
                    'action'            => $action,
                    'allow'             => $spec['allow'],
                    'action_filter_criteria'   => $spec['action_filter_criteria'],
                    'action_filter_value'      => $spec['action_filter_value'],
                    'inherited'         => $spec['inherited'],
                    'inherited_name'    => $spec['inherited_name'],
                ];
            }
        }
        return $return;
    }

    /**
     * TODO: check module only
     *
     * @param array $acl
     * @param string $perm
     * @return bool
     */
    /*
     * NEW WAY
    public function is_allowed($acl, $perm)
    {
        list($module, $action) = array_pad(explode('.', $perm), 2, null);

        $checks = ['*', '*.'.$action, $module.'.*', $module.'.'.$action];

        foreach ($checks as $check) {
            if (!isset($acl[$check])) continue;

            foreach ($acl[$check] as $spec) if ($spec['allow']) return true;
        }
        return false;
    }
    */

    /*
     * OLD WAY
     */
    public function is_allowed($acl, $perm, $action_filter_criteria = null, $action_filter_value = null)
    {
        if (!$acl) return false;

        list($module, $action) = array_pad(explode('.', $perm), 2, null);

        // assoc array
        if (isset($acl['allow'])) {
            return (
                (isset($acl['allow']['*'])
                    && (!isset($acl['deny'][$module])
                        || (!isset($acl['deny'][$module]['*'])
                            && (!isset($acl['deny'][$module][$action])
                                || (!isset($acl['deny'][$module][$action]['*'])
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
                        || ((isset($acl['allow'][$module]['*'])
                                && (!isset($acl['deny'][$module][$action])
                                    || (!isset($acl['deny'][$module][$action]['*'])
                                        && (!$action_filter_criteria
                                            || !isset($acl['deny'][$module][$action][$action_filter_criteria])
                                            || !isset($acl['deny'][$module][$action][$action_filter_criteria][$action_filter_value])
                                        )
                                    )
                                )
                            )
                            || (isset($acl['allow'][$module][$action])
                                && (!isset($acl['deny'][$module][$action]['*']))
                                && (!$action_filter_criteria
                                    || (isset($acl['allow'][$module][$action][$action_filter_criteria])
                                        && ($action_filter_criteria == '*' || isset($acl['allow'][$module][$action][$action_filter_criteria][$action_filter_value]))
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
            if ($permission['module'] == '*'
                || ($permission['module'] == $module
                    && ($permission['action'] == '*'
                        || ($permission['action'] == $action
                            && ($permission['action_filter_criteria'] == '*'
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


    /**
     * Return scpecified permission filters
     *
     * @param array $acl
     * @param string $perm
     * @return array
     */
    public function get_filters($acl, $perm)
    {
        $filters = [];

        list($module, $action) = explode('.',$perm);

        $checks = ['*', '*.'.$action, $module.'.*', $module.'.'.$action];

        foreach ($checks as $check) {
            if (!isset($acl[$check])) continue;

            foreach ($acl[$check] as $spec) {
                if (!empty($spec['action_filter_criteria'])) {
                    if (!isset($filters[$spec['action_filter_criteria']])) $filters[$spec['action_filter_criteria']] = [];
                    $filters[$spec['action_filter_criteria']][] = $spec['action_filter_value'];
                }
            }
        }

        return $filters;
    }
}