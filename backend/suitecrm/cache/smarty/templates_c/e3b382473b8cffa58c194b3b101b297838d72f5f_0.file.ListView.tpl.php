<?php
/* Smarty version 4.5.3, created on 2025-07-22 21:51:04
  from '/var/www/html/include/SugarFields/Fields/Base/ListView.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.3',
  'unifunc' => 'content_688007c8621713_50566924',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    'e3b382473b8cffa58c194b3b101b297838d72f5f' => 
    array (
      0 => '/var/www/html/include/SugarFields/Fields/Base/ListView.tpl',
      1 => 1753214541,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_688007c8621713_50566924 (Smarty_Internal_Template $_smarty_tpl) {
$_smarty_tpl->_checkPlugins(array(0=>array('file'=>'/var/www/html/include/Smarty/plugins/function.sugar_fetch.php','function'=>'smarty_function_sugar_fetch',),));
?>

<?php echo smarty_function_sugar_fetch(array('object'=>$_smarty_tpl->tpl_vars['parentFieldArray']->value,'key'=>$_smarty_tpl->tpl_vars['col']->value),$_smarty_tpl);?>

<?php }
}
