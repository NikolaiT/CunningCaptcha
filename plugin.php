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
 * and stores them in a folder. If the folder contains
 * more than 1000 captcha images, it replaces the newly generated ones with
 * the 10 oldest ones. Then the plugin serves every user with some captchas
 * from the pool (but of course not all images: The uppper bound of reloads per IP
 * address is set to 20.)
 * 
 * This approach guarantees a low memory footprint (since generating captchas
 * is memory and even more CPU power consuming) while maintaing a pretty good
 * protection from spammers.
 * 
 * There are several dependencies:
 *  - You need to install pnmtopng.
 *
 * The following design issues need further tinkering:
 *  - Captchas generation is slow and memory/cpu expensive. (Possible solution: Make svg captchas?!)
 *  - It is hard to prevent determined spammers from cracking/brute forcing the exhaustive captcha pool (Solution would be the generate captcha on request principle)s
 */

define('POOL_SIZE', 100);
define('NUM_CAPTCHAS_TO_GEN', 10);
define('TARGET_DIR', 'captchas/');

/* 
 * Disable that if you use this plugin on your own server.
 * Own my site for example, I need to style the form for the horizontal bootstrap 3 form 
 */
define('CUSTOM_FORM_STYLE', True);

/* Should we consider the case of the captcha? */
define('CASE_SENSITIVE', True);

/*
 * Only the cronjob calls this file directly. The cronjob must have the same
 * IP address as the webserver, otherwise it won't be executed. This prevents
 * users from calling this file directly and DDOSing the server
 */
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    if (in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', '::1'))) {
        require_once('cunning_captcha_lib.php');
        /* Bring the wordpress API into play */
        define('WP_USE_THEMES', false);
        require('../../../wp-blog-header.php'); /* Assuming we're in plugin directory */
        ccaptcha_feed_pool(); /* Feed the little monster */
    } else {
        wp_die(__('Error: Dont call CunningCaptcha directly. It does not like it :(.', 'CunningCaptcha'));
    }
}

require_once('cunning_captcha_lib.php');

/**
 * If this plugin get's started the very first time, add a base64 encoded ecryption key to the WP options database.
 */
if (false == get_option('ccpatcha_encryption_key')) {
    if (false == ($key = ccaptcha_random_hex_bytes($length = 32)))
        wp_die(__('Error: Failed generating encryption key.', 'CunningCaptcha'));

    add_option('ccpatcha_encryption_key', base64_encode($key));
}

// Set the solution cookie
//add_action('init', 'ccaptcha_set_solution_cookie');
// Set a filter to add additional input fields for the comment.
add_filter('comment_form_defaults', 'ccaptcha_comment_form_defaults');
// Add a filter to verify if the captch in the comment section was correct.
add_filter('preprocess_comment', 'ccaptcha_validate_comment_captcha');

// Ad custom captcha field to login form
add_action('login_form', 'ccaptcha_login_form_defaults');
// Validate captcha in login form.
add_filter('authenticate', 'ccaptcha_validate_login_captcha', 30, 3);

