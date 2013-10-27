# Testing drawing 500 bezier points with algorithm from http://stackoverflow.com/questions/246525/how-can-i-draw-a-bezier-curve-using-pythons-pil/2292690#2292690

import timer
import random
from pascal_bezier import *

NUM_CURVES = 500
NUM_STEPS = 1000

R = random.randint
points = [[(R(0,999), R(0,999)), (R(0,999), R(0,999)), (R(0,999), R(0,999))] for i in range(NUM_CURVES)]

ts = [t/100.0 for t in range(NUM_STEPS)]

im = Image.new('RGBA', (1000, 1000), (155, 255, 0, 0))
draw = ImageDraw.Draw(im)
draw.rectangle([(0,0),(1000,1000)], fill=(0,0,0)) # Black as the night

with timer.Timer('Testing plotting %u bezier points with Camerons implementation') as t:
	for p in points:
		bezier = make_bezier(p)
		points = bezier(ts)
		#draw.polygon(points)
	#im.save('outt.png')
