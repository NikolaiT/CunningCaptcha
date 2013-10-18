# Parse inkscap svg files and tries to obtain all bezier curves points and lines.
# Assumes that only one glyph is drawn and extracts ALL bezier points 
# in the main <g> (called 'layer1' in inkscape). Only heterogenous bezier 
# paths and straight lines are parsed correctly.
# Read: http://www.w3.org/TR/SVG/paths.html#Introduction
# Author: Nikolai Tschacher
# Date: 14.10.2013
# Site: incolumitas.com

import sys
try:
    from lxml import etree
except ImportError:
    sys.stderr.write('Ohh boy, it seems that you didn\'t install lxml. Try "pip install lxml"\n')
    sys.exit(0)

class InvalidGlyphShape(Exception):
    '''
    This Exception is raised when the parser discovers a <path> element
    that he cannot read.
    '''
    
# lxml is awesome   
def parse_glyph(fname):
    for event, element in etree.iterparse(fname, tag='{*}g'): # "{*}" includes the whole xml namespace
        if element.get('id') == 'layer1': # Inkscape packs all the shapes in a <g> container and calls it "layer1" by default
            for path in element.iter(tag='{*}path'): # Find ALL(!) path elements
                yield path.get('d')

def parse_path(fname):
    # What format do we need? List of sequence of tuples
    # like [[(43,233), (4434, 222), (87, 387)], [(63,23), (44, 12), (87, 337)], ...]
    # each of them constituting points of a cubic Bézier spline.
    # ... Analogeous for the other shapes...
    for d in parse_glyph(fname):
        lines = []
        c_splines = []
        q_splines = []
        data = d.split(' ')
        
        if data[-1] != 'z':
            raise InvalidGlyphShape('Path is not a closed shape')
        
        # If a moveto is followed by multiple pairs of coordinates,
        # the subsequent pairs are treated as implicit lineto commands.
        cmd = data.pop(0)   
        if cmd not in ('M', 'm'):
            raise InvalidGlyphShape('No move command Mm')
        else: # Check for implicit lineto cmd.
            if (not is_cmd(data[0]) and not is_cmd(data[1])):
                lines.extend([c for c in chunks(sublist(data[0:]), 2)]) # Lil hack here :)
                
        # Magic parsing begins. Very basic parser.
        for i, e in enumerate(data):
            if e in 'Cc':
                data.pop(i)
                c_splines.extend([c for c in chunks(sublist(data[i-1:]), 4)])
            if e in 'Qq':
                data.pop(i)
                q_splines.extend([c for c in chunks(sublist(data[i-1:]), 3)])
            if e in 'Ll':
                data.pop(i)
                lines.extend([c for c in chunks(sublist(data[i-1:]), 2)])
            if e in 'Zz' and i == len(data)-1: # closepath (close the current shape by drawing a line to the last moveto)
                # With "closepath", the end of the final segment of the subpath is "joined"
                # with the start of the initial segment of the subpath.
                lines.append([data[i-1], data[0]])
        
        cleaned = clean(cubic_bezier=c_splines,
                     quadratic_bezier=q_splines,
                     simple_lines=lines)
            
        yield cleaned
                     
# clean the data. For some reason my algorithm yields single points. Just
# remove them and the data is solid.     
def clean(**args):
    keys = sorted(args.keys())
    for kw in keys:
        args[kw] = [e for e in args[kw] if len(e) > 1]

    
    # Make integer coordinates ready to be rasterized.   
    for kw in keys:
        args[kw] = [[tuple([int(float(i)) for i in p.split(',')]) for p in geo_el] for geo_el in args[kw]]
        
    return args
            
# print the glyph data.
def pretty_print(args):
    keys = sorted(args.keys())
    print('----------------------- Next <path> data -------------------------')
    
    for kw in keys:
        print('\n***********\nPoint data for %s' % kw)
        print(args[kw])
                
# Returns a sublist up to the next cmd.
def sublist(l):
    for i, e in enumerate(l):
        if e in 'CcQqLlZz': # SVG path commands
            return l[0:i]
            
# Check if element is a command within d attribute in <path> element.
def is_cmd(e):
    if e in 'CcQqLlZzMm':
        return True
    else:
        return False
        
# Make chunks                          
def chunks(l, n):
    for i in range(0, len(l), n-1):
        yield l[i:i + n]
    
if __name__ == '__main__':
    try:
        open(sys.argv[1], 'r') # raises an exception if file can't be located
        for i in parse_path(sys.argv[1]):
            pretty_print(i)
    except FileNotFoundError as e:
        print('[-] No such file, sir')
    except IndexError:
        print('[-] Usage: %s SvgFile' % sys.argv[0])
