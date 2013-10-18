from timer import Timer
from bezier import Bezier # Simple bezier drawing algorithm directly derived from calculus.
from casteljau import Casteljau # Drawing curves using de Casteljau's algorithm.
import random

# Overwrite Bezier class and Casteljau class to disable the GUI functions. We just want to 
# mesure the algorithm's performance not the graphical toolkit overhead...

# Pre calculate random points for quadratic and cubic Bézier simulation for
# performance tests.
NUMBER_OF_CURVES = 500

R = random.randrange
pp = [[(R(500), R(500)), (R(500), R(500)), (R(500), R(500))] for i in range(NUMBER_OF_CURVES)]
pp4 = [[(R(500), R(500)), (R(500), R(500)), (R(500), R(500)), (R(500), R(500))] for i in range(NUMBER_OF_CURVES)]

class BezierPerf(Bezier):
	def __init__(self):
		self.test1()
		self.test2()
	def plot_pixel(self, x0, y0):
		pass # Nothin here oO
	def test1(self):
		with Timer('Testing quadratic bezier with direct approach') as t:
			for points in pp:
				self.draw_quadratic_bez(points[0], points[1], points[2])
	def test2(self):
		with Timer('Testing cubic curves with direct approach') as t:
			for points in pp4:
				self.draw_cubic_bez(points[0], points[1], points[2], points[3])
			

class CasteljauPerf(Casteljau):
	def __init__(self):
		self.test1()
		self.test2()
	def plot_pixel(self, x0, y0):
		pass # No drawing please.
	def test1(self):
		with Timer('Testing quadratic bezier curves with De Casteljau') as t:
			for points in pp:
				self.draw(points)
	def test2(self):
		with Timer('Testing cubic bezier curves with De Casteljau') as t:
			for points in pp4:
				self.draw(points)

if __name__ == '__main__':
	test2 = CasteljauPerf()
	test = BezierPerf()

