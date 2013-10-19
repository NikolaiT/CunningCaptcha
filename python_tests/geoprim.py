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
	Date: 13.10.2013
	'''
	
	# Because Canvas doesn't support simple pixel plotting,
	# we need to help us out with a line with length 1 in
	# positive x direction.
	def _plot_pixel(self, p):
		self.create_line(p.x, p.y, p.x+1, p.y)

	def _draw_quadratic_bez(self, p1, p2, p3):
		t = 0
		while (t < 1):
			t2 = t*t
			mt = t-1
			mt2 = mt*mt
			self._plot_pixel(Point(p1.x*mt2 + p2.x*2*mt*t + p3.x*t2, p1.y*mt2 + p2.y*2*mt*t + p3.y*t2))
			t += 0.001
	
	def _draw_cubic_bez(self, p1, p2, p3, p4):
		t = 0
		while (t < 1):
			t2 = t * t
			t3 = t2 * t
			mt = 1-t
			mt2 = mt * mt
			mt3 = mt2 * mt
			self._plot_pixel(Point(p1.x*mt3 + 3*p2.x*mt2*t + 3*p3.x*mt*t2 + p4.x*t3,
										p1.y*mt3 + 3*p2.y*mt2*t + 3*p3.y*mt*t2 + p4.y*t3))
			t += 0.001
			
	def _casteljau(self, points, t):
		# Check that input parameters are valid. We don't check wheter 
		# the elements in the tuples are of type int or float.
		for p in points:
			if not isinstance(p, Point):
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
				self._draw_quadratic_bez(*points)
			elif (len(points) == 4):
				self._draw_cubic_bez(*points)
			else:
				raise Exception('Direct approach draws only quadratic and cubic Béziers')
				
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
			self._plot_pixel(Point(x0, y0))
			if (x0==x1 and y0==y1):
				break
			e2 = 2*err
			if (e2 >= dy):
				err += dy
				x0 += sx     
			if (e2 <= dx):
				err += dx
				y0 += sy
