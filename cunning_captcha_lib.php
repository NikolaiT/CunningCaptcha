<?php

//D(cclib_generateCaptchas($path='/tmp', $number=5));
//cclib_speedtest();

/* 
 * Classes that plot captchas without any dependencies. All pure PHP without using
 * any first, second or third class image processing libraries.
 * 
 * @@Version: 0.1
 * @@Author: Nikolai Tschacher
 * @@Date: October/November 2013
 * @@Contact: incolumitas.com
 */

error_reporting(E_ALL);

/* ------------------- This is the function you are going to use ------------------- */

/*
 * $path The path to the folder where the resulting captch images are generated. WITH TRAILING SLASH!
 * $number The number of unique captchas to generate.
 * $captchalength The length of the captcha string.
 * 
 * return value This function returns an array of full path names to the captcha as key and 
 * 				it's according captcha word (That what the user has to enter) as value.
 * 				The captcha base name is a (pretty) random string of length 12, such that
 * 				users can't download all captchas. This means that there needs to be a
 * 				upper limit of reloads before an IP address get's blocked from inspecting
 * 				other captchas (5 for example).
 */
function cclib_generateCaptchas($path='', $number=10, $captchalength=5) {
	if (is_dir($path) === false)
		throw new Exception("Path $path is not a valid directory.");
	else if (is_writable($path) === false)
		throw new Exception("Path $path is not writable.");
	
	/* Generate the captchas */
	$captcha = CunningCaptcha::get_instance($clength=$captchalength);
	foreach (range(0, $number) as $i) {
		$fname = sprintf("%s%s", $path, substr(sha1(cclib_random_string().$i), 4, 18));
		$captchas[$fname] = $captcha->reload($fname);
	}
	return $captchas;
}

/* ------------------- From here on comes internal stuff, that you shouldn't change ------------------- */

/* 
 * .
 * .
 * .
 * .
 */

/* Some handy debugging functions. Send me a letter for Christmas! */
function D($a) {
	print "<pre>";
	print_r($a);
	print "</pre>";
}

/* 
 * Shuffles an array while preserving key=>value pairs. Taken from php.net 
 */
function cclib_shuffle_assoc(&$array) {
	$keys = array_keys($array);
	
	shuffle($keys);
	
	foreach ($keys as $key) {
		$new[$key] = $array[$key];
	}
	$array = $new;
	
	return true;
}

/*
 * Generate a random string. Foundn on stackoverflow.com
 * Do not fucking use for crypto related stuff. rand() is not a secure PRNG.
 */
function cclib_random_string($length=15) {
	$keys = array_merge(range(0,9), range('a', 'z'));
	$key = '';
	for ($i=0; $i < $length; $i++) {
		$key .= $keys[array_rand($keys)];
	}
	return $key;
}

/*
 * Generates cryptographically secure random numbers within the range
 *      integer $start : start of the range
 *      integer $stop : end of the range.
 * 
 *      Both parameters need to be positive. If you need a negative random value, just pass positiv values
 *      to the function and then make the return value negative on your own.
 * 
 *      return value: A random integer within the range (including the edges). If the function returns False, something
 *      went wrong. Always check for false with "===" operator, otherwise a fail might shadow a valid
 *      random value: zero. You can pass the boolean parameter $secure. If it is true, the random value is 
 *      cryptographically secure, else it was generated with rand().
 */
function cclib_secure_random_number($start, $stop, &$secure="True") {
	static $calls = 0;
	
    if ($start < 0 || $stop < 0 || $stop < $start)
        return False;
    
    /* Just look for a random value within the difference of the range */
    $range = abs($stop - $start);
    
    $format = '';
    if ($range < 256)
        $format = 'C';
    elseif ($range < 65536)
        $format = 'S';
    elseif ($range >= 65536 && $range < 4294967296)
        $format = 'L';
    
    /* Get a blob of cryptographically secure random bytes */
    $binary = openssl_random_pseudo_bytes(8192, $crypto_strong);
    if ($crypto_strong == False)
        throw new UnexpectedValueException("openssl_random_bytes has no secure PRNG");
    
    /* unpack data into determined format */
    $data = unpack($format.'*', $binary);
    if ($data == False)
        return False;
    
    foreach ($data as $value) {
        $value = intval($value, $base=10);
        if ($value <= $range) {
            $secure = True;
            return ($start + $value);
	}
    }
    
    $calls++;
    if ($calls >= 50) { /* Fall back to rand() if the numbers of recursive calls exceed 50 */
        $secure = False;
        return rand($start, $stop);
    } else /* If we could't locate integer in the range, try again as long as we do not try more than 50 times. */
        return cclib_secure_random_number($start, $stop, $secure);
}
 
/* 
 * Test the speed of the Bézier plotting functions. 
 */
function cclib_speedtest() {
	for ($i = 0; $i < 500; $i++) {
		$splines[] = array(new CunningPoint(rand(1,500), rand(1,500)), new CunningPoint(rand(1,500), rand(1,500)), new CunningPoint(rand(1,500), rand(1,500)), new CunningPoint(rand(1,500), rand(1,500)));
	}
	$g = CunningGlyph::get_instance();
	$start = microtime(true);
	foreach ($splines as $spline) {
		$g->spline($spline, $algo="approx");
		//$g->line(array(new CunningPoint(rand(1,500), rand(1,500)),new CunningPoint(rand(1,500), rand(1,500))));s
	}
	$end = microtime(true);
	printf("Completed speedtest in %.6f seconds<br />", $end-$start);
}

/* 
 * A simple class to represent points. Public members, since working with
 * getters and setters is too pendantic in this context.
 */
class CunningPoint {
	public $x;
	public $y;
	
	public function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
	
	public function __toString() {
		return 'CunningPoint(x='.$this->x.', y='.$this->y.')';
	}
}

/* 
 * I will NOT implement seperate data structures (classes) for splines and lines, 
 * because it just doesn't make sense. It would make things unnecessary complex and 
 * slow. Lines are just arrays of two points. Quadratic Bézier splines arrays of 
 * three points and cubic Bézier splines respectively arrays of 4 points.
 */

/*
 * Describes the class CunningCanvas which implements algorithms to rasterize geometrical primitives such
 * as quadratic and cubic Bézier splines and straight lines. There may be more than one algorithm for each
 * spline. They differ mostly in performance and smothness of drawing.
 */
 
abstract class CunningCanvas {
	
	const STEP = 0.001;
	const NUM_SEGMENTS = 15;
        const DEFAULT_COLOR = 0;
	
	protected $width;
	protected $height;
	private $bitmap; /* No class that inherits from the CunningCanvas should be able to 
					  * manipulate the bitmap of the CunningCanvas expect through the
					  * geometrical primitive methods and set_pixel().
					  */
	
	/* Lookup-tables for Bézier coefficients */
	private $quad_lut;
	private $cub_lut;
	
	protected function __construct($width=100, $height=100) {
		$this->height = $height;
		$this->width = $width;
		$this->initbm();
	}
	
	/* All classes that extend CunningCanvas need to draw something */
	abstract protected function draw();
	
	protected function initbm() {
		unset($this->bitmap);
	}
	
	public function get_bitmap() {
		return $this->bitmap;
	}
	
	public function get_width() {
		return $this->width;
	}
	
	public function get_height() {
		return $this->height;
	}
	
	protected final function set_pixel($x, $y, $color=self::DEFAULT_COLOR) {
		$this->bitmap[$y][$x] = $color;
	}
	
	/* 
	 * Copy the the two dimensional array with the
	 * offset given by $dx and $dy into the bitmap.
	 * There might be a built-in function for this task such as array_merge()
	 * or something that is better.
	 */
	protected final function merge($dy=0, $dx=0, $array2d) {
		/* If it fits I sits */
		foreach ($array2d as $i => $row) { // $i is the row-index
			foreach ($row as $j => $pixel) { // $j is the column index
				/* Check wheter the glyph fits */
				if ($i+$dy > $this->height || $j+$dx > $this->width)
					return False;
				$this->bitmap[$i+$dy][$j+$dx] = self::DEFAULT_COLOR;
			}
		}
	}
	
