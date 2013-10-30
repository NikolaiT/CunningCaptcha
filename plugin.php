<?php

/**
 * Plugin Name: CunningCaptcha
 * Plugin URI: http://incolumitas.com
 * Description: Generating Captchas and displaying them at comment/login form.
 * Version: 0.1
 * Author: Nikolai Tschacher
 * Author URI: http://incolumitas.com/about
 * License: A "Slug" license name e.g. GPL2
 */
 
 /*  Copyright 2013  Nikolai Tschacher  (email : admin *[at]* incolumitas.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/*
 * CunningCaptcha is a down to Bézier plotting and vector graphics complete 
 * captcha implementation. 
 * This wordpress plugin generates every 3 hours 10 new captcha images 
 * and stores them in a folder. When the folder contains
 * more than 1000 captcha images, it replaces the newly generated ones with
 * the 10 oldest ones. Then the plugin serves every user with some captchas
 * from the pool (but of course not all images: The uppper bound of reloads per IP
 * address is set to 20.)
 * 
 * This approach guarantees a low memory footprint (since generating captchas
 * is memory and even more CPU power consuming) while maintaing a pretty good
 * protection from spammers.
 */


if (!defined('POOL_SIZE'))
	define('POOL_SIZE', 40);
if (!defined('NUM_CAPTCHAS_TO_GEN'))
	define('NUM_CAPTCHAS_TO_GEN', 10);
if (!defined('TARGET_DIR'))
	define('TARGET_DIR', 'captchas/');

/* 
 * Only the cronjob calls this file directly. The cronjob must have the same
 * IP address as the webserver, otherwise it won't be executed. This prevents
 * users from calling this file directly and DDOSing the server
 */
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"]) ) {
	if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
		require_once('cunning_captcha_lib.php');
		/* Bring the wordpress API into play */
		define('WP_USE_THEMES', false);
		require('../../../wp-blog-header.php'); /* Assuming we're in plugin directory */
		feed_pool();
	}
	else
		echo "No, no, no!";
		
	exit();
}

if (!session_id())
	session_start();

require_once('cunning_captcha_lib.php');

// Set a filter to add additional input fields for the comment.
add_filter('comment_form_defaults', 'ccaptcha_comment_form_defaults');
// Add a filter to verify if the captch in the comment section was correct.
add_filter('preprocess_comment', 'ccaptcha_validate_comment_captcha');

// Ad custom captcha field to login form
add_action('login_form', 'ccaptcha_login_form_defaults');
// Validate captcha in login form.
add_action('login_head','ccaptcha_validate_login_captcha');

/*
 * Create the captcha and store the string in the database.
 */
function ccaptcha_comment_form_defaults($default) {
    if (!is_admin()) {
		
		if (isset( $_SESSION["ccaptcha"])) 
			unset( $_SESSION["ccaptcha"]);
			
        $default['fields']['email'] .= 
            '<img id="captcha_image" src="'.__(get_captcha(), 'CunningCaptcha').'captcha.png">
            <p class="comment-form-captcha">
            <label style="margin-right:30px;" for="ccaptcha">'.__('Captcha', 'CunningCaptcha').'</label>
            <input id="ccaptcha" name="ccaptcha" size="30" type="text" /></p>';
    }
    return $default;
}

function ccaptcha_validate_comment_captcha($commentdata) {
    global $current_user;
    get_currentuserinfo();
    $uid = $current_user->ID;

    if ($uid != 1) {
        if (!isset($_POST['ccaptcha']))
          wp_die(__('Error: You need to enter the captcha.', 'CunningCaptcha'));

        $answer = strip_tags($_POST['ccaptcha']);
        $solution = $_SESSION["ccaptcha"];
        echo "<h1>Solution is: $solution</h1>";

        /*if (strcasecmp($answer, $generated) != 0)
          wp_die(__('Error: Your supplied captcha is incorrect.', 'CunningCaptcha'));*/
    }
    return $commentdata;
}

function ccaptcha_login_form_defaults() {

    //Get and set any values already sent
    $user_captcha = ( isset( $_POST['ccaptcha'] ) ) ? $_POST['ccaptcha'] : '';
    ?>

    <p>
		<img id="captcha_image" src="<?php _e(plugin_dir_url(__FILE__).'captcha.png', 'CunningCaptcha'); ?>"
        <label for="ccaptcha"><?php _e('Captcha','CunningCaptcha') ?><br />
            <input type="text" name="ccaptcha" id="ccaptcha" class="input" value="<?php echo esc_attr(stripslashes($user_captcha)); ?>" size="25" /></label>
    </p>

    <?php
}

function ccaptcha_validate_login_captcha() {
    global $error;
    if(empty($_POST['ccaptcha'])) {
		$error  = 'You need to enter a captcha while logging in.';
    }
}

/*
 * Choses a random captcha from the pool and returns the corresponding image path.
 * Sets global variable $captcha_value to the value (The solution the user has to enter)
 * of the captcha.
 */
function get_captcha() {
	$path_captcha_arr = get_option('ccaptcha_path_captcha_a');
	if (false == $path_captcha_arr)
		print(__('Error: Cannot retrieve option ccaptcha_path_captcha_a.', 'CunningCaptcha'));
	$keys = array_keys($path_captcha_arr);
	if (false === shuffle($keys))
		print(__('Error: Cannot pick random captcha from pool.', 'CunningCaptcha'));
	
	$_SESSION["ccaptcha"] = $path_captcha_arr[$keys[0]];
	
	return plugin_dir_path(__FILE__).TARGET_DIR.$keys[0];
}

/*
 * Adds new captcha images to the pool.
 */
function feed_pool() {
	/* First of all check if pnmtopng is available on the system */

	$handle = popen('/usr/bin/which pnmtopng 2>&1', 'r');
	$read = fread($handle, 2096);
	pclose($handle);
	if (!file_exists(trim($read))) {
		print("pnmtopng is not installed on the system.");
		return false;
	}

	/* Check if target dir exists, if not, create it */
	if (!file_exists(TARGET_DIR) && !is_dir(TARGET_DIR)) {
		if (!mkdir(TARGET_DIR, $mode=0755))
			print("Couldn't create image directory");
			return false;
	}
	
	$captchas = cclib_generateCaptchas($path=TARGET_DIR, $number=10, $captchalength=5);
	
	/* Convert them to png using pnmtopng */
	foreach (array_keys($captchas) as $path) {
		system(sprintf("pnmtopng %s > %s && rm %s;",$path.'.ppm', $path.'.png', $path.'.ppm'));
	}
	/* 
	 * If the pool size is now too large, delete the overflow files from the directory
	 * and the options database.
	 */
	$cnt = 0;
	foreach (glob(TARGET_DIR."*.png") as $filename) {
		$filetimes[$filename] = filectime($filename);
		$cnt++;
	}
	
	if ($cnt > POOL_SIZE) {
		$num_to_delete = $cnt - POOL_SIZE;
		/* Sort the array by value (unix timestamp) */
		if (!asort($filetimes, SORT_NUMERIC)) {
			print("couldn't sort images by modification time.");
			return false;
		}
		$keys = array_keys($filetimes);
		foreach (range(0, $num_to_delete) as $i) {
			//echo "deleting ".$keys[$i]. "<br />";
			unlink($keys[$i]);
		}
	}
	
	$savedcaptchas = get_option('ccaptcha_path_captcha_a');

	if (!$savedcaptchas)
		update_option('ccaptcha_path_captcha_a', $captchas);
	else {
		update_option('ccaptcha_path_captcha_a', array_merge($savedcaptchas, $captchas));
	}
	
	return true;
}
?>
