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
			data.append(fmt.format(', '.join(['new Point(%d, %d)' % p for p in shape])))
		for i in range(0, len(data), 3):
			fd.write('\t\t\t\t{0}\n'.format(''.join(data[i:i+4])))
			end = ['\t\t\t),\n', '\t\t\t)\n'][j == len(d)]
		fd.write(end)
	fd.write('\t\t);\n\t\tbreak;\n')
	
def apply_on_all_glyphs(outfile):
	with open(outfile, 'a') as fd:
		# Write the header of the switch statement
		fd.write('switch ($c) {\n')
		# Write every case
		for dirpath, dirname, filenames in os.walk('glyphs/'):
			for f in filenames:
				if f.endswith('.svg'):
					write_switch_case(os.path.join(dirpath, f), fd)
		# Write the last default case
		fd.write('''\tdefault:\n\t\tbreak;\n''')
		# Write the last closing brace
		fd.write('}')
				
if __name__ == '__main__':
	try:
		apply_on_all_glyphs(sys.argv[1])
	except IndexError: 
		print("Usage: %s outfile" % sys.argv[0])