        /**
         * Classmethod to get the derivative of a second and third degree bezier curve for
         * the x and y component.
         * 
         * @param type $spline
         * @param type $t
         * @return array The derivative of the x and y axis of the given spline.
         * 
         * linear = (1-t)+t
         * square = (1-t)**2 + 2(1-t)*t + t**2
         * cubic = (1-t)**3 + 3*(1-t)**2*t + 3*(1-t)*t**2 + t**3
         */
        static final function bezier_derivative($spline, $t) {
            /* Quadratic curves */
            if (count($spline == 3)) {
                return array(
                        "x_der" => (2*($spline[1]->x-$spline[0]->x))*(1-$t) + (2*($spline[2]->x-$spline[1]->x))*$t,
                        "y_der" => (2*($spline[1]->y-$spline[0]->y))*(1-$t) + (2*($spline[2]->y-$spline[1]->y))*$t
                    );
            } elseif(count($spline) == 4) { /* Cubic curves */
                return array(
                        "x_der" => (3*($spline[1]->x-$spline[0]->x))*(1-$t)*(1-$t) + (3*($spline[2]->x-$spline[1]->x))*2*(1-$t)*$t + (3*($spline[3]->x-$spline[2]->x))*$t*$t,
                        "y_der" => (3*($spline[1]->y-$spline[0]->y))*(1-$t)*(1-$t) + (3*($spline[2]->y-$spline[1]->y))*2*(1-$t)*$t + (3*($spline[3]->y-$spline[2]->y))*$t*$t
                    );
            }
            /* No derivatives of higher degree curves */
            return False;
        }
        /**
         * Compute the Bezier point for a given t value.
         * 
         * @param type $spline
         * @param type $t
         * @return array The sum for the x and y component of the given spline.
         */
        static final function bezier_sum($spline, $t) {
            /* Quadratic curves */
            if (count($spline == 3)) {
                $t2 = $t*$t;
                $mt = 1-$t;
                $mt2 = $mt*$mt;
                $x = intval($spline[0]->x*$mt2 + $spline[1]->x*2*$mt*$t + $spline[2]->x*$t2);
                $y = intval($spline[0]->y*$mt2 + $spline[1]->y*2*$mt*$t + $spline[2]->y*$t2);
                return array("x_sum" => $x, "y_sum" => $y);
                
            } elseif(count($spline) == 4) { /* Cubic curves */
                $t2 = $t*$t;
                $t3 = $t2 * $t;
                $mt = 1-$t;
                $mt2 = $mt * $mt;
                $mt3 = $mt2 * $mt;
                $x = intval($spline[0]->x*$mt3 + 3*$spline[1]->x*$mt2*$t + 3*$spline[2]->x*$mt*$t2 + $spline[3]->x*$t3);
                $y = intval($spline[0]->y*$mt3 + 3*$spline[1]->y*$mt2*$t + 3*$spline[2]->y*$mt*$t2 + $spline[3]->y*$t3);
                return array("x_sum" => $x, "y_sum" => $y);
            }
            /* No computation of higher degree curves */
            return False;
        }
        
        /**
         * Find the roots of 3 and 4th degree bezier curve using newton-raphson root finding.
         * 
         * @param type $spline
         */
        static final function bezier_root($spline, $coord="x") {
            $APPROX_EPSILON = 0.000001;

            for ($t = 1; $t < 200; $t++) {
                $t /= 200;
                $tn = $t;
                $cnt = 0;
                while (True) {
                    if ($cnt > 50)
                        return $tn;
                        //throw new Exception ("Newton-Raphson doesn't seem to find anything.");
                    
                    $d = self::bezier_derivative($spline, $tn);
                    $s = self::bezier_sum($spline, $tn);
                    
                    if ($d["x_der"] != 0 && $d["y_der"] != 0) {
                        //echo "In round $cnt, B($tn) = ".$s["x_sum"]."<br />";
                        /* Check if we are under the threshold */
                        if ($coord == "x") if (($s["x_sum"] < 0) ? $s["x_sum"] > -$APPROX_EPSILON : $s["x_sum"] < $APPROX_EPSILON) return $tn;
                        if ($coord == "y") if (($s["y_sum"] < 0) ? $s["y_sum"] > -$APPROX_EPSILON : $s["y_sum"] < $APPROX_EPSILON) return $tn;

                        if ($coord == "x")
                            $t_n1 = $tn - ($s["x_sum"]/$d["x_der"]);
                        elseif($coord == "y")
                            $t_n1 = $tn - ($s["y_sum"]/$d["y_der"]);
                     
                        $tn = $t_n1;
                        //printf("Calculating next round with t=$t and root=$tn <br />");
                    }
                    $cnt++;
                }
            }
        }
        
        
	/* 
	 * All the different rasterization algorithms. They differ in performance and 
	 * granularity of the resulting splines as well as in the smoothness of the curve.
	 */
	 
	/* 
	 * The next two functions calculate the quadratic and cubic bezier points directly.
	 */
	private function _direct_quad_bez($p1, $p2, $p3) {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$x = intval($p1->x*$mt2 + $p2->x*2*$mt*$t + $p3->x*$t2);
			$y = intval($p1->y*$mt2 + $p2->y*2*$mt*$t + $p3->y*$t2);
			$this->set_pixel($x, $y);
			$t += self::STEP;
		}
	}
	
	private function _direct_cub_bez($p1, $p2, $p3, $p4) {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$x = intval($p1->x*$mt3 + 3*$p2->x*$mt2*$t + 3*$p3->x*$mt*$t2 + $p4->x*$t3);
			$y = intval($p1->y*$mt3 + 3*$p2->y*$mt2*$t + 3*$p3->y*$mt*$t2 + $p4->y*$t3);
			$this->set_pixel($x, $y);
			$t += self::STEP;
		}
	}
	
	/* Bézier plotting with look-up tables. Might still be rather slow. */
	
	private function _gen_quad_LUT() {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$this->quad_lut[] = array($mt2, 2*$mt*$t, $t2);
			$t += self::STEP;
		}
	}
	
	private function _gen_cub_LUT() {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$this->cub_lut[] = array($mt3, 3*$mt2*$t, 3*$mt*$t2, $t3);
			$t += self::STEP;
		}
	}
	
	private function _lut_quad_bez($p1, $p2, $p3) {
		if (!$this->quad_lut)
			$this->_gen_quad_LUT();
			
		foreach ($this->quad_lut as $c) {
			$x = intval($p1->x*$c[0] + $p2->x*$c[1] + $p3->x*$c[2]);
			$y = intval($p1->y*$c[0] + $p2->y*$c[1] + $p3->y*$c[2]);
			$this->set_pixel($x, $y);
		}
	}
	
	private function _lut_cub_bez($p1, $p2, $p3, $p4) {
		if (!$this->cub_lut)
			$this->_gen_cub_LUT();
			
		foreach ($this->cub_lut as $c) {
			$x = intval($p1->x*$c[0] + $p2->x*$c[1] + $p3->x*$c[2] + $p4->x*$c[3]);
			$y = intval($p1->y*$c[0] + $p2->y*$c[1] + $p3->y*$c[2] + $p4->y*$c[3]);
			$this->set_pixel($x, $y);
		}
	}
	
	/* The fastest one. Approximates the curve with simple lines. */
	
	private function _approx_quad_bez($p1, $p2, $p3) {
		$last = $p1;
		for ($i = 0; $i < self::NUM_SEGMENTS; $i++) {
			$t = $i / self::NUM_SEGMENTS;
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$x = intval($p1->x*$mt2 + $p2->x*2*$mt*$t + $p3->x*$t2);
			$y = intval($p1->y*$mt2 + $p2->y*2*$mt*$t + $p3->y*$t2);
			$this->line(array($last, new CunningPoint($x, $y)));
			$last = new CunningPoint($x, $y);
		}
	}

	private function _approx_cub_bez($p1, $p2, $p3, $p4) {
		$last = $p1;
		for ($i = 0; $i < self::NUM_SEGMENTS; $i++) {
			$t = $i / self::NUM_SEGMENTS;
			$t2 = $t * $t;
			$t3 = $t2 * $t;
			$mt = 1-$t;
			$mt2 = $mt * $mt;
			$mt3 = $mt2 * $mt;
			$x = intval($p1->x*$mt3 + 3*$p2->x*$mt2*$t + 3*$p3->x*$mt*$t2 + $p4->x*$t3);
			$y = intval($p1->y*$mt3 + 3*$p2->y*$mt2*$t + 3*$p3->y*$mt*$t2 + $p4->y*$t3);
			$this->line(array($last, new CunningPoint($x, $y)));
			$last = new CunningPoint($x, $y);
		}
	}
	
	private function plot_casteljau($points) {
		foreach ($points as $p) {
			if (get_class($p) != 'CunningPoint')
				return False;
		}
		$t = 0;
		while ($t <= 1) {
			$this->_casteljau($points, $t);
			$t += self::STEP;
		}
	}
	
	/* Recursive, numerically stable implementation for plotting splines */
	private function _casteljau($points, $t) {
		/* Base case */
		if (count($points) == 1)
			$this->set_pixel($points[0]->x, $points[0]->y);
		else {
			$newpoints = array();
			foreach (range(0, count($points)-2) as $i) {
				$x = (1-$t) * $points[$i]->x + $t * $points[$i+1]->x;
				$y = (1-$t) * $points[$i]->y + $t * $points[$i+1]->y;
				$newpoints[] = new CunningPoint($x, $y);
			}
		/* Recursive step */
		$this->_casteljau($newpoints, $t);
		}
	}
	
	public final function line($points) {
		if (count($points) != 2)
			return False;
		
		$x0 = $points[0]->x;
		$y0 = $points[0]->y;
		$x1 = $points[1]->x;
		$y1 = $points[1]->y;
		
		$dx = abs($x1-$x0);
		$dy = -abs($y1-$y0);
		$sx = $x0 < $x1 ? 1 : -1;
		$sy = $y0 < $y1 ? 1 : -1;
		$err = $dx+$dy;
		$e2 = 1;
		while (True) {
			$this->set_pixel($x0, $y0);
			if ($x0 == $x1 and $y0 == $y1)
				break;
			$e2 = 2*$err;
			if ($e2 >= $dy) {
				$err += $dy;
				$x0 += $sx;
			}
			if ($e2 <= $dx) {
				$err += $dx;
				$y0 += $sy;
			}
		}
	}
	
	public final function spline($points, $algo='approx') {
		foreach ($points as $p) {
			if (get_class($p) != 'CunningPoint')
				return False;
		}
		
		if (!in_array($algo, array('direct', 'lut', 'approx', 'casteljau')))
			return False;
		
		/* Somehow ugly but what can you do? 
		 * Send me mail, in case you have a hint: admin [(at)] incolumitas.com
		 */
		$plen = count($points);
		switch ($algo) {
			case 'direct':
				if ($plen == 3)
					$this->_direct_quad_bez($points[0], $points[1], $points[2]);
				if ($plen == 4)
					$this->_direct_cub_bez($points[0], $points[1], $points[2], $points[3]);
				break;
			case 'lut':
				if ($plen == 3)
					$this->_lut_quad_bez($points[0], $points[1], $points[2]);
				if ($plen == 4)
					$this->_lut_cub_bez($points[0], $points[1], $points[2], $points[3]);
				break;
			case 'approx':
				if ($plen == 3)
					$this->_approx_quad_bez($points[0], $points[1], $points[2]);
				if ($plen == 4)
					$this->_approx_cub_bez($points[0], $points[1], $points[2], $points[3]);
				break;
			case 'casteljau':
				$this->plot_casteljau($points);
				break;
			default:
				break;
		}
	}
}

