<?php
/* Smarty version 4.5.3, created on 2025-07-22 21:51:04
  from '/var/www/html/themes/SuiteP/include/ListView/ListViewSelectObjects.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.3',
  'unifunc' => 'content_688007c839b6a2_72863515',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '3d7294b8a50fe932fa6e05e75d9fc27a147f5985' => 
    array (
      0 => '/var/www/html/themes/SuiteP/include/ListView/ListViewSelectObjects.tpl',
      1 => 1753214542,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_688007c839b6a2_72863515 (Smarty_Internal_Template $_smarty_tpl) {
?><div class="selectedRecords label hidden"><?php echo $_smarty_tpl->tpl_vars['APP']->value['LBL_LISTVIEW_SELECTED_OBJECTS'];?>
</div><div class="selectedRecords value hidden"><?php echo $_smarty_tpl->tpl_vars['TOTAL_ITEMS_SELECTED']->value;?>
</div>
<input type='hidden' id='selectCountTop' name='selectCount[]' value='<?php echo $_smarty_tpl->tpl_vars['TOTAL_ITEMS_SELECTED']->value;?>
' />

<?php echo '<script'; ?>
>

    $(document).ready(function () {
        setInterval(function () {
            sListView.toggleSelected();
        }, 100);
    });

<?php echo '</script'; ?>
><?php }
}
