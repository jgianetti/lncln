<?php
/**
 * @var $cfg array
 * @var $lang array
 * @var $session Session
 * @var $aclHelper Jan_Acl\AclHelper
 */

// Not logged
$session_user = $session->get('user');
if (empty($session_user['id'])) return;


/*************
 * USER MENU *
 *************/

$html = '<ul class="module">';

/********
 * USER *
 ********/
if ($aclHelper->is_allowed($session_user['acl'], 'user.search')) {
    $html .= '<li><a href="'.$cfg['base_url'].'/user/search" class="user_search" data-ajax="1">'.$lang['_base']['user'].'</a>';
    $submenu = [];

    /************
     * USER ADD *
     ************/
    if ($aclHelper->is_allowed($session_user['acl'], 'user.add')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/user/add" class="user_add" data-ajax="1">' . $lang['_base']['add'] . ' ' . $lang['_base']['user'] . '</a>');
    }

    /************
     * Category *
     ************/
    if ($aclHelper->is_allowed($session_user['acl'], 'category.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/category/search" class="category_search" data-ajax="1">' . $lang['_base']['category'] . '</a>');
    }

    /********
     * RFID *
     ********/
    if ($aclHelper->is_allowed($session_user['acl'], 'rfid.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/rfid/search" class="rfid_search" data-ajax="1">' . $lang['_base']['rfid'] . '</a>' .
            ($aclHelper->is_allowed($session_user['acl'],'rfid', 'add') ? '<ul><li><a href="'.$cfg['base_url'].'/rfid/add" class="rfid_add" data-ajax="1">'.$lang['_base']['rfid_add'].'</a></li></ul>':''));
    }

    /****************
     * USER ABSENCE *
     ****************/
    if ($aclHelper->is_allowed($session_user['acl'], 'user_absence.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/user_absence/search" class="user_absence" data-ajax="1">' . $lang['_base']['user_absence'] . '</a>');
    }

    /*********************
     * PRINT 'IN SCHOOL' *
     *********************/
    if ($aclHelper->is_allowed($session_user['acl'], 'user.print_in_school')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/user/print_in_school" class="user_print_in_school" target="_blank">' . $lang['_base']['print_in_school'] . '</a>');
    }

    if (count($submenu)) $html .= '<ul><li>' . implode('</li><li>', $submenu) . '</li></ul>';
    $html .= '</li>';
}

/*******************
 * COPYCENTER MENU *
 ******************/

$copycenter_menu = false;
foreach (['cc_supplier', 'cc_product', 'cc_purchase', 'cc_delivery', 'cc_help'] as $e) {
    if ($aclHelper->is_allowed($session_user['acl'],$e.'.search')) {
        $copycenter_menu = true;
        break;
    }
}

if ($copycenter_menu) {
    $html .= '<li><a>CopyCenter</a>';
    $submenu = array();

    /***************
     * CC_SUPPLIER *
     ***************/
    if ($aclHelper->is_allowed($session_user['acl'], 'cc_supplier.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/cc_supplier/search" class="cc_supplier_search" data-ajax="1">' . $lang['_base']['cc_supplier'] . '</a>' .
            ($aclHelper->is_allowed($session_user['acl'],'cc_supplier.add') ? '<ul><li><a href="'.$cfg['base_url'].'/cc_supplier/add" class="cc_supplier_add" data-ajax="1">'.$lang['_base']['add'].'</a></li></ul>':''));
    }

    /***************
     * cc_product *
     ***************/
    if ($aclHelper->is_allowed($session_user['acl'], 'cc_product.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/cc_product/search" class="cc_product_search" data-ajax="1">' . $lang['_base']['cc_product'] . '</a>' .
            ($aclHelper->is_allowed($session_user['acl'],'cc_product', 'add')  ? '<ul><li><a href="'.$cfg['base_url'].'/cc_product/add" class="cc_product_add" data-ajax="1">'.$lang['_base']['add'].'</a></li></ul>':''));
    }

    /***************
     * cc_purchase *
     ***************/
    if ($aclHelper->is_allowed($session_user['acl'], 'cc_purchase.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/cc_purchase/search" class="cc_purchase_search" data-ajax="1">' . $lang['_base']['cc_purchase'] . '</a>' .
            ($aclHelper->is_allowed($session_user['acl'],'cc_purchase', 'add') ? '<ul><li><a href="'.$cfg['base_url'].'/cc_purchase/add" class="cc_purchase_add" data-ajax="1">'.$lang['_base']['add'].'</a></li></ul>':''));
    }

    /***************
     * cc_delivery *
     ***************/
    if ($aclHelper->is_allowed($session_user['acl'], 'cc_delivery.search')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/cc_delivery/search" class="cc_delivery_search" data-ajax="1">' . $lang['_base']['cc_delivery'] . '</a>' .
            ($aclHelper->is_allowed($session_user['acl'],'cc_delivery', 'add') ? '<ul><li><a href="'.$cfg['base_url'].'/cc_delivery/add" class="cc_delivery_add" data-ajax="1">'.$lang['_base']['add'].'</a></li></ul>':''));
    }

    /***************
     * cc_help *
     ***************/
    if ($aclHelper->is_allowed($session_user['acl'], 'cc_help.view')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/cc_help/view" class="cc_help_view">' . $lang['_base']['cc_help'] . '</a>');
    }

    if (count($submenu)) $html.= '<ul><li>' . implode('</li><li>',$submenu) . '</li></ul>';
    $html .= '</li>';
}


/*********
 * Error *
 *********/
if ($aclHelper->is_allowed($session_user['acl'], 'error.search')) {
    $html .= '<li><a href="'.$cfg['base_url'].'/error/search" class="error_search" data-ajax="1">'.$lang['_base']['errors'].'</a>';
    $submenu = [];

    if ($aclHelper->is_allowed($session_user['acl'], 'error.clean')) {
        array_push($submenu, '<a href="' . $cfg['base_url'] . '/error/search?clean=1" class="error_search">' . $lang['_base']['del'] . ' ' . $lang['_base']['errors'] . '</a>');
    }

    if (count($submenu)) $html .= '<ul><li>' . implode('</li><li>', $submenu) . '</li></ul>';
    $html .= '</li>';
}


$html .= '</ul>';

/*************
 * USER NAME *
 *************/

$html .= '<span class="user-name">' . $session_user['last_name'] . ', ' . $session_user['name'] . '</span>';


/***********
 * ACTIONS *
 ***********/

$html .= '<ul class="action">';
if (count($cfg['languages'])>1) {
    $html .= '<li><span class="language">'.$lang['_base']['language'].'</span></li><ul>';
    foreach($cfg['languages'] as $e) $html .= '<li><a href="'.$cfg['base_url'].'/?lang='.$e.'" class="lang_setup">'.ucfirst($e).'</a></li>';
    $html .= '</ul>';
}

$html .= '<li><a href="'.$cfg['base_url'].'/?logout=1">'.$lang['_base']['logout'].'</a></li>';
$html .= '</ul>';


return $html;