<?php
/*
Plugin Name: CunningCaptcha
Plugin URI: http://incolumitas.com
Description: Simple/easy captcha to prevent spam sneaking in your blog.
Version: 0.2
Author: Nikolai Tschacher
Author URI: http://incolumitas.com
License: GPLv2 or later
*/

/*  Copyright 2013  Nikolai Tschacher (email : admin@incolumitas.com)

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
 *  A really simplistic approach to eliminate my spam problem on my
 *  website. Generates a captcha consisting of 4 figures and inserts
 *  some noise to prevent it from being cracked by skids.
 *  I think the effort of cracking it wouldn't take longer than 3 hours
 *  for an intermediate programmer. Its efectiveness lies in the captchas
 *  uniqueness. Generally, security by obfuscation is a bad idea. At least I 
 *  know that :) When somebody tries to crack it, I will harden it. As long
 *  as you give up...
 * 
 *  Major update in 26.07.2013:
 *  - Add letter Y and Q because they are so beautiful :)
 *  - Implement cronjob functionality: Each 6 hours, generate 250 captcha images. Then, for
 *    the following 6 hours, use randomly a image from the pool. Name the images randomly such that 
 *    a cracker cannot match the solution to the files and thus circumwent the whole captcha wall.
 *    Reason: Safe ressources. It's memory heavy, to generate a new captcha for every damn request :D
 * - Cleanup of the code.
 */

// http://wpengineer.com/2214/adding-input-fields-to-the-comment-form/
// http://wpengineer.com/2205/comment-form-hooks-visualized/

define("CCAPTCHA_DEBUG", true);

if (CCAPTCHA_DEBUG) {
  error_reporting(E_ALL);
  ini_set('display_errors', true);
}

define("FIGURE_SIZE", 30); /* The size of a char in pixels. */
define("NUM_BRUSH_CHANGES_MAX", 3);
define("PPM_FILE", plugin_dir_path(__FILE__)."gen.ppm");
define("CAPTCHA_PNG", plugin_dir_path(__FILE__)."captcha.png");

/* Apply intercepting logic with wordpress API */

// Set a filter to add additional input fields for the comment
add_filter('comment_form_defaults', 'ccaptcha_comment_form_defaults');
// Add a filter to verify if the captch was correct
add_filter('preprocess_comment', 'ccaptcha_check');
// Add a action hook to add the additioal field to the db
add_action('comment_post', 'ccaptcha_save_input');

function ccaptcha_save_input($comment_id) {
    add_comment_meta($comment_id,
            'ccaptcha', strip_tags($_POST['ccaptcha']));
}

function ccaptcha_check($commentdata) {
    global $current_user;
    get_currentuserinfo();
    $uid = $current_user->ID;

    if ($uid != 1) {
        if (!isset($_POST['ccaptcha']))
          wp_die(__('Error: You need to enter the captcha.', 'ccaptcha'));

        $answer = strip_tags($_POST['ccaptcha']);
        $generated = get_option('ccaptcha');

        if (strcasecmp($answer, $generated) != 0)
          wp_die(__('Error: Your supplied captcha is incorrect.', 'ccaptcha'));
    }
    return $commentdata;
}

/*
 * Create the captcha and store the string in the database.
 */
function ccaptcha_comment_form_defaults($default) {
    $answer = implode('', ccaptcha_generate());
    
    // Well, that is ugly, but how else?
    if (!get_option('ccaptcha'))
        add_option('ccaptcha', $answer);
    else
        update_option('ccaptcha', $answer);
    
    if (!is_user_logged_in()) {
        $default['fields']['email'] .= 
            '<img id="captcha_image" src="'.__(plugin_dir_url(__FILE__)).'captcha.png">
            <p class="comment-form-captcha">
            <label for="captcha">'.__('Captcha'). '</label>
            <input id="ccaptcha" name="ccaptcha" size="30" type="text" /></p>';
    }
    return $default;
}

/*** CunningCaptcha Logic begins ***/

