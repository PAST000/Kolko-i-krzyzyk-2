import Vertex from "./Vertex.js";
import Color from "./Color.js";

export default class Cuboid{
    constructor(cntr, X, Y, Z, fillClr = new Color(0, 0, 20, 0.5), lineClr = new Color(0, 0, 30, 0.7), lineWdt = 0.4){
        this.center = cntr;
        this.length = parseFloat(X);
        this.height = parseFloat(Y);
        this.width = parseFloat(Z);

        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;
        
        this.vertices = [
            new Vertex(cntr.x + this.length/2, cntr.y + this.height/2, cntr.z + this.width/2), 
            new Vertex(cntr.x + this.length/2, cntr.y + this.height/2, cntr.z - this.width/2),
            new Vertex(cntr.x - this.length/2, cntr.y + this.height/2, cntr.z - this.width/2),
            new Vertex(cntr.x - this.length/2, cntr.y + this.height/2, cntr.z + this.width/2),
            new Vertex(cntr.x + this.length/2, cntr.y - this.height/2, cntr.z + this.width/2), 
            new Vertex(cntr.x + this.length/2, cntr.y - this.height/2, cntr.z - this.width/2),
            new Vertex(cntr.x - this.length/2, cntr.y - this.height/2, cntr.z - this.width/2),
            new Vertex(cntr.x - this.length/2, cntr.y - this.height/2, cntr.z + this.width/2)
        ];

        this.faces = [  // Kolejność ścian: Góra, prawo, dół, lewo, tył, przód
            [this.vertices[0], this.vertices[1], this.vertices[2],this.vertices[3]],
            [this.vertices[0], this.vertices[4], this.vertices[5],this.vertices[1]],
            [this.vertices[4], this.vertices[5], this.vertices[6],this.vertices[7]],
            [this.vertices[2], this.vertices[6], this.vertices[7],this.vertices[3]],
            [this.vertices[0], this.vertices[4], this.vertices[7],this.vertices[3]],
            [this.vertices[1], this.vertices[5], this.vertices[6],this.vertices[2]]
        ];
    }

    changeFillColor(fillColor){ this.fill = fillColor; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};