import tkinter
import math

class GeometricalPrimitives(tkinter.Canvas):
	'''
	This class implements geometrical primitives such as Bézier curvatures
	and simple lines. There are several algorithms for the Bézier curves, one
	direct approach and a recursive. There is also the bresenham line plotting
	algorithm for, as you might have guessed, drawing lines!
	Author: Nikolai Tschacher
	Date: 13.10.2013
	'''
	
	# Because Canvas doesn't support simple pixel plotting,
	# we need to help us out with a line with length 1 in
	# positive x direction.
	def _plot_pixel(self, x0, y0):
		self.create_line(x0, y0, x0+1, y0)
		
	# Calculates the quadtratic Bézier polynomial for 
	# the n+1=3 coordinates.
	def _quadratic_bezier_sum(self, t, w):
		t2 = t * t 
		mt = 1-t
		mt2 = mt * mt
		return w[0]*mt2 + w[1]*2*mt*t + w[2]*2
		
	# Calculates the cubic Bézier polynomial for 
	# the n+1=4 coordinates.
	def _cubic_bezier_sum(self, t, w):
		t2 = t * t
		t3 = t2 * t
		mt = 1-t
		mt2 = mt * mt
		mt3 = mt2 * mt
		return w[0]*mt3 + 3*w[1]*mt2*t + 3*w[2]*mt*t2 + w[3]*t3

	def _draw_quadratic_bez(self, p1, p2, p3):
		t = 0
		while (t < 1):
			x = self._quadratic_bezier_sum(t, (p1[0], p2[0], p3[0]))
			y = self._quadratic_bezier_sum(t, (p1[1], p2[1], p3[1]))
			# self.plot_pixel(math.floor(x), math.floor(y))
			self._plot_pixel(x, y)
			t += 0.001
	
	def _draw_cubic_bez(self, p1, p2, p3, p4):
		t = 0
		while (t < 1):
			x = self._cubic_bezier_sum(t, (p1[0], p2[0], p3[0], p4[0]))
			y = self._cubic_bezier_sum(t, (p1[1], p2[1], p3[1], p4[1]))
			self._plot_pixel(math.floor(x), math.floor(y))
			t += 0.001
			
	def _casteljau(self, points, t):
		# Check that input parameters are valid. We don't check wheter 
		# the elements in the tuples are of type int or float.
		for p in points:
			if (not isinstance(p, tuple) or not len(p) == 2):
				raise InvalidInputError('points is not a list of points(tuples)')
		if (len(points) == 1):
			self._plot_pixel(points[0][0], points[0][1])
		else:
			newpoints = []
			for i in range(0, len(points)-1):
				x = (1-t) * points[i][0] + t * points[i+1][0]
				y = (1-t) * points[i][1] + t * points[i+1][1]
				newpoints.append((x, y))
			self._casteljau(newpoints, t)
			
	# Usage function for the casteljau algorithm.
	def _draw_casteljau(self, points):
		t = 0
		while (t <= 1):
			self._casteljau(points, t)
			t += 0.001
			
	# Usage function for drawing Bézier curves
	# - The var algo is a string indicating the algorithm to use.
	#   You can choose between the recursive "casteljau" algorithm and the 
	#   direct approach "direct".
	# - points is a list of tuples, which in turn are points.
	# - 
	def bezier(self, points, algo='casteljau'):
		if algo == 'casteljau':
			self._draw_casteljau(points)
		elif algo == 'direct':
			if (len(points) == 3):
				self._draw_quadratic_bez(points[0], points[1], points[2])
			elif (len(points) == 4):
				self._draw_cubic_bez(points[0], points[1], points[2], points[3])
			else:
				raise Exception('Direct approach draws only quadratic and cubic Béziers')
				
	# Yet another implementation taken from
	# http://members.chello.at/easyfilter/bresenham.html
	# This alogrithm is capable to plot all possible lines in a 2d plane.
	def line(self, points):
		if len(points) != 2 or not isinstance(points[1], tuple):
			raise InvalidInputError('To draw a line we need a list of two tuples containing two ints')
			
		(x0, y0), (x1, y1) = points
		
		dx = abs(x1-x0)
		dy = -abs(y1-y0)
		sx = [-1, 1][x0<x1]
		sy = [-1, 1][y0<y1]
		err = dx+dy
		e2 = 1
		while (True):
			self._plot_pixel(x0, y0)
			if (x0==x1 and y0==y1):
				break
			e2 = 2*err
			if (e2 >= dy):
				err += dy
				x0 += sx     
			if (e2 <= dx):
				err += dx
				y0 += sy

