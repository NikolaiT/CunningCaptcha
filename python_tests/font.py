import collections
import tkinter
import math
import geoprim

Point = collections.namedtuple('Point', 'x y')

class Glyph(geoprim.GeometricalPrimitives):
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
		# Proud on that one. First time I use collections ;)
		d = collections.defaultdict(list)
		for g in parseSVG.parse_path(fname):
			for k, v in g.items():
				if v:
					d[k].extend(v)
		self.data = d
					
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
			self.data[kw][i][j] = Point(int(p[0]*math.cos(a) + p[1]*math.sin(a)), int(math.cos(a)*p[1] - math.sin(p[0])*p[0]))
	
	# Translate the given points by delta x and delta y units.
	def _translate(self, dx, dy):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = Point(p[0]+dx, p[1]+dy)
	
	# Scale the vectors with the literals sx and sy
	# Apply reflection by the x-axis with sclaing with sx=1,sy=-1
	# and by the y-axis with sx=-1,sy=1
	def _scale(self, sx, sy):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = Point(int(sx*p[0]), int(sy*p[1]))
	
	def _shear(self, ky, kx):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = Point(int(p[0]+p[1]*ky), int(p[1]+p[0]*kx))
			
	def _skew(self, a):
		for kw, i, j, p in self._op():
			self.data[kw][i][j] = Point(int(p[0]+math.sin(a)*p[1]), p[1])
	
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