/*
 * The class CunningGlyph represents a generic CunningGlyph. A glyph inherits from the class CunningCanvas.
 * It is very important to differentiate between the parent class property $this->get_bitmap()
 * and the CunningGlyph class property glyphdata! Whereas glyphdata holds all the information to draw
 * a glyph (consisting of Bézier splines and simple lines) the bitmap is just an two dimensional
 * array of pixels that can be manipulated with the methods in the class CunningCanvas(). This means
 * that the most methods in CunningGlyph apply some function on glyphdata, not the bitmap!
 * 
 * This class implements a wide range of different 'blur' techniques that try to confuse computational
 * approaches like OCR to recognize the glyph. Therefore there are linear transformations and a wide range of parameters that
 * are randomly chosen. All these bluring techniques can be applied with the blur() function.
 * Each concrete glyph (like A, b, y, x, Q) inherits from the abstract class CunningGlyph. Each such concrete
 * class initializes the attribute glyphdata with the associative array of lines and bezier splines.
 */
 
class CunningGlyph extends CunningCanvas {
	private static $instance;
	
	private $character;
	private $glyphdata;
	
	private $plotted = False;
	
	protected function __construct($character, $width, $height) {
		if (isset(self::$instance))
			throw new Exception("CunningGlyph is a singleton. Only one instance possible.");
		parent::__construct($width, $height);
		$this->character = $character;
		if ($character !== '')
			$this->load_glyph($character);
	}
	
	public static function get_instance($character='', $width=50, $height=50) { /* The measures of each glyph should be calculated by the using class */
		if (!isset(self::$instance)) {
			self::$instance = new CunningGlyph($character, $width, $height);
		}
		return self::$instance;
	}
	
