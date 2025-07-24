<?php
/* Smarty version 4.5.3, created on 2025-07-24 17:33:56
  from '/var/www/html/include/utils/recaptcha_disabled.tpl' */

/* @var Smarty_Internal_Template $_smarty_tpl */
if ($_smarty_tpl->_decodeProperties($_smarty_tpl, array (
  'version' => '4.5.3',
  'unifunc' => 'content_68826e84986953_82050794',
  'has_nocache_code' => false,
  'file_dependency' => 
  array (
    '5124abb240fb9d5dab6fed94134b12a518821cca' => 
    array (
      0 => '/var/www/html/include/utils/recaptcha_disabled.tpl',
      1 => 1753297806,
      2 => 'file',
    ),
  ),
  'includes' => 
  array (
  ),
),false)) {
function content_68826e84986953_82050794 (Smarty_Internal_Template $_smarty_tpl) {
echo '<script'; ?>
>

  /**
   * Login Screen Validation
   */
  function validateAndSubmit() {
      generatepwd();
    }

  /**
   * Password reset screen validation
   */
  function validateCaptchaAndSubmit() {
      document.getElementById('username_password').value = document.getElementById('new_password').value;
      document.getElementById('ChangePasswordForm').submit();
    }
<?php echo '</script'; ?>
>
<?php }
}
