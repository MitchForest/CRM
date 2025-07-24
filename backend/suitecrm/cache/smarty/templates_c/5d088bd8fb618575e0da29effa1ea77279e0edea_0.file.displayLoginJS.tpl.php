<?php
/* Smarty version 4.5.3, created on 2025-07-24 17:33:56
  from '/var/www/html/include/MVC/View/tpls/displayLoginJS.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.3',
  'unifunc' => 'content_68826e848bb796_22777441',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '5d088bd8fb618575e0da29effa1ea77279e0edea' => 
    array (
      0 => '/var/www/html/include/MVC/View/tpls/displayLoginJS.tpl',
      1 => 1753297806,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_68826e848bb796_22777441 (Smarty_Internal_Template $_smarty_tpl) {
if ($_smarty_tpl->tpl_vars['MODULE_SUGAR_GRP1']->value) {?>
    <?php echo '<script'; ?>
 type="text/javascript">var module_sugar_grp1 = '<?php echo $_smarty_tpl->tpl_vars['MODULE_SUGAR_GRP1']->value;?>
';<?php echo '</script'; ?>
>
<?php }
if ($_smarty_tpl->tpl_vars['ACTION_SUGAR_GRP1']->value) {?>
    <?php echo '<script'; ?>
 type="text/javascript">var action_sugar_grp1 = '<?php echo $_smarty_tpl->tpl_vars['ACTION_SUGAR_GRP1']->value;?>
';<?php echo '</script'; ?>
>
<?php }
echo '<script'; ?>
 type="text/javascript" src="<?php echo $_smarty_tpl->tpl_vars['SUGAR_GRP1_JQUERY']->value;?>
" z><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
 type="text/javascript" src="<?php echo $_smarty_tpl->tpl_vars['SUGAR_GRP1_YUI']->value;?>
"><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
 type="text/javascript" src="<?php echo $_smarty_tpl->tpl_vars['SUGAR_GRP1']->value;?>
"><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
 type="text/javascript" src="<?php echo $_smarty_tpl->tpl_vars['CALENDAR']->value;?>
"><?php echo '</script'; ?>
>
<?php echo '<script'; ?>
 type="text/javascript">

    if ( typeof(SUGAR) == 'undefined' ) {SUGAR = {}};
    if ( typeof(SUGAR.themes) == 'undefined' ) SUGAR.themes = {};

<?php echo '</script'; ?>
>

<?php }
}
