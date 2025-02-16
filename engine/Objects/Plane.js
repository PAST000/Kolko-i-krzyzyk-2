import Vertex from "./Vertex.js";
import Color from "./Color.js";

export default class Plane{
    constructor(cntr, len, fillClr = new Color(0, 0, 20, 0.5), lineClr = new Color(0, 0, 30, 0.7), lineWdt = 0.4){ 
        this.center = cntr;
        this.length = parseFloat(len);
        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;

        this.vertices = [
            new Vertex(this.center.x + this.length/2, this.center.y + this.length/2, this.center.z),
            new Vertex(this.center.x + this.length/2, this.center.y - this.length/2, this.center.z),
            new Vertex(this.center.x - this.length/2, this.center.y - this.length/2, this.center.z),
            new Vertex(this.center.x - this.length/2, this.center.y + this.length/2, this.center.z)
        ];

        this.faces = [[this.vertices[0], this.vertices[1], this.vertices[2], this.vertices[3]]];
    }

    constructByVertices(vert1, vert2, vert3, vert4){  
        this.center = new Vertex((vert1.x + vert2.x)/2, (vert1.y + vert3.y)/2, this.center.z);
        this.vertices = [vert1, vert2, vert3, vert4];
        this.faces = [[this.vertices[0], this.vertices[1], this.vertices[2], this.vertices[3]]];
    }

    changeFillColor(fillClr){ this.fillColor = fillClr; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};