function ccaptcha_generate() {

  $h = fopen(PPM_FILE, "w");

  if (!$h) {
	var_dump(error_get_last());
	wp_die(__('Error: fopen() failed.', 'ccaptcha'));
  }
  
  $figures = array();
  $str = array();
  for ($index = 0; $index < 7; $index++) {
      $choose = rand(0, 4);

      switch ($choose) {
          case 0:
            $figures[$index] = get_1(); $str[$index] = '1';
            break;
          case 1:
            $figures[$index] = get_7(); $str[$index] = '7';
            break;
          case 2:
            $figures[$index] = get_E(); $str[$index] = 'E';
            break;
          case 3:
            $figures[$index] = get_Z(); $str[$index] = 'Z';
            break;
          case 4:
            $figures[$index] = get_Q(); $str[$index] = 'Q';
            break;
          case 5:
            $figures[$index] = get_Y(); $str[$index] = 'Y';
            break;
          default:
            break;
      }
  }
  
  $captcha = glue_figures($figures);
  
  $width = FIGURE_SIZE * count($figures);
  $height = count($captcha);
  
  /* write the ppm header */
  if (!fwrite($h, "P3\n$width $height\n255\n")) {
    wp_die(__('Error: fwrite ppm header failed.', 'ccaptcha'));
  }

  for ($i = 0; $i < $height; $i++) {
    for ($j = 0; $j < $width; $j++) {
         fwrite($h, $captcha[$i][$j]."\t");
    }
    fwrite($h, "\n");
  }

  if (!fclose($h)) {
      wp_die(__('Error: Couldn\'t close file.', 'ccaptcha'));
  }
  
  /* 
   * Convert to png and remove ppm. Assumes you're runnin some kind
   * of linux system and that you have pnmtopng installed.
   */
  system(sprintf("pnmtopng %s > %s && rm %s;",
                PPM_FILE, CAPTCHA_PNG, PPM_FILE));
  
  return $str;
}

/*
 * Glue the figures/letters together. Expects an array of figures. Returns
 * a single array representing the bitmap, ready to print...
 */
function glue_figures($array_figures) {
    $captcha = array(array());
    $off = 0;
    
    $pad_size = rand(8, 16);
    if ($pad_size % 2 != 0)
        $pad_size -= 1; // make even
    
    $pad_size /= 2;
    $shift = 0;
    
    for ($index = 0; $index < count($array_figures); $index++) {
      $shift = rand(0, $pad_size);
      for ($i = 0; $i < FIGURE_SIZE+$pad_size*2; $i++) {
        for ($j = 0; $j < FIGURE_SIZE; $j++) {
          $off = FIGURE_SIZE * $index + $j;
          $captcha[$i][$off] = rand_grey();
          if ($i > $pad_size && $i < FIGURE_SIZE+$pad_size) {
            $captcha[$i-$shift][$off] = $array_figures[$index][$i-$pad_size][$j];
          }
        }
      }
    }
    return $captcha;
}

/* Get a random grey scale color to make some noise :) */
function rand_grey() {
    $grey = rand(0, 180);
    return sprintf("%s %s %s", $grey, $grey, $grey);
}

/* Get a random color */
function rand_color() {
    return sprintf
    (
            "%s %s %s",
            rand(0, 255),
            rand(0, 255),
            rand(0, 255)
    );
}

function get_1() {
  $one = array(array());
  // Apply a random shift
  $r_offset = rand(0, FIGURE_SIZE/2);
  // the number of changing the brush color of the figure
  $n_color_changes = FIGURE_SIZE / 
            (rand(0, NUM_BRUSH_CHANGES_MAX) + 1);
  // Get a random brush
  $brush = rand_color();

  for ($i = 0; $i < FIGURE_SIZE; $i++) {
    if ($i % $n_color_changes == 0)
        $brush = rand_color();
    
    for ($j = 0; $j < FIGURE_SIZE; $j++) {

    // Fill the rest with greyscale colors
    $one[$i][$j] = rand_grey(); 
    
    // The tree of the '1'
    if ($j == FIGURE_SIZE-1-$r_offset || 
        $j == FIGURE_SIZE-2-$r_offset) {
       $one[$i][$j] = $brush;
    }

    // The hook of the '1'
    if (($i + $j == FIGURE_SIZE-1-$r_offset && $i < (FIGURE_SIZE-1)/2) ||
        ($i+1 + $j == FIGURE_SIZE-1-$r_offset && $i < (FIGURE_SIZE-1)/2))
        $one[$i][$j] = $brush;
    }
  }
  return $one;
}

function get_7() {
  $seven = array(array());
  // Apply a random shift
  $r_offset = rand(0, FIGURE_SIZE/2);
  // the number of changing the brush color of the figure
  $n_color_changes = FIGURE_SIZE / 
            (rand(0, NUM_BRUSH_CHANGES_MAX) + 1);
  // Get a random brush
  $brush = rand_color();
  
  for ($i = 0; $i < FIGURE_SIZE; $i++) {
    if ($i % $n_color_changes == 0)
        $brush = rand_color();
    
    for ($j = 0; $j < FIGURE_SIZE; $j++) {
        
      // Fill the rest with greyscale colors
      $seven[$i][$j] = rand_grey();
      
      // The roof of the '7'
      if (($i == 0 || $i == 1) &&
              $j > (FIGURE_SIZE-1)/2)
        $seven[$i][$j-$r_offset] = $brush;
      
      // The tree of the '7'
      if (  ($i/2 + $j) == FIGURE_SIZE-1-$r_offset      ||
            ($i/2 + $j-1) == FIGURE_SIZE-1-$r_offset    ||
            (($i+1)/2 + $j) == FIGURE_SIZE-1-$r_offset  ||
            (($i+1)/2 + $j-1) == FIGURE_SIZE-1-$r_offset )
        $seven[$i][$j] = $brush;
    }
  }
  return $seven;
}

