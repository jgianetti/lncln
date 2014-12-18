<?php
/**
 * @var $cfg array
 * @var $lang array
 * @var $request Request
 * @var $session Session
 * @var $aclRepository AclRepository
 * @var $controller_return array
 */


$session_user = $session->get('user');
$lang['user'] = json_decode(file_get_contents('modules/user/_pub/lang/es.json'), true);
?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=iso-8859-1" />
    <title><?php echo $lang['_base']['document_title'] ?> :: <?php /* echo $lang['_base'][$request->getModule()."_".$request->getAction()] */ ?></title>
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery.dataTables.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/jquery.dataTables.TableTools.css" />
    <link rel="stylesheet" href="<?php echo $cfg['base_url'] ?>/_pub/css/app.css" />
</head>
<body>
<div id="container">
    <div id="main">
        <div>Fecha: <?php echo date('d/m/Y - H:i:s'), ' - TOTAL: ',$controller_return['total']; ?></div><br>
<?php
        foreach ($controller_return['categories'] as $cat) {
?>
        <table class="user-print-in-school">
            <thead>
            <tr>
                <th><?php echo $cat['name'], ' - Total: ',count($cat['users']); ?></th>
            </tr>
            </thead>
            <tbody>
<?php
            foreach ($cat['users'] as $u) {
?>
            <tr>
                <td><?php echo $u['last_name'],', ',$u['name']; ?> </td>
            </tr>
<?php
            }
?>
            </tbody>
        </table>
<?php
        }
?>
    </div>
</div>
<script>
    window.print();
</script>
</body>
</html>