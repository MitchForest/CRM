<?php
/* Smarty version 4.5.3, created on 2025-07-22 21:51:04
  from '/var/www/html/themes/SuiteP/include/MySugar/tpls/actions_menu.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.3',
  'unifunc' => 'content_688007c8694b63_61784300',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '1ed8494d3d1b1b352077ccfa1f524075c9b76030' => 
    array (
      0 => '/var/www/html/themes/SuiteP/include/MySugar/tpls/actions_menu.tpl',
      1 => 1753214542,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_688007c8694b63_61784300 (Smarty_Internal_Template $_smarty_tpl) {
?><ul class="dropdown-menu tab-actions">
    <?php if (!$_smarty_tpl->tpl_vars['lock_homepage']->value) {?>

        <li>
            <input class="button addDashlets" type="button" name="Edit"  data-toggle="modal" data-target=".modal-add-dashlet" value="<?php echo $_smarty_tpl->tpl_vars['lblAddDashlets']->value;?>
">
        </li>
        <li>
            <input class="button addDashboard" type="button" name="Edit"  data-toggle="modal" data-target=".modal-add-dashboard" value="<?php echo $_smarty_tpl->tpl_vars['lblAddTab']->value;?>
">
        </li>
        <li>
            <input class="button addDashboard" type="button" name="Edit"  data-toggle="modal" data-target=".modal-edit-dashboard" value="<?php echo $_smarty_tpl->tpl_vars['app']->value['LBL_EDIT_TAB'];?>
">
        </li>
    <?php }?>
</ul>
<?php }
}
