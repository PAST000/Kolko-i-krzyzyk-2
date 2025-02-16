import Vertex from "./Vertex.js";
import Color from "./Color.js";

export default class Cube{
    constructor(cntr, len, fillClr = new Color(0, 0, 20, 0.5), lineClr = new Color(0, 0, 30, 0.7), lineWdt = 0.4){
        this.center = cntr;
        this.length = parseFloat(len);
        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;
        
        this.vertices = [
            new Vertex(cntr.x + len/2, cntr.y + len/2, cntr.z + len/2), 
            new Vertex(cntr.x + len/2, cntr.y + len/2, cntr.z - len/2), 
            new Vertex(cntr.x - len/2, cntr.y + len/2, cntr.z - len/2), 
            new Vertex(cntr.x - len/2, cntr.y + len/2, cntr.z + len/2), 
            new Vertex(cntr.x + len/2, cntr.y - len/2, cntr.z + len/2), 
            new Vertex(cntr.x + len/2, cntr.y - len/2, cntr.z - len/2), 
            new Vertex(cntr.x - len/2, cntr.y - len/2, cntr.z - len/2), 
            new Vertex(cntr.x - len/2, cntr.y - len/2, cntr.z + len/2)
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