function ccaptcha_set_solution_cookie() {
    if (!isset($_COOKIE["ccaptcha_solution"])) {
        setcookie("ccaptcha_solution", '', time() + 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}

/*
 * Create the captcha and store the encrypted solution in a hidden field.
 * Alternatives:
 * Use a encrypted session variable (Bad: Uses files = slow)
 * Use add_option() [Using a database] (Bad: Needs to be written to = slow)
 * Use a encrypted hidden field. That's what I do :/
 * Use a cookie. (Bad: Can't make it working with wordpress.)
 */

function ccaptcha_comment_form_defaults($default) {
    if (!is_admin()) {

        $out = ccaptcha_get_captcha();
        
        if (CUSTOM_FORM_STYLE === True) {
            $default['fields']['email'] .=
                    '<div class="form-group">
                        <label class="col-sm-2 control-label" for="ccaptcha_answer">' . __('Captcha', 'CunningCaptcha') . '<span class="required"> *</span></label>
                        <div class="col-sm-10">
                                <input id="ccaptcha_answer" class="form-control" name="ccaptcha_answer" size="30" type="text" />
                                <img id="captcha_image" style="padding:10px 0;" src="' . __($out["captcha_path"], 'CunningCaptcha') . '" /> 
                                <input name="ccaptcha_solution" type="hidden" value="' . $out["captcha_solution"] . '" />
                        </div>
                    </div>';
        } else {
            $default['fields']['email'] .=
                    '<p class="comment-form-captcha"><label for="ccaptcha_answer">' . __('Captcha', 'CunningCaptcha') . '</label>
                    <span class="required">*</span>
                    <input id="ccaptcha_answer" name="ccaptcha_answer" size="30" type="text" />
                    <input name="ccaptcha_solution" type="hidden" value="' . $out["captcha_solution"] . '" />
                    <img id="captcha_image" src="' . __($out["captcha_path"], 'CunningCaptcha') . '" /> </p>';
        }
    }
    return $default;
}

function ccaptcha_validate_comment_captcha($commentdata) {
    if (!is_admin()) { /* Admins excluded. They should't be prevented from spamming... */
        if (empty($_POST['ccaptcha_answer']))
            wp_die(__('Error: You need to enter the captcha.', 'CunningCaptcha'));

        $answer = strip_tags($_POST['ccaptcha_answer']);

        $solution = trim(ccaptcha_decrypt($_POST["ccaptcha_solution"]));

        if (!ccaptcha_check($answer, $solution))/* Case insensitive comparing */
            wp_die(__('Error: Your supplied captcha is incorrect.', 'CunningCaptcha'));
    }
    return $commentdata;
}

function ccaptcha_login_form_defaults() {

    //Get and set any values already sent
    $user_captcha = ( isset($_POST['ccaptcha_answer']) ) ? $_POST['ccaptcha_answer'] : '';

    $out = ccaptcha_get_captcha();
    
    ?>
    
    <div class="form-group">
        <img id="captcha_image" src="<?php _e($out["captcha_path"], 'CunningCaptcha') ?>" />
        <label for="ccaptcha_answer" class="col-sm-2 control-label"><?php _e('Captcha', 'CunningCaptcha') ?><span class="required"> *</span></label>
        <input type="text" name="ccaptcha_answer" id="ccaptcha_answer" class="form-control" value="<?php echo esc_attr(stripslashes($user_captcha)); ?>" size="25" />
        <input type="hidden" name="ccaptcha_solution" value="<?php echo $out["captcha_solution"] ?>" >
    </div>
    
    <?php
}

function ccaptcha_validate_login_captcha($user, $username, $password) {
    if (!is_admin()) { /* Whenever a admin tries to login -.- */
        if (empty($_POST['ccaptcha_answer'])) {
            return new WP_Error('invalid_captcha', __("You need to enter a fucking captcha.", 'CunningCaptcha'));
        } else {
            $answer = strip_tags($_POST['ccaptcha_answer']);
            $solution = trim(ccaptcha_decrypt($_POST["ccaptcha_solution"]));
            if (!ccaptcha_check($answer, $solution)) {/* Case insensitive comparing */
                return new WP_Error('invalid_captcha', __("Your supplied captcha is incorrect.", 'CunningCaptcha'));
            } else {
                return $user;
            }
        }
    }
}

/*
 * Checks whether the user provided answer is correct.
 */

function ccaptcha_check($answer, $solution) {
    if (CASE_SENSITIVE) {
        return (strcmp($answer, $solution) == 0) ? True : False;
    } else {
        return (strcasecmp($answer, $solution) == 0) ? True : False;
    }
}

/*
 * Choses a random captcha from the pool and returns the corresponding image path.
 * Sets global variable $captcha_value to the value (The solution the user has to enter)
 * of the captcha.
 */

function ccaptcha_get_captcha() {
    $path_captcha_arr = get_option('ccaptcha_path_captcha_a');
    if (false == $path_captcha_arr)
        print(__('Error: Cannot retrieve option ccaptcha_path_captcha_a.', 'CunningCaptcha'));

    $keys = array_keys($path_captcha_arr);
    if (false === shuffle($keys))
        print(__('Error: Cannot pick random captcha from pool.', 'CunningCaptcha'));

    $path = trailingslashit(plugin_dir_url(__FILE__)) . $keys[0] . ".png";

    return array("captcha_path" => $path, "captcha_solution" => ccaptcha_encrypt($path_captcha_arr[$keys[0]]));
}

/**
 * Get random pseudo bytes for encryption.
 */
function ccaptcha_random_hex_bytes($length = 32) {
    $cstrong = False;
    $bytes = openssl_random_pseudo_bytes($length, $cstrong);
    if ($cstrong == False)
        return False;
    else
        return $bytes;
}

/**
 * Encrypt data using AES_256 with CBC mode. Prepends IV on ciphertext.
 * 
 */
function ccaptcha_encrypt($plaintext) {
    if (false == ($key = get_option('ccpatcha_encryption_key')))
        wp_die(__('Encryption error: could not retrieve encryption key from options database.', 'CunningCaptcha'));

    $key = base64_decode($key); /* Get binary key */

    if (32 != ($key_size = strlen($key)))
        wp_die(__('Encryption error: Invalid keysize.', 'CunningCaptcha'));

    # Create random IV to use with CBC mode.
    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);

    $ciphertext = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $plaintext, MCRYPT_MODE_CBC, $iv);

    # Prepend the IV on the ciphertext for decryption (Must not be confidential).
    $ciphertext = $iv . $ciphertext;

    # Encode such that it can be represented as astring.
    return base64_encode($ciphertext);
}

