<?php

/* Tests */
$c = new Canvas();


/* 
 * A simple class to represent points. Public members, since working with
 * getters and setters is too pendantic in this context.
 */
class Point {
	public $x;
	public $y;
	
	public function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
	
	public function __toString() {
		return 'Point(x='.$this->x.', y='.$this->y.')';
	}
}

/* 
 * I will NOT implement seperate data structures (classes) for splines and lines, 
 * because it just doesn't make sense. It would make things unnecessary complex and 
 * slow. Lines are just arrays of two points. Quadratic Bézier splines arrays of 
 * three points and cubic Bézier splines respectively arrays of 4 points.
 */


/*
 * Describes the class Canvas which implements algorithms to rasterize geometrical primitives such
 * as quadratic and cubic Bézier splines and straight lines. There may be more than one algorithm for each
 * shape.
 */
 
class Canvas {
	
	const STEP = 0.001;
	
	private $width;
	private $height;
	private $bitmap;
	
	/* Lookup-tables for Bézier coeffizients */
	private quad_lut;
	private cub_lut;
	
	public function __construct($width=50, $height=50) {
		$this->heigt = $height;
		$this->width = $width;
		$this->initbm();
	}
	
	private function initbm() {
		$this->bitmap = array_fill(0, $this->height, array_fill(0, $this->width, '0 0 0')); /* init the bitmap */
	}
	
	protected function set_pixel($x, $y, $color='0 0 0') {
		$this->bitmap[$x][$y] = $color;
	}
	
	/* All the different rasterization algorithms. They differ in performance and 
	 * granularity of drawing as well as the smoothness of the curve
	 */
	 
	/* The next two functions calculate the quadratic and cubic bezier points directly.
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
	
	/* Bézier plotting with look-up tables */
	
	private function _gen_quad_LUT() {
		$t = 0;
		while ($t < 1) {
			$t2 = $t*$t;
			$mt = 1-$t;
			$mt2 = $mt*$mt;
			$this->quad_lut[$t] = array($mt2, 2*$mt*$t, $t2);
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
			$this->cub_lut[$t] = array($mt3, 3*$mt2*$t, 3*$mt*$t2, $t3);
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
	
	public function line($points) {}
	
	public function spline($points) {}
}

/*
 * The abstract class Glyph represents a generic Glyph. A glyph inherits from the class Canvas.
 * This class implements a wide range of different 'blur' techniques that try to confuse computational
 * approaches like OCR to recognize the glyph. Therefore there are linear transformations and a wide range of parameters that
 * are randomly chosen. All these bluring techniques can be applied with the blur() function.
 * Each concrete glyph (like A, b, y, x, Q) inherits from the abstract class Glyph. Each such concrete
 * class initializes the attribute glyphdata with the associative array of lines and bezier splines.
 */
 
class Glyph extends Canvas {
	
	public function __construct($character, $width, $height) {
		/* Array of array of Points() */
		$this->glyphdata = array();
	}
	
	
	/* Rudimentary function to fill the glyph (only works when the glyph's shape
	 * is completly closed and the glyphs border are set to a specific color! The
	 * point to begin the filling process MUSTS be within the glyphs shape, otherwise
	 * the whole approach fails!
	 */
	private function _fill($startp) {}
	 
	
	/* linear transformations.
	 * These functions are on purpose in the Glyph class (could also be
	 * in Canvas) because they refer to the splines and lines that constitute
	 * a Glyph.  */
	
	private function _rotate($a) {}
	
	private function _skew($a) {}
	
	private function _scale($a) {}
	
	private function _shear($a) {}
	
	private function _translate($dx, $dy) {}
	
	public function blur() {
		
	}
}

class G_y extends Glyph {
	
}

/*
 * The class Captcha represents the Captcha. It is on the top of the abstraction layer of this plugin
 * and is exported to the wordpress plugin. This means the wordpress code will do almost all it's stuff with 
 * a Captcha object. A captcha object has methods to write it's internal state as .bbm and .png files.
 * A object of this class can see as a "stamp", because Captcha implements a method called reload() that
 * recalculates a complete new captcha based on the current configuration. This means that the all applications only need one captcha object.
 * 
 * The Captcha class has a alphabet of glyphs and a array of glyphs (That it eventually exports as image). These array elements
 * are Glyph() instances. The glyphs are ony plotted when write_image() is called.
 */
 
class Captcha {
	private $width;
	private $height;
	
	public function __construct($width=200, $height=80) {
		$this->width = $width;
		$this->height = $height;
		$this->init_captcha();
	}
	
	private function init_captcha() {
		$this->bitmap = array_fill(0, $this->height, array_fill(0, $this->width, '0 0 0')); /* init the bitmap */
	}
	
	public function write_image($path, $format = 'png') {
		
		if (!in_array($format, array('png', 'bbm', 'jpg', 'gif')))
			return False;
		
		/* Either way we are going to write a file. When we cannot open a file, there
		 * must be a severe failure and we terminate the script.
		 */
		$h = fopen($path.$format, "w") or exit('Error: fopen() failed.');
	  
		switch ($format) {
			case 'png':
				break;
			case 'ppm':
				fwrite($h, sprintf('P3\n%u %u\n255\n', $this->width, $this->height))
															or exit('fwrite() failed.');
				/* Write all pixels */
				for ($i = 0; $i < $this->height; $i++) {
					for ($j = 0; $j < $this->width; $j++) {
						 fwrite($h, $captcha[$i][$j]."\t");
					}
					fwrite($h, "\n");
				  }
				break;
			default:
				break;
		}
		
		/* Close the open file descriptor. */
		fclose($h) or exit('fclose() failed.');
	}
	
	public function reload() {}
	
}

?>
