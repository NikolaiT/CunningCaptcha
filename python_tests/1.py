# Just for science. This code is not based on my own ideas. It's mainly
# snippets ported from Javascript or C (depends on the source) ported
# to Python. This just helps me to pick the right algorithm for my captcha 
# project, since I need some geometrical primitives for my character plotting :)

import tkinter
import math

class MyCanvas(tkinter.Canvas):
    # Because Canvas doesn't support simple pixel plotting,
    # we need to help us out with a line with length 1 in
    # positive x direction.
    def plot_pixel(self, x0, y0):
        self.create_line(x0, y0, x0+1, y0)
    # Plots all 8 symmetric points for circles.
    # (x,y) are the circle coordinates. (dx, dy) is the offset such
    # that we can determine where we want to draw the circle.
    def plot_symmetrical_pixels(self, x, y, dx, dy):
        self.plot_pixel(dx+x, dy+y)
        self.plot_pixel(dx+x, dy-y)
        self.plot_pixel(dx-x, dy+y)
        self.plot_pixel(dx-x, dy-y)
        # Flip/mirror along f(x) = x <=> y = x
        self.plot_pixel(dx+y, dy+x)
        self.plot_pixel(dx+y, dy-x)
        self.plot_pixel(dx-y, dy+x)
        self.plot_pixel(dx-y, dy-x)
    # Plots a bezier curve in the canvas.
    # http://pomax.github.io/bezierinfo/
    # Currently not working :/
    def bezier(self, x0, y0, x1, y1, x2, y2):
        sx = x2-x1
        sy = y2-y1
        xx = x0-x1
        yy = y0-y1
        xy = dx = dy = err = 0
        cur = xx*sy-yy*sx
        
        if (xx*sx > 0 or yy*sy > 0):
            print('[-] Sign of gradient changes.')
            return
            
        if (sx*sx+sy*sy > xx*xx+yy*yy):
            x2 = x0
            x0 = sx+x1
            y2 = y0
            y0 = sy+y1
            cur = -cur
            
        if (cur != 0):
            xx += sx
            sx = [-1, 1][x0<x2]
            xx *= sx
            
            yy += sy
            sy = [-1, 1][y0<y2]
            yy *= sy
            
            xy = 2*xx*yy
            xx *= xx
            yy *= yy
            
            if ((cur*sx*sy) < 0):
                xx, yy, xy, cur = (-xx, -yy, -xy, -cur)
            
            dx = 4.0*sy*cur*(x1-x0)+xx-xy
            dy = 4.0*sx*cur*(y0-y1)+yy-xy
            xx += xx
            yy += yy
            err = dx+dy+xy
            
            while(dy < dx):
                self.plot_pixel(x0, y0)
                #print('Plotting %d|%d...' % (x0, y0))
                if (x0 == x2 and y0 == y2):
                    return
                y1 = (2*err < dx)
                if (2*err > dy):
                    x0 += sx
                    dx -= xy
                    dy += yy
                    err += dy
                if (y1):
                    y0 += sy
                    dy -= xy
                    dy += xx
                    err += dy
            self.plot_line(x0, y0, x2, y2)
            
    # t is a float between 0 and 1
    # w is a set of weights (The coordinates).
    def quadratic_bezier_sum(self, t, w):
        t2 = t * t 
        mt = 1-t
        mt2 = mt * mt
        return w[0]*mt2 + w[1]*2*mt*t + w[2]*2
        
    def cubic_bezier_sum(self, t, w):
        pass
        
    def DrawBez(self, x0, y0, x1, y1, x2, y2):
        t = 0
        while (t < 1):
            x = self.quadratic_bezier_sum(t, [x0, x1, x2])
            y = self.quadratic_bezier_sum(t, [y0, y1, y2])
            # print('Plotting pixel: %d|%d' % (x,y))
            self.plot_pixel(math.floor(x), math.floor(y))
            t += 0.001 # 1000 iterations. If you want the curve to be really
                       # fine grained, consider "t += 0.0001" for ten thousand iterations.
    
    # Plots a circle using Methode of Horn. See
    # http://de.wikipedia.org/wiki/Rasterung_von_Kreisen
    # z is the thickness of the circle.
    def circle(self, x0, y0, r, z):
        d = -r
        x = r
        y = 0
        while (y <= x):
            #print('x=%d, y=%d, d=%d' %(x, y, d))
            self.plot_symmetrical_pixels(x, y, x0, y0)
            d += 2*y + 1
            y += 1
            if (d >= 0):
                d += -2*x + 2
                x += -1
    # Plots a bresenham line. Implemented along reading the excellent
    # article http://de.wikipedia.org/wiki/Rasterung_von_Linien
    # The working of bresenham algorith simplified:
    # You have two points P1:=(x0,y0) and P2:=(x1,y1) that you want to
    # connect with a line on a pixel grid. So the algorithm basically calculates
    # for every step in the x direction (x+=1) wheter a step in the
    # y direction (y+=1) is necessary such that we walk along the closest approximation of the line.
    # For every step in the x-direction we consider the pixels (x_i+1,y_i) and
    # (x_i+1,y_i+1). Now we observe the vertical middle between these
    # pixels(Lets call it M_i). If M_i is situated above the line, we pick
    #(x_i+1,y_i) as the next pixel to be plotted.
    # Accordingly, if M_i lies under the line, we consider (x_i+1,y_i+1) as the closest
    # approximation to the line and we therefore plot it.
    def bresenham_line(self, x0, y0, x1, y1, z):
        x = x0
        y = y0
        dx = x1-x0
        dy = y1-y0
        
        D = 2*dy - dx
        self.plot_pixel(x, y)
        
        while (x <= x1):
            if (D > 0):
                y += 1
                self.plot_pixel(x, y)
                D += (2*dy-2*dx)
            else:
                self.plot_pixel(x, y)
                D += 2*dy
            x += 1
    # Another line implementation. Needs a slow division at the beginning, but
    # then just simple additions/subtraction operations. The idea behind this 
    # algorithm is to continuously add the slope of the curve to a variable c
    # and increase y+=1 whenever this variable becomes bigger or equal to one.
    def line(self, x0, y0, x1, y1):
        x = 0
        y = 0
        dx = abs(x1 - x0)
        dy = abs(y1 - y0)
        m = dy/dx
        c = 0.5
        self.plot_pixel(x0+x, y0+y)
        while (x <= x1):
            c += m
            if (c >= 1):
                y += 1
                c -= 1
            x += 1
            self.plot_pixel(x0+x, y0+y)
    # Yet another implementation taken from
    # http://members.chello.at/easyfilter/bresenham.html
    # This alogrithm is capable to plot lines with all possible slopes
    # compared to the two previous ones.    
    def plot_line(self, x0, y0, x1, y1):
        dx = abs(x1-x0)
        dy = -abs(y1-y0)
        sx = [-1, 1][x0<x1]
        sy = [-1, 1][y0<y1]
        err = dx+dy
        e2 = 1
        
        while (True):
            self.plot_pixel(x0, y0)
            if (x0==x1 and y0==y1):
                break
            e2 = 2*err
            if (e2 >= dy):
                err += dy
                x0 += sx
                
            if (e2 <= dx):
                err += dx
                y0 += sy


# draws and axis-aligns a bézier curvature. Example.
# first translate such that the P0 becomes the (0,0) point.
# then rotate until the y-axis of the last point becomes zero.
def align_curve(w):
	# first draw a coordinate system. Let's assume that (200, 750) Is our
	# (0,0) point of the coordinate system
	w.plot_line(200, 150, 200, 800) # y-axis
	w.plot_line(150, 750, 800, 750) # x-axis
	
	# our bézier curve that we want to axis-algin.
	w.DrawBez(310, 256, 317, 179, 381, 151)
	
	
master = tkinter.Tk()
w = MyCanvas(master, width=1100, height=1100)
w.pack()

#w.circle(200, 150, 60, 0)
#w.bresenham_line(100, 100, 150, 120, 0)
#w.line(20, 160 ,290, 120)
w.plot_line(0, 0, 200, 200)
w.DrawBez(70, 250, 62, 59, 0, 0)
#w.DrawBez(171, 52, 42, 144, 206, 224)
#align_curve(w)
tkinter.mainloop()
