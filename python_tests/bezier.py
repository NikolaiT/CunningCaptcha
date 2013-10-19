import tkinter
import math

class Bezier(tkinter.Canvas):
    '''
    Simple and slow algorithm to draw quadratic and 
    cubic Bézier curves. Heavily inspired by http://pomax.github.io/bezierinfo/#control
    This code should just prove a concept and is not intended to be 
    used in a real world app...
    Author: Nikolai Tschacher
    Date: 07.10.2013
    '''
    # Because Canvas doesn't support simple pixel plotting,
    # we need to help us out with a line with length 1 in
    # positive x direction.
    def plot_pixel(self, x0, y0):
        self.create_line(x0, y0, x0+1, y0)
        
    # Calculates the quadtratic Bézier polynomial for 
    # the n+1=3 coordinates.
    def quadratic_bezier_sum(self, t, w):
        t2 = t * t 
        mt = 1-t
        mt2 = mt * mt
        return w[0]*mt2 + w[1]*2*mt*t + w[2]*t2
        
    # Calculates the cubic Bézier polynomial for 
    # the n+1=4 coordinates.
    def cubic_bezier_sum(self, t, w):
        t2 = t * t
        t3 = t2 * t
        mt = 1-t
        mt2 = mt * mt
        mt3 = mt2 * mt
        return w[0]*mt3 + 3*w[1]*mt2*t + 3*w[2]*mt*t2 + w[3]*t3

    def draw_quadratic_bez(self, p1, p2, p3):
        t = 0
        while (t < 1):
            x = self.quadratic_bezier_sum(t, (p1[0], p2[0], p3[0]))
            y = self.quadratic_bezier_sum(t, (p1[1], p2[1], p3[1]))
            # self.plot_pixel(math.floor(x), math.floor(y))
            self.plot_pixel(x, y)
            t += 0.001 # 1000 iterations. If you want the curve to be really
                       # fine grained, consider "t += 0.0001" for ten thousand iterations.
    
    def draw_cubic_bez(self, p1, p2, p3, p4):
        t = 0
        while (t < 1):
            x = self.cubic_bezier_sum(t, (p1[0], p2[0], p3[0], p4[0]))
            y = self.cubic_bezier_sum(t, (p1[1], p2[1], p3[1], p4[1]))
            self.plot_pixel(math.floor(x), math.floor(y))
            t += 0.001
    

if __name__ == '__main__':
	master = tkinter.Tk()
	w = Bezier(master, width=1000, height=1000)
	w.pack()

	# Finally draw some Bézier curves :)
	#w.draw_quadratic_bez((70, 250), (62, 59), (250, 61))
	#w.draw_quadratic_bez((170,77), (162, 159), (210, 161))
	w.draw_quadratic_bez((0, 100), (162, 89), (250, 61))
	#w.draw_cubic_bez((120, 160), (35, 200), (153, 268), (165, 70))
	tkinter.mainloop()