	/* This function load's the points that constitute the glyph. Maybe it's a design
	 * error, but a worse alternative would be to make n classes for each character where
	 * n = len(alphabet). This would imply a lot of redundant code and unflexible handling
	 */
	 public function load_glyph($c) {
		 unset($this->glyphdata);
		 $this->character = $c;
		/* 
		 * Theres a python function that generates this PHP switch statement,
		 * because the glyphdata may easily change if I redesign the glyph in
		 * the future.
		 * It's certainly a bad idea to mix data with code as done here, but in order
		 * to speed things up, this will have to stay in memory ;)
		 */
		
		/* All CunningGlyphs: y, W, G, a, H, i, f, b, n, S, X, k, E, Q */
		switch ($c) {
			case 'y':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(260, 601), new CunningPoint(260, 601), new CunningPoint(230, 670), new CunningPoint(204, 695)), array(new CunningPoint(204, 695), new CunningPoint(191, 706), new CunningPoint(175, 717), new CunningPoint(158, 719)), array(new CunningPoint(158, 719), new CunningPoint(135, 722), new CunningPoint(88, 698), new CunningPoint(88, 698)), array(new CunningPoint(82, 715), new CunningPoint(82, 715), new CunningPoint(131, 737), new CunningPoint(155, 735)), 
						array(new CunningPoint(82, 715), new CunningPoint(82, 715), new CunningPoint(131, 737), new CunningPoint(155, 735)), array(new CunningPoint(155, 735), new CunningPoint(175, 733), new CunningPoint(194, 721), new CunningPoint(210, 708)), array(new CunningPoint(210, 708), new CunningPoint(240, 681), new CunningPoint(257, 642), new CunningPoint(277, 606)), array(new CunningPoint(277, 606), new CunningPoint(317, 534), new CunningPoint(378, 372), new CunningPoint(378, 372)),
						array(new CunningPoint(277, 606), new CunningPoint(317, 534), new CunningPoint(378, 372), new CunningPoint(378, 372))
					),
					'lines' => array(
						array(new CunningPoint(112, 375), new CunningPoint(147, 375)), array(new CunningPoint(147, 375), new CunningPoint(260, 601)), array(new CunningPoint(88, 698), new CunningPoint(82, 715)), array(new CunningPoint(378, 372), new CunningPoint(429, 372)), 
						array(new CunningPoint(378, 372), new CunningPoint(429, 372)), array(new CunningPoint(429, 372), new CunningPoint(429, 356)), array(new CunningPoint(429, 356), new CunningPoint(321, 356)), array(new CunningPoint(321, 356), new CunningPoint(321, 372)), 
						array(new CunningPoint(321, 356), new CunningPoint(321, 372)), array(new CunningPoint(321, 372), new CunningPoint(360, 372)), array(new CunningPoint(360, 372), new CunningPoint(271, 585)), array(new CunningPoint(271, 585), new CunningPoint(163, 375)), 
						array(new CunningPoint(271, 585), new CunningPoint(163, 375)), array(new CunningPoint(163, 375), new CunningPoint(217, 374)), array(new CunningPoint(217, 374), new CunningPoint(217, 356)), array(new CunningPoint(217, 356), new CunningPoint(112, 356)), 
						array(new CunningPoint(217, 356), new CunningPoint(112, 356)), array(new CunningPoint(112, 356), new CunningPoint(112, 375))
					)
				);
				break;
			case 'W':
				$this->glyphdata = array(
					'lines' => array(
						array(new CunningPoint(70, 322), new CunningPoint(200, 712)), array(new CunningPoint(200, 712), new CunningPoint(260, 712)), array(new CunningPoint(260, 712), new CunningPoint(340, 442)), array(new CunningPoint(340, 442), new CunningPoint(420, 712)), 
						array(new CunningPoint(340, 442), new CunningPoint(420, 712)), array(new CunningPoint(420, 712), new CunningPoint(480, 712)), array(new CunningPoint(480, 712), new CunningPoint(590, 322)), array(new CunningPoint(590, 322), new CunningPoint(500, 332)), 
						array(new CunningPoint(590, 322), new CunningPoint(500, 332)), array(new CunningPoint(500, 332), new CunningPoint(450, 612)), array(new CunningPoint(450, 612), new CunningPoint(370, 402)), array(new CunningPoint(370, 402), new CunningPoint(310, 402)), 
						array(new CunningPoint(370, 402), new CunningPoint(310, 402)), array(new CunningPoint(310, 402), new CunningPoint(230, 612)), array(new CunningPoint(230, 612), new CunningPoint(160, 332)), array(new CunningPoint(160, 332), new CunningPoint(70, 322)),
						array(new CunningPoint(160, 332), new CunningPoint(70, 322))
					)
				);
				break;
			case 'G':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(516, 306), new CunningPoint(516, 306), new CunningPoint(479, 274), new CunningPoint(457, 263)), array(new CunningPoint(457, 263), new CunningPoint(437, 253), new CunningPoint(414, 246), new CunningPoint(392, 250)), array(new CunningPoint(392, 250), new CunningPoint(366, 254), new CunningPoint(342, 271), new CunningPoint(324, 291)), array(new CunningPoint(324, 291), new CunningPoint(301, 316), new CunningPoint(288, 349), new CunningPoint(280, 381)), 
						array(new CunningPoint(324, 291), new CunningPoint(301, 316), new CunningPoint(288, 349), new CunningPoint(280, 381)), array(new CunningPoint(280, 381), new CunningPoint(271, 421), new CunningPoint(268, 464), new CunningPoint(280, 503)), array(new CunningPoint(280, 503), new CunningPoint(289, 533), new CunningPoint(307, 561), new CunningPoint(332, 579)), array(new CunningPoint(332, 579), new CunningPoint(351, 593), new CunningPoint(376, 598), new CunningPoint(400, 598)), 
						array(new CunningPoint(332, 579), new CunningPoint(351, 593), new CunningPoint(376, 598), new CunningPoint(400, 598)), array(new CunningPoint(400, 598), new CunningPoint(435, 599), new CunningPoint(504, 590), new CunningPoint(504, 590)), array(new CunningPoint(475, 543), new CunningPoint(476, 572), new CunningPoint(449, 570), new CunningPoint(426, 567)), array(new CunningPoint(426, 567), new CunningPoint(398, 563), new CunningPoint(342, 547), new CunningPoint(326, 524)), 
						array(new CunningPoint(426, 567), new CunningPoint(398, 563), new CunningPoint(342, 547), new CunningPoint(326, 524)), array(new CunningPoint(326, 524), new CunningPoint(306, 494), new CunningPoint(307, 454), new CunningPoint(311, 420)), array(new CunningPoint(311, 420), new CunningPoint(314, 382), new CunningPoint(324, 341), new CunningPoint(348, 311)), array(new CunningPoint(348, 311), new CunningPoint(364, 293), new CunningPoint(387, 280), new CunningPoint(412, 282)), 
						array(new CunningPoint(348, 311), new CunningPoint(364, 293), new CunningPoint(387, 280), new CunningPoint(412, 282)), array(new CunningPoint(412, 282), new CunningPoint(445, 284), new CunningPoint(492, 330), new CunningPoint(492, 330)), array(new CunningPoint(492, 330), new CunningPoint(492, 330), new CunningPoint(486, 338), new CunningPoint(516, 306))
					),
					'lines' => array(
						array(new CunningPoint(504, 590), new CunningPoint(506, 448)), array(new CunningPoint(506, 448), new CunningPoint(386, 446)), array(new CunningPoint(386, 446), new CunningPoint(384, 477)), array(new CunningPoint(384, 477), new CunningPoint(475, 477)), 
						array(new CunningPoint(384, 477), new CunningPoint(475, 477)), array(new CunningPoint(475, 477), new CunningPoint(475, 543)), array(new CunningPoint(516, 306), new CunningPoint(516, 306))
					)
				);
				break;
			case 'a':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(315, 20), new CunningPoint(283, 20), new CunningPoint(253, 29), new CunningPoint(227, 47)), array(new CunningPoint(227, 47), new CunningPoint(191, 73), new CunningPoint(180, 182), new CunningPoint(180, 182)), array(new CunningPoint(180, 182), new CunningPoint(193, 197), new CunningPoint(215, 232), new CunningPoint(215, 232)), array(new CunningPoint(215, 232), new CunningPoint(215, 232), new CunningPoint(224, 119), new CunningPoint(249, 92)), 
						array(new CunningPoint(215, 232), new CunningPoint(215, 232), new CunningPoint(224, 119), new CunningPoint(249, 92)), array(new CunningPoint(249, 92), new CunningPoint(281, 51), new CunningPoint(327, 46), new CunningPoint(382, 94)), array(new CunningPoint(382, 94), new CunningPoint(420, 150), new CunningPoint(397, 205), new CunningPoint(365, 248)), array(new CunningPoint(365, 248), new CunningPoint(329, 297), new CunningPoint(271, 294), new CunningPoint(225, 307)), 
						array(new CunningPoint(365, 248), new CunningPoint(329, 297), new CunningPoint(271, 294), new CunningPoint(225, 307)), array(new CunningPoint(225, 307), new CunningPoint(129, 346), new CunningPoint(120, 464), new CunningPoint(143, 541)), array(new CunningPoint(143, 541), new CunningPoint(152, 582), new CunningPoint(173, 610), new CunningPoint(210, 621)), array(new CunningPoint(210, 621), new CunningPoint(270, 638), new CunningPoint(313, 621), new CunningPoint(345, 583)), 
						array(new CunningPoint(210, 621), new CunningPoint(270, 638), new CunningPoint(313, 621), new CunningPoint(345, 583)), array(new CunningPoint(345, 583), new CunningPoint(351, 610), new CunningPoint(352, 640), new CunningPoint(378, 646)), array(new CunningPoint(378, 646), new CunningPoint(418, 654), new CunningPoint(471, 648), new CunningPoint(432, 609)), array(new CunningPoint(432, 609), new CunningPoint(393, 571), new CunningPoint(403, 555), new CunningPoint(401, 530)), 
						array(new CunningPoint(432, 609), new CunningPoint(393, 571), new CunningPoint(403, 555), new CunningPoint(401, 530)), array(new CunningPoint(455, 91), new CunningPoint(459, 63), new CunningPoint(411, 37), new CunningPoint(360, 25)), array(new CunningPoint(360, 25), new CunningPoint(344, 21), new CunningPoint(329, 20), new CunningPoint(315, 20)), array(new CunningPoint(372, 303), new CunningPoint(390, 387), new CunningPoint(371, 555), new CunningPoint(272, 591)), 
						array(new CunningPoint(372, 303), new CunningPoint(390, 387), new CunningPoint(371, 555), new CunningPoint(272, 591)), array(new CunningPoint(272, 591), new CunningPoint(174, 628), new CunningPoint(155, 454), new CunningPoint(192, 404)), array(new CunningPoint(192, 404), new CunningPoint(244, 333), new CunningPoint(298, 299), new CunningPoint(372, 303))
					),
					'lines' => array(
						array(new CunningPoint(401, 530), new CunningPoint(455, 91)), array(new CunningPoint(315, 20), new CunningPoint(315, 20)), array(new CunningPoint(372, 303), new CunningPoint(372, 303))
					)
				);
				break;
			case 'H':
				$this->glyphdata = array(
					'lines' => array(
						array(new CunningPoint(115, 172), new CunningPoint(115, 207)), array(new CunningPoint(115, 207), new CunningPoint(170, 207)), array(new CunningPoint(170, 207), new CunningPoint(170, 692)), array(new CunningPoint(170, 692), new CunningPoint(115, 692)), 
						array(new CunningPoint(170, 692), new CunningPoint(115, 692)), array(new CunningPoint(115, 692), new CunningPoint(115, 722)), array(new CunningPoint(115, 722), new CunningPoint(265, 722)), array(new CunningPoint(265, 722), new CunningPoint(265, 692)), 
						array(new CunningPoint(265, 722), new CunningPoint(265, 692)), array(new CunningPoint(265, 692), new CunningPoint(210, 692)), array(new CunningPoint(210, 692), new CunningPoint(210, 442)), array(new CunningPoint(210, 442), new CunningPoint(440, 442)), 
						array(new CunningPoint(210, 442), new CunningPoint(440, 442)), array(new CunningPoint(440, 442), new CunningPoint(440, 692)), array(new CunningPoint(440, 692), new CunningPoint(380, 692)), array(new CunningPoint(380, 692), new CunningPoint(380, 722)), 
						array(new CunningPoint(380, 692), new CunningPoint(380, 722)), array(new CunningPoint(380, 722), new CunningPoint(535, 722)), array(new CunningPoint(535, 722), new CunningPoint(535, 692)), array(new CunningPoint(535, 692), new CunningPoint(485, 692)), 
						array(new CunningPoint(535, 692), new CunningPoint(485, 692)), array(new CunningPoint(485, 692), new CunningPoint(485, 207)), array(new CunningPoint(485, 207), new CunningPoint(535, 207)), array(new CunningPoint(535, 207), new CunningPoint(535, 172)), 
						array(new CunningPoint(535, 207), new CunningPoint(535, 172)), array(new CunningPoint(535, 172), new CunningPoint(380, 172)), array(new CunningPoint(380, 172), new CunningPoint(380, 207)), array(new CunningPoint(380, 207), new CunningPoint(440, 207)), 
						array(new CunningPoint(380, 207), new CunningPoint(440, 207)), array(new CunningPoint(440, 207), new CunningPoint(440, 402)), array(new CunningPoint(440, 402), new CunningPoint(210, 402)), array(new CunningPoint(210, 402), new CunningPoint(210, 207)), 
						array(new CunningPoint(210, 402), new CunningPoint(210, 207)), array(new CunningPoint(210, 207), new CunningPoint(265, 207)), array(new CunningPoint(265, 207), new CunningPoint(265, 172)), array(new CunningPoint(265, 172), new CunningPoint(115, 172)),
						array(new CunningPoint(265, 172), new CunningPoint(115, 172))
					)
				);
				break;
			case 'i':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(344, 166), new CunningPoint(325, 165), new CunningPoint(305, 179), new CunningPoint(294, 194)), array(new CunningPoint(294, 194), new CunningPoint(283, 210), new CunningPoint(277, 232), new CunningPoint(284, 250)), array(new CunningPoint(284, 250), new CunningPoint(291, 272), new CunningPoint(314, 293), new CunningPoint(337, 295)), array(new CunningPoint(337, 295), new CunningPoint(356, 296), new CunningPoint(376, 282), new CunningPoint(386, 265)), 
						array(new CunningPoint(337, 295), new CunningPoint(356, 296), new CunningPoint(376, 282), new CunningPoint(386, 265)), array(new CunningPoint(386, 265), new CunningPoint(397, 247), new CunningPoint(399, 221), new CunningPoint(390, 202)), array(new CunningPoint(390, 202), new CunningPoint(382, 184), new CunningPoint(363, 168), new CunningPoint(344, 166))
					),
					'lines' => array(
						array(new CunningPoint(300, 379), new CunningPoint(300, 852)), array(new CunningPoint(300, 852), new CunningPoint(373, 852)), array(new CunningPoint(373, 852), new CunningPoint(373, 379)), array(new CunningPoint(373, 379), new CunningPoint(300, 379)), 
						array(new CunningPoint(373, 379), new CunningPoint(300, 379)), array(new CunningPoint(344, 166), new CunningPoint(344, 166))
					)
				);
				break;
			case 'f':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(450, 132), new CunningPoint(450, 132), new CunningPoint(377, 132), new CunningPoint(348, 143)), array(new CunningPoint(348, 143), new CunningPoint(316, 156), new CunningPoint(294, 180), new CunningPoint(280, 212)), array(new CunningPoint(280, 212), new CunningPoint(267, 240), new CunningPoint(270, 302), new CunningPoint(270, 302)), array(new CunningPoint(300, 302), new CunningPoint(300, 302), new CunningPoint(297, 248), new CunningPoint(307, 223)), 
						array(new CunningPoint(300, 302), new CunningPoint(300, 302), new CunningPoint(297, 248), new CunningPoint(307, 223)), array(new CunningPoint(307, 223), new CunningPoint(316, 200), new CunningPoint(356, 180), new CunningPoint(380, 172)), array(new CunningPoint(380, 172), new CunningPoint(407, 163), new CunningPoint(450, 182), new CunningPoint(450, 182))
					),
					'lines' => array(
						array(new CunningPoint(450, 182), new CunningPoint(450, 132)), array(new CunningPoint(270, 302), new CunningPoint(210, 302)), array(new CunningPoint(210, 302), new CunningPoint(210, 332)), array(new CunningPoint(210, 332), new CunningPoint(270, 332)), 
						array(new CunningPoint(210, 332), new CunningPoint(270, 332)), array(new CunningPoint(270, 332), new CunningPoint(270, 702)), array(new CunningPoint(270, 702), new CunningPoint(210, 702)), array(new CunningPoint(210, 702), new CunningPoint(210, 732)), 
						array(new CunningPoint(210, 702), new CunningPoint(210, 732)), array(new CunningPoint(210, 732), new CunningPoint(340, 732)), array(new CunningPoint(340, 732), new CunningPoint(360, 702)), array(new CunningPoint(360, 702), new CunningPoint(300, 702)), 
						array(new CunningPoint(360, 702), new CunningPoint(300, 702)), array(new CunningPoint(300, 702), new CunningPoint(300, 332)), array(new CunningPoint(300, 332), new CunningPoint(360, 332)), array(new CunningPoint(360, 332), new CunningPoint(360, 302)), 
						array(new CunningPoint(360, 332), new CunningPoint(360, 302)), array(new CunningPoint(360, 302), new CunningPoint(300, 302)), array(new CunningPoint(450, 182), new CunningPoint(450, 182))
					)
				);
				break;
			case 'b':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(237, 275), new CunningPoint(233, 288), new CunningPoint(232, 295), new CunningPoint(231, 301)), array(new CunningPoint(231, 301), new CunningPoint(194, 577), new CunningPoint(199, 713), new CunningPoint(199, 713)), array(new CunningPoint(199, 713), new CunningPoint(199, 713), new CunningPoint(336, 729), new CunningPoint(382, 689)), array(new CunningPoint(382, 689), new CunningPoint(416, 660), new CunningPoint(431, 604), new CunningPoint(418, 562)), 
						array(new CunningPoint(382, 689), new CunningPoint(416, 660), new CunningPoint(431, 604), new CunningPoint(418, 562)), array(new CunningPoint(418, 562), new CunningPoint(407, 529), new CunningPoint(371, 496), new CunningPoint(335, 495)), array(new CunningPoint(335, 495), new CunningPoint(293, 495), new CunningPoint(234, 570), new CunningPoint(234, 570)), array(new CunningPoint(263, 580), new CunningPoint(263, 580), new CunningPoint(212, 648), new CunningPoint(232, 673)), 
						array(new CunningPoint(263, 580), new CunningPoint(263, 580), new CunningPoint(212, 648), new CunningPoint(232, 673)), array(new CunningPoint(232, 673), new CunningPoint(258, 706), new CunningPoint(325, 691), new CunningPoint(355, 663)), array(new CunningPoint(355, 663), new CunningPoint(380, 641), new CunningPoint(383, 596), new CunningPoint(372, 564)), array(new CunningPoint(372, 564), new CunningPoint(366, 547), new CunningPoint(350, 528), new CunningPoint(332, 528)), 
						array(new CunningPoint(372, 564), new CunningPoint(366, 547), new CunningPoint(350, 528), new CunningPoint(332, 528)), array(new CunningPoint(332, 528), new CunningPoint(303, 526), new CunningPoint(263, 580), new CunningPoint(263, 580))
					),
					'lines' => array(
						array(new CunningPoint(234, 570), new CunningPoint(279, 279)), array(new CunningPoint(279, 279), new CunningPoint(237, 275)), array(new CunningPoint(263, 580), new CunningPoint(263, 580))
					)
				);
				break;
			case 'n':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(251, 382), new CunningPoint(251, 382), new CunningPoint(286, 370), new CunningPoint(346, 371)), array(new CunningPoint(346, 371), new CunningPoint(407, 373), new CunningPoint(427, 385), new CunningPoint(444, 399)), array(new CunningPoint(444, 399), new CunningPoint(458, 411), new CunningPoint(480, 442), new CunningPoint(480, 442)), array(new CunningPoint(510, 442), new CunningPoint(510, 442), new CunningPoint(501, 403), new CunningPoint(480, 382)), 
						array(new CunningPoint(510, 442), new CunningPoint(510, 442), new CunningPoint(501, 403), new CunningPoint(480, 382)), array(new CunningPoint(480, 382), new CunningPoint(450, 352), new CunningPoint(428, 347), new CunningPoint(360, 342)), array(new CunningPoint(360, 342), new CunningPoint(291, 337), new CunningPoint(250, 352), new CunningPoint(250, 352))
					),
					'lines' => array(
						array(new CunningPoint(170, 332), new CunningPoint(170, 362)), array(new CunningPoint(170, 362), new CunningPoint(220, 362)), array(new CunningPoint(220, 362), new CunningPoint(220, 682)), array(new CunningPoint(220, 682), new CunningPoint(170, 682)), 
						array(new CunningPoint(220, 682), new CunningPoint(170, 682)), array(new CunningPoint(170, 682), new CunningPoint(140, 712)), array(new CunningPoint(140, 712), new CunningPoint(300, 712)), array(new CunningPoint(300, 712), new CunningPoint(300, 682)), 
						array(new CunningPoint(300, 712), new CunningPoint(300, 682)), array(new CunningPoint(300, 682), new CunningPoint(250, 682)), array(new CunningPoint(250, 682), new CunningPoint(251, 382)), array(new CunningPoint(480, 442), new CunningPoint(480, 682)), 
						array(new CunningPoint(480, 442), new CunningPoint(480, 682)), array(new CunningPoint(480, 682), new CunningPoint(430, 682)), array(new CunningPoint(430, 682), new CunningPoint(430, 712)), array(new CunningPoint(430, 712), new CunningPoint(560, 712)), 
						array(new CunningPoint(430, 712), new CunningPoint(560, 712)), array(new CunningPoint(560, 712), new CunningPoint(510, 682)), array(new CunningPoint(510, 682), new CunningPoint(510, 442)), array(new CunningPoint(250, 352), new CunningPoint(250, 332)), 
						array(new CunningPoint(250, 352), new CunningPoint(250, 332)), array(new CunningPoint(250, 332), new CunningPoint(170, 332))
					)
				);
				break;
			case 'S':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(471, 504), new CunningPoint(434, 427), new CunningPoint(325, 402), new CunningPoint(283, 327)), array(new CunningPoint(283, 327), new CunningPoint(272, 306), new CunningPoint(262, 279), new CunningPoint(269, 256)), array(new CunningPoint(269, 256), new CunningPoint(276, 234), new CunningPoint(300, 220), new CunningPoint(319, 209)), array(new CunningPoint(319, 209), new CunningPoint(341, 196), new CunningPoint(366, 188), new CunningPoint(391, 186)), 
						array(new CunningPoint(319, 209), new CunningPoint(341, 196), new CunningPoint(366, 188), new CunningPoint(391, 186)), array(new CunningPoint(391, 186), new CunningPoint(428, 182), new CunningPoint(472, 180), new CunningPoint(503, 201)), array(new CunningPoint(503, 201), new CunningPoint(519, 211), new CunningPoint(532, 266), new CunningPoint(531, 248)), array(new CunningPoint(530, 182), new CunningPoint(529, 159), new CunningPoint(538, 154), new CunningPoint(477, 153)), 
						array(new CunningPoint(530, 182), new CunningPoint(529, 159), new CunningPoint(538, 154), new CunningPoint(477, 153)), array(new CunningPoint(477, 153), new CunningPoint(477, 153), new CunningPoint(345, 138), new CunningPoint(272, 196)), array(new CunningPoint(272, 196), new CunningPoint(200, 254), new CunningPoint(211, 307), new CunningPoint(223, 334)), array(new CunningPoint(223, 334), new CunningPoint(258, 415), new CunningPoint(367, 442), new CunningPoint(417, 515)), 
						array(new CunningPoint(223, 334), new CunningPoint(258, 415), new CunningPoint(367, 442), new CunningPoint(417, 515)), array(new CunningPoint(417, 515), new CunningPoint(432, 538), new CunningPoint(440, 565), new CunningPoint(446, 592)), array(new CunningPoint(446, 592), new CunningPoint(454, 631), new CunningPoint(456, 671), new CunningPoint(451, 710)), array(new CunningPoint(451, 710), new CunningPoint(445, 747), new CunningPoint(449, 778), new CunningPoint(412, 817)), 
						array(new CunningPoint(451, 710), new CunningPoint(445, 747), new CunningPoint(449, 778), new CunningPoint(412, 817)), array(new CunningPoint(412, 817), new CunningPoint(366, 865), new CunningPoint(268, 869), new CunningPoint(212, 833)), array(new CunningPoint(212, 833), new CunningPoint(190, 819), new CunningPoint(184, 742), new CunningPoint(185, 761)), array(new CunningPoint(187, 829), new CunningPoint(190, 883), new CunningPoint(251, 880), new CunningPoint(278, 880)), 
						array(new CunningPoint(187, 829), new CunningPoint(190, 883), new CunningPoint(251, 880), new CunningPoint(278, 880)), array(new CunningPoint(278, 880), new CunningPoint(308, 879), new CunningPoint(337, 879), new CunningPoint(366, 879)), array(new CunningPoint(366, 879), new CunningPoint(419, 878), new CunningPoint(471, 802), new CunningPoint(491, 743)), array(new CunningPoint(491, 743), new CunningPoint(517, 668), new CunningPoint(506, 576), new CunningPoint(471, 504)),
						array(new CunningPoint(491, 743), new CunningPoint(517, 668), new CunningPoint(506, 576), new CunningPoint(471, 504))
					),
					'lines' => array(
						array(new CunningPoint(531, 248), new CunningPoint(530, 182)), array(new CunningPoint(185, 761), new CunningPoint(187, 829)), array(new CunningPoint(471, 504), new CunningPoint(471, 504))
					)
				);
				break;
			case 'X':
				$this->glyphdata = array(
					'lines' => array(
						array(new CunningPoint(200, 322), new CunningPoint(320, 522)), array(new CunningPoint(320, 522), new CunningPoint(190, 712)), array(new CunningPoint(190, 712), new CunningPoint(230, 712)), array(new CunningPoint(230, 712), new CunningPoint(340, 542)), 
						array(new CunningPoint(230, 712), new CunningPoint(340, 542)), array(new CunningPoint(340, 542), new CunningPoint(450, 722)), array(new CunningPoint(450, 722), new CunningPoint(490, 722)), array(new CunningPoint(490, 722), new CunningPoint(360, 512)), 
						array(new CunningPoint(490, 722), new CunningPoint(360, 512)), array(new CunningPoint(360, 512), new CunningPoint(486, 322)), array(new CunningPoint(486, 322), new CunningPoint(450, 322)), array(new CunningPoint(450, 322), new CunningPoint(340, 492)), 
						array(new CunningPoint(450, 322), new CunningPoint(340, 492)), array(new CunningPoint(340, 492), new CunningPoint(240, 322)), array(new CunningPoint(240, 322), new CunningPoint(200, 322))
					)
				);
				break;
			case 'k':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(463, 532), new CunningPoint(463, 532), new CunningPoint(440, 584), new CunningPoint(421, 587)), array(new CunningPoint(421, 587), new CunningPoint(399, 591), new CunningPoint(390, 553), new CunningPoint(376, 536)), array(new CunningPoint(376, 536), new CunningPoint(363, 520), new CunningPoint(339, 492), new CunningPoint(343, 473)), array(new CunningPoint(343, 473), new CunningPoint(349, 435), new CunningPoint(387, 447), new CunningPoint(398, 410)), 
						array(new CunningPoint(343, 473), new CunningPoint(349, 435), new CunningPoint(387, 447), new CunningPoint(398, 410)), array(new CunningPoint(398, 410), new CunningPoint(409, 378), new CunningPoint(431, 328), new CunningPoint(412, 300)), array(new CunningPoint(412, 300), new CunningPoint(400, 280), new CunningPoint(400, 277), new CunningPoint(377, 278)), array(new CunningPoint(377, 278), new CunningPoint(320, 281), new CunningPoint(188, 340), new CunningPoint(188, 340)), 
						array(new CunningPoint(377, 278), new CunningPoint(320, 281), new CunningPoint(188, 340), new CunningPoint(188, 340)), array(new CunningPoint(335, 301), new CunningPoint(284, 311), new CunningPoint(202, 331), new CunningPoint(201, 377)), array(new CunningPoint(307, 435), new CunningPoint(341, 429), new CunningPoint(372, 401), new CunningPoint(387, 370)), array(new CunningPoint(387, 370), new CunningPoint(397, 352), new CunningPoint(404, 323), new CunningPoint(390, 307)), 
						array(new CunningPoint(387, 370), new CunningPoint(397, 352), new CunningPoint(404, 323), new CunningPoint(390, 307)), array(new CunningPoint(390, 307), new CunningPoint(378, 293), new CunningPoint(353, 297), new CunningPoint(335, 301))
					),
					'lines' => array(
						array(new CunningPoint(141, 160), new CunningPoint(162, 166)), array(new CunningPoint(162, 166), new CunningPoint(163, 618)), array(new CunningPoint(163, 618), new CunningPoint(215, 605)), array(new CunningPoint(215, 605), new CunningPoint(221, 574)), 
						array(new CunningPoint(215, 605), new CunningPoint(221, 574)), array(new CunningPoint(221, 574), new CunningPoint(187, 590)), array(new CunningPoint(187, 590), new CunningPoint(193, 477)), array(new CunningPoint(193, 477), new CunningPoint(317, 455)), 
						array(new CunningPoint(193, 477), new CunningPoint(317, 455)), array(new CunningPoint(317, 455), new CunningPoint(317, 502)), array(new CunningPoint(317, 502), new CunningPoint(350, 545)), array(new CunningPoint(350, 545), new CunningPoint(414, 621)), 
						array(new CunningPoint(350, 545), new CunningPoint(414, 621)), array(new CunningPoint(414, 621), new CunningPoint(448, 594)), array(new CunningPoint(448, 594), new CunningPoint(463, 532)), array(new CunningPoint(188, 340), new CunningPoint(186, 148)), 
						array(new CunningPoint(188, 340), new CunningPoint(186, 148)), array(new CunningPoint(186, 148), new CunningPoint(142, 143)), array(new CunningPoint(142, 143), new CunningPoint(141, 160)), array(new CunningPoint(201, 377), new CunningPoint(200, 453)), 
						array(new CunningPoint(201, 377), new CunningPoint(200, 453)), array(new CunningPoint(200, 453), new CunningPoint(307, 435)), array(new CunningPoint(335, 301), new CunningPoint(335, 301))
					)
				);
				break;
			case 'E':
				$this->glyphdata = array(
					'lines' => array(
						array(new CunningPoint(150, 202), new CunningPoint(150, 252)), array(new CunningPoint(150, 252), new CunningPoint(200, 252)), array(new CunningPoint(200, 252), new CunningPoint(200, 832)), array(new CunningPoint(200, 832), new CunningPoint(150, 832)), 
						array(new CunningPoint(200, 832), new CunningPoint(150, 832)), array(new CunningPoint(150, 832), new CunningPoint(150, 882)), array(new CunningPoint(150, 882), new CunningPoint(520, 882)), array(new CunningPoint(520, 882), new CunningPoint(520, 752)), 
						array(new CunningPoint(520, 882), new CunningPoint(520, 752)), array(new CunningPoint(520, 752), new CunningPoint(470, 752)), array(new CunningPoint(470, 752), new CunningPoint(470, 832)), array(new CunningPoint(470, 832), new CunningPoint(250, 832)), 
						array(new CunningPoint(470, 832), new CunningPoint(250, 832)), array(new CunningPoint(250, 832), new CunningPoint(250, 562)), array(new CunningPoint(250, 562), new CunningPoint(430, 562)), array(new CunningPoint(430, 562), new CunningPoint(430, 512)), 
						array(new CunningPoint(430, 562), new CunningPoint(430, 512)), array(new CunningPoint(430, 512), new CunningPoint(250, 512)), array(new CunningPoint(250, 512), new CunningPoint(250, 252)), array(new CunningPoint(250, 252), new CunningPoint(470, 252)), 
						array(new CunningPoint(250, 252), new CunningPoint(470, 252)), array(new CunningPoint(470, 252), new CunningPoint(470, 332)), array(new CunningPoint(470, 332), new CunningPoint(520, 332)), array(new CunningPoint(520, 332), new CunningPoint(520, 202)), 
						array(new CunningPoint(520, 332), new CunningPoint(520, 202)), array(new CunningPoint(520, 202), new CunningPoint(150, 202))
					)
				);
				break;
			case 'Q':
				$this->glyphdata = array(
					'cubic_splines' => array(
						array(new CunningPoint(200, 362), new CunningPoint(198, 474), new CunningPoint(201, 680), new CunningPoint(200, 712))
					),
					'lines' => array(
						array(new CunningPoint(130, 722), new CunningPoint(200, 812)), array(new CunningPoint(200, 812), new CunningPoint(490, 812)), array(new CunningPoint(490, 812), new CunningPoint(540, 872)), array(new CunningPoint(540, 872), new CunningPoint(630, 872)), 
						array(new CunningPoint(540, 872), new CunningPoint(630, 872)), array(new CunningPoint(630, 872), new CunningPoint(570, 802)), array(new CunningPoint(570, 802), new CunningPoint(640, 732)), array(new CunningPoint(640, 732), new CunningPoint(640, 342)), 
						array(new CunningPoint(640, 732), new CunningPoint(640, 342)), array(new CunningPoint(640, 342), new CunningPoint(561, 274)), array(new CunningPoint(561, 274), new CunningPoint(200, 272)), array(new CunningPoint(200, 272), new CunningPoint(130, 342)), 
						array(new CunningPoint(200, 272), new CunningPoint(130, 342)), array(new CunningPoint(130, 342), new CunningPoint(130, 722)), array(new CunningPoint(200, 712), new CunningPoint(240, 752)), array(new CunningPoint(240, 752), new CunningPoint(440, 752)), 
						array(new CunningPoint(240, 752), new CunningPoint(440, 752)), array(new CunningPoint(440, 752), new CunningPoint(400, 692)), array(new CunningPoint(400, 692), new CunningPoint(490, 692)), array(new CunningPoint(490, 692), new CunningPoint(530, 752)), 
						array(new CunningPoint(490, 692), new CunningPoint(530, 752)), array(new CunningPoint(530, 752), new CunningPoint(570, 702)), array(new CunningPoint(570, 702), new CunningPoint(570, 362)), array(new CunningPoint(570, 362), new CunningPoint(520, 322)), 
						array(new CunningPoint(570, 362), new CunningPoint(520, 322)), array(new CunningPoint(520, 322), new CunningPoint(250, 322)), array(new CunningPoint(250, 322), new CunningPoint(200, 362))
					)
				);
				break;
			default:
				return False;
		}
	}
	
	public function get_glyph() {
		return $this->character;
	}
	 
	/* 
	 * Scales the glyphs correctly such that it fits with it's
	 * maximal possible size into the bounding rectangle.
	 */
	private function adjust() {
		if (!$this->glyphdata)
			return False;
		
		/* Translate glyph to the top left corner, such that the 
		 * smallest x and y value both become zero
		 */
		$bpoints = $this->bounding_points();
		/* Get the height of our glyph */
		$dy = $bpoints['max']->y - $bpoints['min']->y;
		/* And the width */
		$dx = $bpoints['max']->x - $bpoints['min']->x;
		
		/* Determine which dimension is critical for resizing. Width or height. Take the smaller. */
		$f1 = $this->width/$dx; $f2 = $this->height/$dy;
		/* In case the width and/or length is taller then the default measures we won't scale
		 * it with the resulting factor > 1, because the blur() functon might have deliberately
		 * scaled it.
		 */
		$cfactor = ($f1 > $f2 ? $f2 : $f1); //$cfactor = $cfactor > 1 ? 1 : $cfactor;
		$cfactor -= 0.001; /* Guessing some error margin. This is essential */
		$this->_translate(-$bpoints['min']->x, -$bpoints['min']->y);
		$this->_scale($cfactor, $cfactor);
	}
	
	public function draw_glyph() {
		/* Because this class follows the singleton design pattern, it is used to 
		 * draw several glyphs on its bitmap. But after each plotting we need to erease
		 * the bitmap before we draw a new glyph.
		 */
		 if ($this->plotted) {
			$this->initbm();
		}
		
		$this->blur();
		$this->adjust();
                
                $this->_fill();
		
		foreach ($this->glyphdata as $shapetype => $shapearray) {
			switch ($shapetype) {
				case 'lines':
					foreach ($this->glyphdata[$shapetype] as $line)
						$this->line($line);
					break;
				case 'quadratic_splines':
					foreach ($this->glyphdata[$shapetype] as $spline)
						$this->spline($spline);
				case 'cubic_splines':
					foreach ($this->glyphdata[$shapetype] as $spline)
						$this->spline($spline);
					break;
				default:
					throw new Exception("Invalid shapetype in draw_glyph()");
					break;
			}
		}
		$this->plotted = True;
	}
	
	public function draw() { $this->draw_glyph(); }
	
	/* Some debugging methods */
	public function count_glyphdata() {
		echo "CunningGlyph data count: ".count($this->glyphdata['lines'])." and ".count($this->glyphdata['cubic_splines'])."<br />";
	}
	
	public function p_glyphmeasures() {
		$bpoints = $this->bounding_points();
		/* Get the height of our glyph */
		$dy = $bpoints['max']->y - $bpoints['min']->y;
		/* And the width */
		$dx = $bpoints['max']->x - $bpoints['min']->x;
		echo "CunningGlyph [$this->character]: Height=$dy, Width=$dx <br /><br /><hr>";
	}
	
	/* Gets the bounding rectangle of the glyphdata.
	 * Returns an array of 2 points: The minum coordinates and
	 * the maximum coordinates.
	 */
	private function bounding_points() {
		$minp = $this->extremas("min");
		$maxp = $this->extremas("max");
		return array('min' => new CunningPoint($minp[0], $minp[1]),
					 'max' => new CunningPoint($maxp[0], $maxp[1]));
	}
	
	/* Maybe I reinvent here something. But don't want to go searching for existing solutions. ;)
	 * I made it because Python lacks (real) ternary operators and I wanted to play a bit.
	 * 
	 * To do: Get min and max value together (Now only one extreme is found per call).
	 */
	private function extremas($which="max") {
		if(!in_array($which, array("max", "min")))
			return False;
			
		/* Find extremas */
		$exx = $exy = ($which == "max") ? -100000000 : PHP_INT_MAX;
		foreach ($this->glyphdata as $shapetype => $shapearray) {
			foreach ($shapearray as $i => $shape) {
				foreach ($shape as $p) {
					$exx = ($which == "max") ? ($exx < $p->x ? $p->x : $exx) : ($exx > $p->x ? $p->x : $exx);
					$exy = ($which == "max") ? ($exy < $p->y ? $p->y : $exy) : ($exy > $p->y ? $p->y : $exy);
				}
			}
		}
		return array($exx, $exy);
	}
        
	/* 
	 * Rudimentary function to fill the glyph with a given color.
         * 
	 * Approach: Keep in mind that the filling process might happen after
         * linear transformations have been applied on the glyph. 
         * 
         * For each pixel, we need to know if it lies in the glyph or in the outside.
         * For this point-in-shape detection check, we have this cute idea: http://pomxax.github.io/bezierinfo/#shapes
         * In short: Ray casting with even-odd rule applied. For every shape
         * (line or bezier curve), we need to find the intersection points. We will
         * cast a ray to the upper left corner.
         * 
         * The process needs to happen AFTER the glyph is rasterized on the bitmap, because
         * reshaping the geometrical primitives after filling a shape is impossible to say the least.
	 */
	private function _fill() {
            if ($this->plotted)
                return False;
            
            $upper_left = new CunningPoint(0, 0);
            
            for ($i = 0; $i < $this->get_height(); $i++) {
                for ($j = 0; $j < $this->get_width(); $j++) {
                    $current_point = new CunningPoint($j, $i);
                    foreach ($this->glyphdata as $shapetype) {
                        foreach ($shapetype as $shape) {
                            if ($this->_intersects(array(new CunningPoint(0, 0), $current_point), $shape)) {
                                $this->set_pixel ($j, $i);
                            }
                        }
                    }
                }
            }
        }
        
        /* 
         * Check whether there's a intersection between the 
         * given $line and $spline. The $spline may be a quadratic
         * or cubic Bezier curvature, as well as a line.
         * 
         * Approach: http://pomax.github.io/bezierinfo/#intersections
         * Using Raphson-Netwon root finding to find the extremities.
         * 
         * Returns True if a intersection was found.
         */
        private function _intersects($line, $shape) {
            if (count($shape) == 2) /* Handle line-line intersections */ {
                $a1 = $line[0]->y - $line[1]->x; $a2 = $shape[0]->y - $shape[1]->x;
                $b1 = $line[0]->x - $line[1]->x; $b2 = $shape[0]->x - $shape[1]->x;
                $c1 = $a1*$line[0]->x+$b1*$line[0]->y; $c2 = $a2*$shape[0]->x+$b2*$shape[1]->y;
                $det = $a1*$b2 - $a2*$b1;
                
                if ($det == 0) {
                    //Lines are parallel
                    return False;
                } else {
                    $x = (int) (($b2*$c1 - $b1*$c2)/$det);
                    $y = (int) (($a1*$c2 - $a2*$c1)/$det);
                    echo "Found intersection of line [({$line[0]->x}, {$line[0]->y}), ({$line[1]->x}, {$line[1]->y})] and line [({$shape[0]->x}, {$shape[0]->y}), ({$shape[1]->x}, {$shape[1]->y})]: ($x, $y) <br />";
                }
                
                if (
                        min($line[0]->x,$line[1]->x) <= $x && $x <= max($line[0]->x,$line[1]->x) &&
                        min($line[0]->y,$line[1]->y) <= $y && $y <= max($line[0]->y,$line[1]->y)
                   ) {
                        echo "True";
                        return True;
                   } else return False;
            }
            
            if (count($shape) == 4) { /* Handle cubic splines */
                /* Translate/rotate $line in that way such that it becomes (a subset) of the x-axis */

                /* Translate such that the first point of the line coincides with the origin */
                foreach ($shape as $key => $value) {
                    $shape[$key]->x = $shape[$key]->x - $line[0]->x;
                    $shape[$key]->y = $shape[$key]->y - $line[0]->y;
                }

                /* 
                 * Rotate such that the y coordinate of the last line point becomes zero. Find out the angle
                 * such that the following is true for the last point in the spline:
                 *  y' = xsin(a) + ycos(a) = 0 <=> xsin(a) = -ycos(a) <=> x/-y =cos(a)/sin(a)
                 *  <=> x/-y = cot(a) <=> a = arccot(x/-y)
                 * whereas alpha denotes the rotation angle.
                 */
                $i = count($line)-1;
                if ($line[$i]->y != 0) {
                    $alpha = pi()/2 - atan($line[$i]->x/(-$line[$i]->y));
                    //echo "Alpha is $alpha <br />";

                    /* And now rotate with this angle */
                    foreach ($shape as $key => $value) {
                        $rx = intval(cos($alpha)*$shape[$key]->x - sin($alpha)*$shape[$key]->y);
                        $ry = intval(sin($alpha)*$shape[$key]->x + cos($alpha)*$shape[$key]->y);
                        $shape[$key]->x = $rx;
                        $shape[$key]->y = $ry;
                    }
                }

                //echo "<pre>"; var_dump($spline); echo "</pre>";

                /* Now the roots of the translated/rotated spline coincides with the intersections points with the line. */
                $in_range = create_function('$num', 'if ($num <= 1 && $num >= 0) return True; else return False;');
                $root_x = CunningCanvas::bezier_root($shape, $coord="x");
                $root_y = CunningCanvas::bezier_root($shape, $coord="y");
                if ($in_range($root_x) || $in_range($root_y))
                    return True;
                else
                    return False;
            }
        }
	
	/*
	 * http://mathworld.wolfram.com/AffineTransformation.html
	 * 
	 * Affine transformations. An affine transformation is equivalent to
	 * the composed effects of translation, rotation, isotropic scaling and shear.
	 * While an affine transformation preserves proportions on lines,
	 * it does not necessarily preserve angles or lengths.
	 * 
	 * array_walk_recursive applies the callback only on the leaves. That's good.
	 * These functions are on purpose in the CunningGlyph class (could also be
	 * in CunningCanvas) because they refer to the splines and lines that constitute
	 * a CunningGlyph.
	 */
	
	private function _rotate($a) {
		// (x, y) = cos(a)*x - sin(a)*y, sin(a)*x + cos(a)*y
		$code = sprintf('if ($v instanceof CunningPoint) { $v->x = intval(cos(%s)*$v->x - sin(%s)*$v->y); $v->y = intval(sin(%s)*$v->x + cos(%s)*$v->y); }', $a, $a, $a, $a);
		$func = create_function('&$v, $k', $code);
		array_walk_recursive($this->glyphdata, $func);
	}
	
	private function _skew($a) {
		$code = sprintf('if ($v instanceof CunningPoint) { $v->x = intval($v->x+sin(%s)*$v->y);}', $a);
		$func = create_function('&$v, $k', $code);
		array_walk_recursive($this->glyphdata, $func);
	}
	
	/*
	 * dilation.
	 */
	private function _scale($sx, $sy) {
		$code = sprintf('if ($v instanceof CunningPoint) { $v->x = intval($v->x*%s); $v->y = intval($v->y*%s); }', $sx, $sy);
		$func = create_function('&$v, $k', $code);
		array_walk_recursive($this->glyphdata, $func);
	}
	
	/* 
	 * non-uniform scaling in some directions.
	 */
	private function _shear($kx, $ky) {
		$code = sprintf('if ($v instanceof CunningPoint) { $v->x = intval($v->x+$v->y*%s); $v->y = intval($v->y+$v->x*%s); }', $ky, $kx);
		$func = create_function('&$v, $k', $code);
		array_walk_recursive($this->glyphdata, $func);
	}
	
	private function _translate($dx, $dy) {
		$code = sprintf('if ($v instanceof CunningPoint) { $v->x += %s; $v->y += %s; }', $dx, $dy);
		$func = create_function('&$v, $k', $code);
		array_walk_recursive($this->glyphdata, $func);
	}
	
	/* 
	 * Apply bluring techniques on the glyph! The sophistication of
	 * this method essentially determines the strenth of the captcha.
	 */
	private function blur() {
		$shear = (cclib_secure_random_number(0, 13)-6)/10;
		$this->_shear($shear, $shear);
		
		//$skew = rand(0, 90);
		//$this->_skew($skew);
		
		$this->_rotate((cclib_secure_random_number(0, 10)-5)/10);
		
		//$scale = rand(5, 30) / 10;
		//$this->_scale($scale, $scale);
	}
}

