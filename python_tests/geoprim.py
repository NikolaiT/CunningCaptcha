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
				
def beziersum(spline, t):
	p1, p2, p3, p4 = spline
	t2 = t * t
	t3 = t2 * t
	mt = 1-t
	mt2 = mt * mt
	mt3 = mt2 * mt
	return Point(p1.x*mt3 + 3*p2.x*mt2*t + 3*p3.x*mt*t2 + p4.x*t3, p1.y*mt3 + 3*p2.y*mt2*t + 3*p3.y*mt*t2 + p4.y*t3)
	
def derivative(spline, t):
	return Point(
			(3*(spline[1].x-spline[0].x))*(1-t)*(1-t) + (3*(spline[2].x-spline[1].x))*2*(1-t)*t + (3*(spline[3].x-spline[2].x))*t*t,
			(3*(spline[1].y-spline[0].y))*(1-t)*(1-t) + (3*(spline[2].y-spline[1].y))*2*(1-t)*t + (3*(spline[3].y-spline[2].y))*t*t
	)
# Returns a new rotated point
def rotate(p, a):
	return Point(int(math.cos(a)*p.x - math.sin(a)*p.y), int(math.sin(a)*p.x + math.cos(a)*p.y))
# Returns a new translated point	
def translate(p, dx, dy):
	return Point(p.x + dx, p.y + dy)
	
# Checks if a given line and cubic curve do intersect.
def intersects(line, spline):
	assert len(line) == 2 and len(spline) == 4
			
	# Now lets find the roots for the x-axis with Newton iterations
	EPSILON = 0.000000000001
	roots = []
	for t in range(1, 50):
		t /= 50
		tn = t
		for i in range(0, 20): # Maximally 20 iterations
			s = beziersum(spline, tn)
			d = derivative(spline, tn)
			
			if d.x != 0:
				if s.x <= 0 and s.x > -EPSILON:
					roots.append(tn)
					break
				elif s.x >= 0 and s.x < EPSILON:
					roots.append(tn)
					break
						
				tn = tn - (s.x / d.x)
			#print("[i] In round {} with t={} our tn is {} and the function evaluates to {}".format(i, t, tn, s.x))
	# Round
	roots = set(roots)
	print(roots)
	# Filter the range 0 <= t <= 1
	roots = [r for r in roots if r >= 0 and r <= 1]
	
	# translate such that the first line point becomes the origin
	#for i, e in enumerate(spline):
	#	spline[i] = translate(spline[i], -line[-1].x, -line[-1].y)
	#	
	# calculate rotation angle
	#if line[-1] != 0:
	#	alpha = math.pi/2 - math.atan(line[-1].x/(-line[-1].y))
	#	
	#	for i, e in enumerate(spline):
	#		spline[i] = rotate(spline[i], alpha)
	#
	#print("[i] Spline after being translated and rotated: {}".format(spline))
	
	
def lintersects(L1, L2):
	'''Checks whether two lines intersect in their segments. Returns
	   the intersection points if it exists, else returns False.
	'''
	assert len(L1) == 2 and len(L2) == 2 # Lines
	assert L1[1].x - L1[0].x != 0 and L2[1].x - L2[0].x != 0 # We don't want to deal with lines parallel to the y-axis (dx=0)
	
	m1 = (L1[1].y - L1[0].y) / (L1[1].x - L1[0].x)
	m2 = (L2[1].y - L2[0].y) / (L2[1].x - L2[0].x)
	n1 = (L1[1].x*L1[0].y - L1[0].x*L1[1].y) / (L1[1].x - L1[0].x)
	n2 = (L2[1].x*L2[0].y - L2[0].x*L2[1].y) / (L2[1].x - L2[0].x)
	
	# f(x) = m1x + n1 ; g(x) = m2x + n2
	# Intersection x-component: x = (n2 - n1)/(m1 - m2)
	
	if (abs(m1) - abs(m2) == 0): # Lines are parallel; Avoid division by zero
		return False
		
	x = (n2 - n1) / (m1 - m2)
	
	# Check whether the found point is on the line segments
	if ( (x > max(L1[0].x, L1[1].x) or x > max(L2[0].x, L2[1].x)) or # Found x is bigger than a maximal x-coordinate of a at least one line
	   (x < min(L1[0].x, L1[1].x) or x < min(L2[0].x, L2[1].x)) ):   # Found x is smaller than a minimal x-coordinate of a at least one line
		return False
	
	return Point(int(x), int(m1*x + n1))
			
# Some tests when executing module directly.
if __name__ == '__main__':
	master = tkinter.Tk()
	gp = GeometricalPrimitives(master, width=1000, height=1000)
	
	line1 = [Point(1000, 100), Point(149, 800)]
	line2 = [Point(300,800), Point(500, 0)]
	ip = lintersects(line1, line2)
	if ip:
		print("Found intersection: {}".format(ip))
		visualize = [Point(ip.x, 900), Point(ip.x, 0)]
		gp.line(visualize)
	else:
		print("No intersections found in the line segments")
	gp.line(line1)
	gp.line(line2)

	#spline = [Point(75, 46), Point(35, 200), Point(220, 260), Point(261, 190)]
	#gp.bezier(spline)
	#gp.bezier(line)
	#ip = intersects(line, spline)
	#gp.line([Point(ip.x, 1000), Point(ip.x, 0)])
	# gp.bezier([Point(300, 250), Point(0, 0), Point(465, 111)], 'lut')
	# gp.bezier([Point(100, 250), Point(0, 0), Point(365, 211)], 'casteljau')
	# gp.bezier([Point(520, 180), Point(40, 20), Point(165, 311)], 'direct')
	# gp.bezier([Point(30, 800), Point(400, 0), Point(800, 800), Point(800, 83)], 'approx')
	gp.pack()
	tkinter.mainloop()
