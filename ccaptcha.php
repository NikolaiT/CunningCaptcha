
<?php

/* 
 * A simple class to represent points. Public members, since working with
 * getters and setters is too pendantic in this context.
 */
public class Point {
	public $x;
	public $y;
	
	public function __construct($x, $y) {
		$this->x = $x;
		$this->y = $y;
	}
	
	public __toString() {
		return 'Point(x='.$this->x.', y='.$this->y')';
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
 
public class Canvas {
	private $width;
	private $height;
	
	public function __construct($width=100, $height=100) {
		
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
 
 public class Glyph {
	/* Array of array of Points() */
	$this->glyphdata = {};
	
	public function __construct($character, $width, $height) {
		
	}
	
	public function blur() {
		
	}
}


/*
 * The class Captcha represents the Captcha. It is on the top of the abstraction layer of this plugin
 * and is exported to the wordpress plugin. This means the wordpress code will do all it's stuff with 
 * a Captcha object. A captcha object has methods to write it's internal state as .bbm and .png files.
 * A object of this class can see as a "stamp", because Captcha implements a method called reload() that
 * recalculates a complete new captcha based on the current configuration. This means that the all applications only need one captcha object.
 * 
 * The Captcha class has a alphabet of glyphs and a array of glyphs (That it eventually exports as image). These array elements
 * are Glyph() instances. The glyphs are ony plotted when write_image() is called.
 */
 
public class Captcha {
	public function __construct() {
		
	}
	
	public function write_image($path, $format = 'png') {}
	
	public function reload() {}
	
}


?>