/*
 * The class CunningCaptcha represents the CunningCaptcha. It is on the top of the abstraction layer of this plugin
 * and is exported to the wordpress plugin. This means the wordpress code will do almost all it's stuff with 
 * a CunningCaptcha object. A captcha object has methods to write it's internal state as .bbm and .png files.
 * A object of this class can see as a "stamp", because CunningCaptcha implements a method called reload() that
 * recalculates a complete new captcha based on the current configuration. This means that the all applications only need one captcha object.
 * Therefore this class is designed after the Singleton design pattern. It's only possible to construct one single instance of 
 * this class. Everything else is a waste of memory.
 * 
 * The CunningCaptcha class has a alphabet of glyphs and a array of glyphs (That it eventually exports as image). These array elements
 * are CunningGlyph() instances. The glyphs are ony plotted when write_image() is called. CunningCaptcha inherits also from CunningCanvas! This enables
 * the CunningCaptcha instance to manipulate it's bitmap AFTER all glyphs have been merged to it. So there is a second layer of randomization
 * on the bitmap, that wouldn't have existed if just glyph sized rectangles are planted into the captcha. There wouldn't be cross glyhp
 * noise. I hope you understand. 
 */

class CunningCaptcha extends CunningCanvas {
	/* The instance of this class */
	private static $instance;
	