/**
 * Decrypt using AES_256 with the IV prepended on base64_encoded ciphertext.
 */
function ccaptcha_decrypt($ciphertext) {
    if (false == ($key = get_option('ccpatcha_encryption_key')))
        wp_die(__('Decryption error: could not retrieve encryption key from options database.', 'CunningCaptcha'));

    $key = base64_decode($key); /* Get binary key */

    $ciphertext = base64_decode($ciphertext);

    $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC);
    $iv = substr($ciphertext, 0, $iv_size);
    $ciphertext = substr($ciphertext, $iv_size);

    $plaintext = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $ciphertext, MCRYPT_MODE_CBC, $iv);
    return $plaintext;
}

/*
 * Adds new captcha images to the pool.
 */

function ccaptcha_feed_pool() {
    /* First of all check if pnmtopng is available on the system */

    $handle = popen('/usr/bin/which pnmtopng 2>&1', 'r');
    $read = fread($handle, 2096);
    pclose($handle);
    if (!file_exists(trim($read))) {
        print("pnmtopng is not installed on the system.");
        return false;
    }

    /* Check if target dir exists, if not, create it */
    if (!file_exists(trailingslashit(TARGET_DIR)) && !is_dir(trailingslashit(TARGET_DIR))) {
        if (!mkdir(trailingslashit(TARGET_DIR), $mode = 0755))
            print("Couldn't create image directory");
        return false;
    }

    /* If there are no png files in the target directory, unset the option */
    if (false === strpos(implode('', array_values(scandir(trailingslashit(TARGET_DIR)))), 'png')) {
        echo "deleted options";
        delete_option('ccaptcha_path_captcha_a');
    }

    $captchas = cclib_generateCaptchas($path = trailingslashit(TARGET_DIR), $number = 10, $captchalength = 5);

    /* Convert them to png using pnmtopng */
    foreach (array_keys($captchas) as $path) {
        $path = escapeshellarg($path);
        system(sprintf("pnmtopng %s > %s && rm %s;", $path . '.ppm', $path . '.png', $path . '.ppm'));
    }
    /*
     * If the pool size is now too large, delete the superfluous (redundant) files from the directory
     * and the options database.
     */
    $cnt = 0;
    foreach (glob(trailingslashit(TARGET_DIR) . "*.png") as $filename) {
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
        $savedcaptchas = get_option('ccaptcha_path_captcha_a'); /* This array cannot (should't) be empty */
        foreach (range(0, $num_to_delete - 1) as $i) {
            unlink($keys[$i]);
            unset($savedcaptchas[rtrim($keys[$i], '.png')]);
        }
        /* Synchronize the deletions with the options database */
        if (false === update_option('ccaptcha_path_captcha_a', $savedcaptchas)) {
            /* update failed or the option didn't change */
        }
    }

    $savedcaptchas = get_option('ccaptcha_path_captcha_a');

    if (!$savedcaptchas) // Create
        update_option('ccaptcha_path_captcha_a', $captchas);
    else { // Add
        update_option('ccaptcha_path_captcha_a', array_merge($savedcaptchas, $captchas));
    }

    /* Check if the directory and the options database are synchronized */
    $a = get_option('ccaptcha_path_captcha_a');
    foreach (array_keys($a) as $key)
        if (!file_exists($key . '.png'))
            wp_die("Options database doesn't match with FS");

    D($a);

    return true;
}
?>
