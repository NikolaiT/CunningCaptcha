import tkinter

class InvalidInputError(Exception):
    pass

class Casteljau(tkinter.Canvas):
    '''
    Implementation of de Casteljau's algorithm for drawing Bézier curves.
    Implemented along the submittal of http://pomax.github.io/bezierinfo/#control.
    Author: Nikolai Tschacher
    Date: 07.10.2013
    '''
    # Because Canvas doesn't support simple pixel plotting,
    # we need to help us out with a line with length 1 in
    # positive x direction.
    def plot_pixel(self, x0, y0):
        self.create_line(x0, y0, x0+1, y0)
        
    def draw_curve(self, points, t):
        # Check that input parameters are valid. We don't check wheter 
        # the elements in the tuples are of type int or float.
        for p in points:
            if (not isinstance(p, tuple) or not len(p) == 2):
                raise InvalidInputError('points is not a list of points(tuples)')
        if (len(points) == 1):
            self.plot_pixel(points[0][0], points[0][1])
        else:
            newpoints = []
            for i in range(0, len(points)-1):
                x = (1-t) * points[i][0] + t * points[i+1][0]
                y = (1-t) * points[i][1] + t * points[i+1][1]
                newpoints.append((x, y))
            self.draw_curve(newpoints, t)
    
    # Use De Casteljau's algorithm with recursion eliminated, but the same
    # geometrical approach. Idea: Eliminate the expensive stack frame generation
    # that recursion comes with. Only quadratical Bézier curves.
    def draw_point2(self, points, t):
        # Vector addition of P0+P1
        q0_x, q0_y = ((1-t) * points[0][0] + t * points[1][0],
						(1-t) * points[0][1] + t * points[1][1])
        q1_x, q1_y = ((1-t) * points[1][0] + t * points[2][0],
						(1-t) * points[1][1] + t * points[2][1])
        
        b_x, b_y = ((1-t)*q0_x + t*q1_x, (1-t)*q0_y + t*q1_y)
        self.plot_pixel(b_x, b_y)
          
    # Usage function for the algorithm.
    def draw(self, points):
        t = 0
        while (t <= 1):
            self.draw_curve(points, t)
            t += 0.001

if __name__ == '__main__':
    master = tkinter.Tk()
    w = Casteljau(master, width=1000, height=1000)
    w.pack()

    # Finally draw some Bézier curves :)
    w.draw([(70, 250), (462, 159), (0, 0)])
    #w.draw([(133, 267), (121, 28), (198, 270), (210, 29)])
    tkinter.mainloop()