function get_Z() {
  $z = array(array());
  // Apply a random shift
  $r_offset = rand(0, FIGURE_SIZE/2);
  // the number of changing the brush color of the figure
  $n_color_changes = FIGURE_SIZE / 
            (rand(0, NUM_BRUSH_CHANGES_MAX) + 1);
  // Get a random brush
  $brush = rand_color();
  
  for ($i = 0; $i < FIGURE_SIZE; $i++) {
    if ($i % $n_color_changes == 0)
        $brush = rand_color();
    
    for ($j = 0; $j < FIGURE_SIZE; $j++) {
        
      // Fill the rest with greyscale colors
      $z[$i][$j] = rand_grey();
      
      // The roof and soil of the 'Z'
      if ((($i == 0 || $i == 1) || 
          ($i == FIGURE_SIZE-1 || $i == FIGURE_SIZE-2)) &&
           $j > (FIGURE_SIZE-1)/2)
        $z[$i][$j-$r_offset] = $brush;
      
      // The tree of the 'Z'
      if (  ($i/2 + $j) == FIGURE_SIZE-1-$r_offset      ||
            ($i/2 + $j-1) == FIGURE_SIZE-1-$r_offset    ||
            (($i+1)/2 + $j) == FIGURE_SIZE-1-$r_offset  ||
            (($i+1)/2 + $j-1) == FIGURE_SIZE-1-$r_offset )
        $z[$i][$j] = $brush;
    }
  }
  return $z; 
}

function get_E() {
  $e = array(array());
  // Apply a random shift
  $r_offset = rand(0, FIGURE_SIZE/2);
  // the number of changing the brush color of the figure
  $n_color_changes = FIGURE_SIZE / 
            (rand(0, NUM_BRUSH_CHANGES_MAX) + 1);
  // Get a random brush
  $brush = rand_color();
  
  for ($i = 0; $i < FIGURE_SIZE; $i++) {
    if ($i % $n_color_changes == 0)
        $brush = rand_color();
    
    for ($j = 0; $j < FIGURE_SIZE; $j++) {     
        
      // Fill the rest with greyscale colors
      $e[$i][$j] = rand_grey();
      
      // The left vertical bar
      if ($j == FIGURE_SIZE/2-$r_offset ||
          $j == FIGURE_SIZE/2-1-$r_offset)
        $e[$i][$j] = $brush;
      
      // The three balks of the 'E'
      if ((($i == 0 || $i == 1) || 
          ($i == FIGURE_SIZE-1 || $i == FIGURE_SIZE-2) ||
          ($i == FIGURE_SIZE/2-1 || $i == FIGURE_SIZE/2-2))
          /* prevent out of bounds indices */
          && $j > (FIGURE_SIZE-1)/2)
        $e[$i][$j-$r_offset] = $brush;
    }
  }
  return $e;
}

function get_Q() {
  $q = array(array());
  // Apply a random shift
  $r_offset = rand(0, FIGURE_SIZE/2);
  // the number of changing the brush color of the figure
  $n_color_changes = FIGURE_SIZE / 
            (rand(0, NUM_BRUSH_CHANGES_MAX) + 1);
  // Get a random brush
  $brush = rand_color();
  
  /* The horizontal middle */
  $h_middle = FIGURE_SIZE/2-$r_offset;
  $l = $r = $h_middle;
  
  for ($i = 0; $i < FIGURE_SIZE; $i++) { /* $i indicates the Y axis */
    if ($i % $n_color_changes == 0)
        $brush = rand_color();
    
    for ($j = 0; $j < FIGURE_SIZE; $j++) { /* $j X axis parameter */

        /* 
         * To draw the oval circle, we need some functionality that increases
         * until the middle of the vertical height of the picture is reached. Then it
         * should shrink until it collapses in the end-point. Or let's learn how
         * to to it the good way: http://en.wikipedia.org/wiki/Midpoint_circle_algorithm
         */
        // Fill the rest with greyscale colors
        $q[$i][$j] = rand_grey();

        if ($j == $l) { $q[$i][$j] = $brush; }
        if ($j == $r) { $q[$i][$j] = $brush; }
    }
    
    $l = ($i < floor(FIGURE_SIZE/2)) ? --$l : ++$l;
    $r = ($i < floor(FIGURE_SIZE/2)) ? ++$r : --$r;
  }
  return $q;
}
?>