	/* Wheter the user needs to tell the case of each glyph */
	const CASE_SENSITIVE = False;
	
	/* All glyphs */
	const ALPHABET = 'y, W, G, a, H, i, f, b, n, S, X, k, E, Q';
	
	/* A single glyph used as the generic stamp. */
	private $stamp;
	
	/* Length of the captcha */
	private $clength = 0;
	
	/* name of the file to write */
	private $outfile;
	
	public function __construct($clength, $height) {
		/* 
                 * This check needs to be here in order to hold the constructor public (inheritance).
		 * while keeping Singleton design pattern.
		 */
		if(isset(self::$instance))
			throw new Exception('Only one instance of CunningCaptcha allowed!');
		
		/* Get a glyph instance */
		$this->stamp = CunningGlyph::get_instance($character='');
		
		parent::__construct($this->stamp->get_width()*$clength + 100 /* Some buffer */, $height);
		$this->clength = $clength;
	}
	
	/* 
	 * All newly calls to get_instance() 
	 * return the same object. Singleton
	 * design pattern.
	 * 
	 * Make sure that you take care of your instance ;)
	 */
	public static function get_instance($clength=5, $height=80) {
		if (!isset(self::$instance)) {
			$c = __CLASS__;
			self::$instance = new $c($clength, $height);
			// Or easier: self::$instance = new CunningCaptcha();
		}
		return self::$instance;
	}
	
