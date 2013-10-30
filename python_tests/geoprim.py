import collections
import tkinter
import math

Point = collections.namedtuple('Point', 'x y')

class GeometricalPrimitives(tkinter.Canvas):
	'''
	This class implements geometrical primitives such as Bézier curvatures
	and simple lines. There are several algorithms for the Bézier curves, one
	direct approach and a recursive. There is also the bresenham line plotting
	algorithm for, as you might have guessed, drawing lines!
	Author: Nikolai Tschacher
	Date: 13.10.2013 to 21.10.2013
	'''
	
	_NUM_SEGMENTS = 20
	_STEP = 0.001
	_LUT_Q = {}
	_LUT_C = {}
	
	# Because Canvas doesn't support simple pixel plotting,
	# we need to help us out with a line with length 1 in
	# positive x direction.
	# Coordinates as arguemnts because point namedtuples
	# are too slow.
	def _plot_pixel(self, x, y):
		self.create_line(x, y, x+1, y) # This may be slow. In a real world implementation, this is ways faster.
		
	def _draw_quadratic_bez(self, p1, p2, p3):
		t = 0
		while (t < 1):
			t2 = t*t
			mt = 1-t
			mt2 = mt*mt
			x, y = int(p1.x*mt2 + p2.x*2*mt*t + p3.x*t2), int(p1.y*mt2 + p2.y*2*mt*t + p3.y*t2)
			self._plot_pixel(x, y)
			t += self._STEP
	
	def _draw_cubic_bez(self, p1, p2, p3, p4):
		t = 0
		while (t < 1):
			t2 = t * t
			t3 = t2 * t
			mt = 1-t
			mt2 = mt * mt
			mt3 = mt2 * mt
			x, y = int(p1.x*mt3 + 3*p2.x*mt2*t + 3*p3.x*mt*t2 + p4.x*t3), int(p1.y*mt3 + 3*p2.y*mt2*t + 3*p3.y*mt*t2 + p4.y*t3)
			self._plot_pixel(x, y)
			t += self._STEP
			
	def _quadratic_bez_lut(self, p1, p2, p3):
		t = 0
		if not self._LUT_Q:
			#print('[i] lut generating for quadratic splines...')
			while (t < 1):
				t2 = t*t
				mt = 1-t
				mt2 = mt*mt
				self._LUT_Q[t] = (mt2, 2*mt*t, t2)
				t += self._STEP
				
		for v in self._LUT_Q.values():
			x, y = int(p1.x*v[0] + p2.x*v[1] + p3.x*v[2]), int(p1.y*v[0] + p2.y*v[1] + p3.y*v[2])
			self._plot_pixel(x, y)
	
	def _cubic_bez_lut(self, p1, p2, p3, p4):
		t = 0
		if not self._LUT_C:
			#print('[i] lut generating for cubic splines...')
			while (t < 1):
				t2 = t*t
				t3 = t2*t
				mt = 1-t
				mt2 = mt*mt
				mt3 = mt2*mt
				self._LUT_C[t] = (mt3, 3*mt2*t, 3*mt*t2, t3)
				t += self._STEP
				
		for v in self._LUT_C.values():
			x, y = int(p1.x*v[0] + p2.x*v[1] + p3.x*v[2] + p4.x*v[3]), int(p1.y*v[0] + p2.y*v[1] + p3.y*v[2] + p4.y*v[3])
			self._plot_pixel(x, y)
			
	def _approx_quadratic_bez(self, p1, p2, p3):
		lp = []
		lp.append(p1)
		for i in range(self._NUM_SEGMENTS):
			t = i / self._NUM_SEGMENTS
			t2 = t*t
			mt = 1-t
			mt2 = mt*mt
			x, y = int(p1.x*mt2 + p2.x*2*mt*t + p3.x*t2), int(p1.y*mt2 + p2.y*2*mt*t + p3.y*t2)
			lp.append(Point(x,y))
			
		for i in range(len(lp)-1):
			self.line([lp[i], lp[i+1]])
		
	def _approx_cubic_bez(self, p1, p2, p3, p4):
		lp = []
		lp.append(p1)
		for i in range(self._NUM_SEGMENTS):
			t = i / self._NUM_SEGMENTS
			t2 = t * t
			t3 = t2 * t
			mt = 1-t
			mt2 = mt * mt
			mt3 = mt2 * mt
			x, y = int(p1.x*mt3 + 3*p2.x*mt2*t + 3*p3.x*mt*t2 + p4.x*t3), int(p1.y*mt3 + 3*p2.y*mt2*t + 3*p3.y*mt*t2 + p4.y*t3)
			lp.append(Point(x,y))

		for i in range(len(lp)-1):
			self.line([lp[i], lp[i+1]])
			
	def _casteljau(self, points, t):
		# Check that input parameters are valid. We don't check wheter 
		# the elements in the tuples are of type int or float.
		for p in points:
			if not isinstance(p, tuple):
				raise ValueError('points is not a list of points(tuples)')
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
			t += self._STEP
			
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
				self._draw_quadratic_bez(*points)
			elif (len(points) == 4):
				self._draw_cubic_bez(*points)
			else:
				raise ValueError('Direct approach draws only quadratic and cubic Béziers')
		elif algo == 'approx':
			if (len(points) == 3):
				self._approx_quadratic_bez(*points)
			elif (len(points) == 4):
				self._approx_cubic_bez(*points)
			else:
				raise ValueError('Approximation just plots quadratic and cubic Béziers')
		elif algo == 'lut':
			if (len(points) == 3):
				self._quadratic_bez_lut(*points)
			elif (len(points) == 4):
				self._cubic_bez_lut(*points)
			else:
				raise ValueError('Lookup tables algorithm just plots quadratic and cubic Béziers')
				
	# Yet another implementation taken from
	# http://members.chello.at/easyfilter/bresenham.html
	# This alogrithm is capable to plot all possible lines in a 2d plane.
	def line(self, points):
		if len(points) != 2 or not (type(points[0].x) == int and type(points[0].y) == int):
			raise ValueError('To draw a line we need a list of two tuples containing two ints')
			
		(x0, y0), (x1, y1) = points
		
		dx = abs(x1-x0)
		dy = -abs(y1-y0)
		sx = [-1, 1][x0<x1]
		sy = [-1, 1][y0<y1]
		err = dx+dy
		e2 = 1
		while (True):
			self._plot_pixel(x0, y0)
			if (x0 == x1 and y0 == y1):
				break
			e2 = 2*err
			if (e2 >= dy):
				err += dy
				x0 += sx
			if (e2 <= dx):
				err += dx
				y0 += sy
			
# Some tests when executing module directly.
if __name__ == '__main__':
	master = tkinter.Tk()
	gp = GeometricalPrimitives(master, width=1000, height=1000)
	gp.bezier([Point(300, 250), Point(0, 0), Point(465, 111)], 'lut')
	gp.bezier([Point(100, 250), Point(0, 0), Point(365, 211)], 'casteljau')
	gp.bezier([Point(520, 180), Point(40, 20), Point(165, 311)], 'direct')
	gp.bezier([Point(30, 800), Point(400, 0), Point(800, 800), Point(800, 83)], 'approx')
	gp.pack()
	tkinter.mainloop()
