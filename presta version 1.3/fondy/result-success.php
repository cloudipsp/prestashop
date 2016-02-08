<?php
/**
 * Created by PhpStorm.
 * User: helcy
 * Date: 12.09.14
 * Time: 9:05
 */

require_once(dirname(__FILE__).'/fondy.php');
$module = new Fondy();

$smarty->assign('message', 'Payment was successful');

// Display all and exit
include(_PS_ROOT_DIR_.'/header.php');
echo $module->display(__FILE__, 'result-success.tpl');
include(_PS_ROOT_DIR_.'/footer.php');
die ;