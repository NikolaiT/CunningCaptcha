import collections
# Generates the PHP code when the glyphs update. Manual editing is just a 
# major pain in the ass.
def gen_PHP_switch_statement(fname):
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
	print('{0} Glyph Data for {1} {2}'.format('*'*20, fname.rstrip('.svg').lstrip('glyphs/'), '*'*20))
	for k, v in d.items():
		print(k)
		for shape in v:
			print('array({0}),'.format(', '.join(['new Point(%d, %d)' % p for p in shape])))
	
def apply_on_all_glyphs():
	import os
	for dirpath, dirname, filenames in os.walk('glyphs/'):
		print('All Glyphs: %s' % ', '.join([g.rstrip('.svg') for g in filenames if g.endswith('.svg')]))
		for f in filenames:
			if f.endswith('.svg'):
				gen_PHP_switch_statement(os.path.join(dirpath, f))
				
if __name__ == '__main__':
	apply_on_all_glyphs()
