<?php

/*
 *  This file needs to be called every 3 hours with a cronjob.
 *  It then generates 10 new captcha images and converts them 
 *  to PNG and saves them in the directory /captchas
 *  If there are more than POOL_SIZE images in the directory,
 *  it replaces the newly generated ones with the 10 oldest 
 *  captchas, such that the size of the pool doesn't change.
 *  Then the function stores the paths => strings of the added
 *  captchas in the options database of wordpress.
 *  All image files have a 18 chars long random name, so the 
 *  probability that there is a collision is very low.
 */
 
define('POOL_SIZE', 500);
define('NUM_CAPTCHAS_TO_GEN', 10);
define('TARGET_DIR', 'captchas/');

require 'cunning_captcha_lib.php';
/* Bring the wordpress API into play */
define('WP_USE_THEMES', false);
require('../../../wp-blog-header.php'); /* Assuming we're in plugin directory */

feed_pool();

function feed_pool() {
	$captchas = cclib_generateCaptchas($path=TARGET_DIR, $number=10, $captchalength=5);
	
	/* Convert them to png using pnmtopng */
	foreach (array_keys($captchas) as $path) {
		system(sprintf("pnmtopng %s > %s && rm %s;",$path.'ppm', $path.'png', $path.'ppm'));
	}
	/* 
	 * If the pool size is now too large, delete the overflow files from the directory
	 * and the options database
	 */
	 
	
	$savedcaptchas = get_option('ccaptcha_captchainfo');
}

?>
