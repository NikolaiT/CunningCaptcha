<?php

/* All the code that deals with wordpress and data peristence comes here.
 * The captcha and it's logic is stored in ccaptcha.php */
 
function __autoload($classname) {
	include('ccaptcha.php');
}

$captcha = new Captcha();
?>
