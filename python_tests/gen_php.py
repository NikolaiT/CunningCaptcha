# Author: Nikolai Tschacher
# Date 27.10.2013

# Generates the PHP code when the glyphs update. Because manual editing is just a 
# major pain in the ass. But this is also ugly :D

import collections
import sys
import os
try:
	import parseSVG
except ImportError as e:
	print('[-] Couldnt find svg parser module.')
	exit(0)

Point = collections.namedtuple('Point', 'x y')

def write_switch_case(fname, fd):
	d = collections.defaultdict(list)
	for g in parseSVG.parse_path(fname):
		for k, v in g.items():
			if v:
				d[k].extend(v)
	glyph = fname.rstrip('.svg').lstrip('glyphs/')
	fd.write('''\tcase '{0}':\n\t\t$this->glyphdata = array(\n'''.format(glyph))
	data = []
	j = 0
	for k, v in d.items():
		data = []
		j += 1
		fd.write('\t\t\t\'{0}\' => array(\n'.format(k))
		for i, shape in enumerate(v):
			fmt = ['array({0}), ', 'array({0})'][i == len(v)-1] # Last array elements don't have commas
			data.append(fmt.format(', '.join(['new CunningPoint(%d, %d)' % p for p in shape])))
		for i in range(0, len(data), 3):
			fd.write('\t\t\t\t{0}\n'.format(''.join(data[i:i+4])))
			end = ['\t\t\t),\n', '\t\t\t)\n'][j == len(d)]
		fd.write(end)
	fd.write('\t\t);\n\t\tbreak;\n')
	
def make_alphabet_array(fname, fd):
	d = collections.defaultdict(list)
	for g in parseSVG.parse_path(fname):
		for k, v in g.items():
			if v:
				d[k].extend(v)
	
	if fname.startswith("glyphs/") and fname.endswith(".svg"):
		glyph = fname[7:8]
	else:
		return False
	# Segment algin the glyphs and return the width, height of the glyph.
	width, height = segment_algin(d)
	print("Width and height of {} = ({}, {})".format(glyph, width, height))
	
	fd.write('''\t'{0}' => array(\n'''.format(glyph))
	fd.write('''\t\t'width' => {},\n'''.format(width))
	fd.write('''\t\t'height' => {},\n'''.format(height))
	fd.write('''\t\t'glyph_data' => array(\n'''.format(height))
	data = []
	j = 0
	for k, v in d.items():
		data = []
		j += 1
		fd.write('\t\t\t\'{0}\' => array(\n'.format(k))
		for i, shape in enumerate(v):
			fmt = ['array({0}), ', 'array({0})'][i == len(v)-1] # Last array elements don't have commas
			data.append(fmt.format(', '.join(['new Point(%d, %d)' % p for p in shape])))
		for i in range(0, len(data), 3):
			fd.write('\t\t\t\t{0}\n'.format(''.join(data[i:i+4])))
			end = ['\t\t\t),\n', '\t\t))\n'][j == len(d)]
		fd.write(end)
	fd.write('\t),\n')
	
def segment_algin(d):
	x_max, x_min, y_max, y_min = extremas(d)
	for key, shapes in d.items():
		for i, shape in enumerate(shapes):
			for ii, p in enumerate(shape):
				d[key][i][ii] = Point(d[key][i][ii].x - x_min, d[key][i][ii].y - y_min)
	return (x_max-x_min, y_max-y_min)
# Find extremas: Get maximal/minimal x component and maximal/minimal y component.
def extremas(d):
	x_max = y_max = -10000000000000
	x_min = y_min = 10000000000000

	for _, shapes in d.items():
		for shape in shapes:
			for p in shape:
				x_max = max(x_max, p.x)
				y_max = max(y_max, p.y)
				
				x_min = min(x_min, p.x)
				y_min = min(y_min, p.y)
	return (x_max, x_min, y_max, y_min)
	
def apply_on_all_glyphs(outfile):
	with open(outfile, 'a') as fd:
		# Write the header of the switch statement
		#fd.write('switch ($c) {\n')
		# Write every case
		fd.write('$alphabet = array(\n')
		for dirpath, dirname, filenames in os.walk('glyphs/'):
			for f in filenames:
				if f.endswith('.svg'):
					make_alphabet_array(os.path.join(dirpath, f), fd)
		# Write the last default case
		#fd.write('''\tdefault:\n\t\tbreak;\n''')
		# Write the last closing brace
		#fd.write('}')
		fd.write(');\n')
				
if __name__ == '__main__':
	try:
		apply_on_all_glyphs(sys.argv[1])
	except IndexError: 
		print("Usage: %s outfile" % sys.argv[0])
