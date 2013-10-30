<?php

/*
 * CunningCaptcha is a down to Bézier plotting and vector graphics complete 
 * captcha implementation. 
 * This wordpress plugin generates every 3 hours
 * 10 new captcha images and stores them in a folder. When the folder contains
 * more than 1000 captcha images, it replaces the newly generated ones with
 * the 10 oldest ones. Then the plugin serves every user with some captchas
 * from the pool (but of course not all images: The uppper bound of reloads per IP
 * address is set to 20.)
 * 
 * This approach guarantees a low memory footprint (since generating captchas
 * is memory and even more CPU power consuming) while maintaing a pretty good
 * protection from spammers.
 */
 
function __autoload($classname) {
	include('ccaptcha.php');
}

$captcha = new Captcha();
?>