class Glyph(GeometricalPrimitives):
	'''
	Represents a glyph. Dirty coding, since this acts as a kind of stamp. Each time
	you want to draw another glyph, you need to reaload the glyph data with R()
	'''
	
	def __init__(self, master, c, fname=''):
		if not isinstance(master, tkinter.Tk):
			raise Exception('The Glyph has no master frame to draw on')
		super().__init__(master, width=1500, height=1000)
		# The glyph is represented as a dict of list of list of points (tuples).
		self.data = {'quadratic_bezier': [], 'simple_lines': [], 'cubic_bezier': []}
		
		self.character = c
		if fname:
			self.glyph_from_svg(fname)
			
	def glyph_from_svg(self, fname):
		try:
			import parseSVG
		except ImportError as e:
			print('[-] Couldnt find svg parser module')
			return False
		
		# Somehow ugly. Maybe design error of underlying module, maybe there
		# are better ways to union/inject n different dicts with same keys.
		for g in parseSVG.parse_path(fname):
			for kw in sorted(g.keys()):
				if g[kw]:
					self.data[kw].extend(g[kw])
					
	def R(self, fname):
		self.data = {'quadratic_bezier': [], 'simple_lines': [], 'cubic_bezier': []}
		self.glyph_from_svg(fname)
				
	def draw(self):
		for kw in sorted(self.data.keys()):
			if kw in ('cubic_bezier', 'quadratic_bezier'):
				for spline in self.data[kw]:
					self.bezier(spline, 'direct')
			elif kw == 'simple_lines':
				for line in self.data[kw]:
					self.line(line)
	
	# This function applies random linear transformations on the glyph.
	# The parameters shouldn't be to large, since the glyphs might become 
	# unreadable.
	def blur(self, glyph):
		pass
						
	# These functions apply linear transformations to a list of points.
	
	# Rotate the coordinates of the given list of points by the angle a.
	def _rotate(self, a):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = (int(p[0]*math.cos(a) + p[1]*math.sin(a)), int(math.cos(a)*p[1] - math.sin(p[0])*p[0]))
	
	# Translate the given points by delta x and delta y units.
	def _translate(self, dx, dy):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = (p[0]+dx, p[1]+dy)
	
	# Scale the vectors with the literals sx and sy
	# Apply reflection by the x-axis with sclaing with sx=1,sy=-1
	# and by the y-axis with sx=-1,sy=1
	def _scale(self, sx, sy):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = (int(sx*p[0]), int(sy*p[1]))
	
	def _shear(self, ky, kx):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = (int(p[0]+p[1]*ky), int(p[1]+p[0]*kx))
			
	def _skew(self, a):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = (int(p[0]+math.sin(a)*p[1]), p[1])
	
	def _fill(self, color):
		pass # Fill the glyph. This is actually no trivial task. Maybe I will
			 # ignore this in the real implementation, since it just uses very much
			 # computing power (a lot of bézier curve and line intersections have to
			 # be calculated and for this I need the first derivative. Maybe there are better ways...
	
	# 3 for loops vs 1.
	def _op(self):
		keys = sorted(self.data.keys())
		for kw in keys:
			for i, geo_el in enumerate(self.data[kw]):
				for j, p in enumerate(geo_el):
					if isinstance(p, tuple):
						yield (kw, i, j, p)
							 
class Font():
	'''
	Implements a very basic font and demonstrates the glyphs in a tests()
	method. Just for illustration purposes.
	Author: Nikolai Tschacher
	Date: 13.10.2013
	'''
	
	def __init__(self):
		self.alphabet = 'bEfGHiknQSWXy'
		
	def tests(self):
		master = tkinter.Tk()
		
		v, h = (100, -200)
		g = Glyph(master, 'a', 'glyphs/a.svg')
		for i, c in enumerate(self.alphabet):
			g._scale(0.3, 0.3)
			g._translate(h, v)
			g._shear(1, 0)
			if i % 4 == 0 and i > 0:
				v += 250
				h = 0
			h += 200
			g.draw()
			g.pack()
			g.R('glyphs/%s.svg' % c)

		tkinter.mainloop()
	
if __name__ == '__main__':
	Font().tests()
