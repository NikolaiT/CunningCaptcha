# Testing speed to draw 500 bezier points with algorithm from.

import timer
import random
from pascal_bezier import *

R = random.randint
points = [[(R(0,999), R(0,999)), (R(0,999), R(0,999)), (R(0,999), R(0,999))] for i in range(500)]

ts = [t/100.0 for t in range(100)]

im = Image.new('RGBA', (1000, 1000), (155, 255, 0, 0))
draw = ImageDraw.Draw(im)
draw.rectangle([(0,0),(1000,1000)], fill=(0,0,0)) # Black as the night

with timer.Timer('testing') as t:
	for p in points:
		bezier = make_bezier(p)
		points = bezier(ts)
		#draw.polygon(points)
	#im.save('outt.png')
