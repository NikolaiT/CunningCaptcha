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

/* Get wordpress API */
//define('WP_USE_THEMES', false);
//require('/home/nikolai/motivational_abyss/workspace/wordpress/wp-blog-header.php');

require 'cunning_captcha_lib.php';

//D(cclib_generateCaptchas($path='/tmp', $number=5));

// Set a filter to add additional input fields for the comment.
add_filter('comment_form_defaults', 'ccaptcha_comment_form_defaults');
// Add a filter to verify if the captch was correct.
add_filter('preprocess_comment', 'ccaptcha_check');

/*
 * Create the captcha and store the string in the database.
 */
function ccaptcha_comment_form_defaults($default) {
    
    if (!is_admin()) {
        $default['fields']['email'] .= 
            '<img id="captcha_image" src="'.__(plugin_dir_url(__FILE__)).'captcha.png">
            <p class="comment-form-captcha">
            <label style="margin-right:30px;" for="ccaptcha">'.__('Captcha', 'CunningCaptcha').'</label>
            <input id="ccaptcha" name="ccaptcha" size="30" type="text" /></p>';
    }
    return $default;
}

function ccaptcha_login_form_defaults($default) {
	
	return $default;
}

function ccaptcha_check($commentdata) {
    global $current_user;
    get_currentuserinfo();
    $uid = $current_user->ID;

    if ($uid != 1) {
        if (!isset($_POST['ccaptcha']))
          wp_die(__('Error: You need to enter the captcha.', 'CunningCaptcha'));

        $answer = strip_tags($_POST['ccaptcha']);
        $generated = get_option('ccaptcha');

        if (strcasecmp($answer, $generated) != 0)
          wp_die(__('Error: Your supplied captcha is incorrect.', 'CunningCaptcha'));
    }
    return $commentdata;
}
?>
