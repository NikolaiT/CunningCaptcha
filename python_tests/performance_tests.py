from timer import Timer
from bezier import Bezier # Simple bezier drawing algorithm directly derived from calculus.
from casteljau import Casteljau # Drawing curves using de Casteljau's algorithm.
import geoprim # All geometrical primitives. Updated extensivly.
import random

# Overwrite Bezier class and Casteljau class to disable the GUI functions. We just want to 
# mesure the algorithm's performance not the graphical toolkit overhead...

# Pre calculate random points for quadratic and cubic Bézier simulation for
# performance tests.
NUMBER_OF_CURVES = 500

R = random.randrange
P = geoprim.Point
pp = [[P(R(500), R(500)), P(R(500), R(500)), P(R(500), R(500))] for i in range(NUMBER_OF_CURVES)]
pp4 = [[P(R(500), R(500)), P(R(500), R(500)), P(R(500), R(500)), P(R(500), R(500))] for i in range(NUMBER_OF_CURVES)]

class GeoPrimPerf_1(geoprim.GeometricalPrimitives):
	def __init__(self):
		self.test1()
		self.test2()
	def _plot_pixel(self, x, y):
		pass # No drawing please.
	def test1(self):
		with Timer('Testing quadratic bezier curves with direct approach') as t:
			for points in pp:
				self.bezier(points, algo='direct')
	def test2(self):
		with Timer('Testing cubic bezier curves with direct approach') as t:
			for points in pp4:
				self.bezier(points, algo='direct')
				
class GeoPrimPerf_2(geoprim.GeometricalPrimitives):
	def __init__(self):
		self.test1()
		self.test2()
	def _plot_pixel(self, x, y):
		pass # No drawing please.
	def test1(self):
		with Timer('Testing quadratic bezier curves with De Casteljaus algorithm') as t:
			for points in pp:
				self.bezier(points, algo='casteljau')
	def test2(self):
		with Timer('Testing cubic bezier curves with De Casteljaus algorithm') as t:
			for points in pp4:
				self.bezier(points, algo='casteljau')
				
class GeoPrimPerf_3(geoprim.GeometricalPrimitives):
	def __init__(self):
		self.test1()
		self.test2()
	def _plot_pixel(self, x, y):
		pass # No drawing please.
	def test1(self):
		with Timer('Testing quadratic bezier curves with approximation') as t:
			for points in pp:
				self.bezier(points, algo='approx')
	def test2(self):
		with Timer('Testing cubic bezier curves with approximation') as t:
			for points in pp4:
				self.bezier(points, algo='approx')
				
class GeoPrimPerf_4(geoprim.GeometricalPrimitives):
	def __init__(self):
		self.test1()
		self.test2()
	def _plot_pixel(self, x, y):
		pass # No drawing please.
	def test1(self):
		with Timer('Testing quadratic bezier curves with lookup tables') as t:
			for points in pp:
				self.bezier(points, algo='lut')
	def test2(self):
		with Timer('Testing cubic bezier curves with lookup tables') as t:
			for points in pp4:
				self.bezier(points, algo='lut')

if __name__ == '__main__':
	print('[+] Testing task is to draw %d randomly generated Bézier splines with different algorithms. Approximation uses %d segments.'
				% (NUMBER_OF_CURVES, geoprim.GeometricalPrimitives._NUM_SEGMENTS))
	direct = GeoPrimPerf_1()
	casteljau = GeoPrimPerf_2()
	approx = GeoPrimPerf_3()
	lut = GeoPrimPerf_4()