	/* 
	 * Implement abstract draw() function.
	 * This function merges a random selection of glyphs into the bitmap and
	 * returns the corresponding string.
         */
	protected function draw() {
		/* Take n=length random characters from the alphabet */
		$randalphabet = explode(', ', self::ALPHABET);
		shuffle($randalphabet);
		for ($i = 0; $i < $this->clength; $i++) {
			$randchars[] = $randalphabet[intval(cclib_secure_random_number(0, count($randalphabet)-1))];
		}

		foreach ($randchars as $i => $char) {
			$this->stamp->load_glyph($char);
			$this->stamp->draw();
			$this->merge(cclib_secure_random_number(0, 20), $i*cclib_secure_random_number($this->stamp->get_width(), $this->stamp->get_width() + 10), $this->stamp->get_bitmap());
		}
		
		//echo sprintf("[!] Using %u bytes <br />", memory_get_usage());
		
		$this->write_image($this->outfile, $format='ppm');
		
		/* Return the captcha word as a string */
		return implode('', $randchars);
	}
	
	public function write_image($path, $format = 'png') {
		
		if (!in_array($format, array('png', 'ppm', 'jpg', 'gif')))
			return False;
		
		/* Either way we are going to write a file. When we cannot open a file, there
		 * must be a severe failure and we terminate the script.
		 */
		$h = fopen($path.".".$format, "w") or exit('Error: fopen() failed.');

		switch ($format) {
			case 'png':
				break;
			case 'ppm':
				/* Writes a colorous ppm image file. It's not compressed */
				fwrite($h, sprintf("P3\n%u %u\n255\n", $this->width, $this->height)) or exit('fwrite() failed.');
				$bm = $this->get_bitmap();
				/* Write all pixels */
				for ($i = 0; $i < $this->height; $i++) {
					for ($j = 0; $j < $this->width; $j++) {
						if (isset($bm[$i][$j]))
							fwrite($h, '0 0 0'."\t");
						else
							fwrite($h, '200 200 200'."\t");
					}
					fwrite($h,"\n");
				}
				break;
			default:
				break;
		}
		
		/* Close the open file descriptor. */
		fclose($h) or exit('fclose() failed.');
	}
	
	public function reload($outfile) {
		$this->outfile = $outfile;
		$this->initbm();
		return $this->draw();
	}
}
?>
