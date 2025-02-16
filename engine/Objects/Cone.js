import Color from "./Color.js";
import Vertex from "./Vertex.js";

export default class Cone {
    constructor(cntr, rad, height, precision, fillColor = new Color(0, 0, 50, 0.2), lineClr = new Color(0, 0, 80, 0.5), lineWdt = 0.4){  //precision - liczba wierzchołków podstawy
        this.center = cntr;
        this.baseCenter = new Vertex(cntr.x, cntr.y + height/2, cntr.z);
        this.radius = parseFloat(rad);
        this.height = parseFloat(height);
        this.precision = parseFloat(precision);
        this.fill = fillColor;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;

        this.vertices = [];
        this.faces = [];

        this.createVerticies();
        this.createFaces();
    }

    createVerticies(){
        if(this.precision < 3) return;
        this.vertices = [new Vertex(this.center.x, this.center.y - this.height/2, this.center.z)];
        var theta = 2 * Math.PI / this.precision;

        for(var i = 0; i < this.precision; i++){
            this.vertices.push(new Vertex(this.baseCenter.x + this.radius * Math.cos(theta * i),
                                          this.baseCenter.y, 
                                          this.baseCenter.z + this.radius * Math.sin(theta * i)));
        }
    }

    createFaces(){
        if(this.vertices.length == 0) return;
        for(let i = 1; i < this.precision; i++)
            this.faces.push([this.vertices[0], this.vertices[i], this.vertices[i + 1]]);
        this.faces.push([this.vertices[0], this.vertices[this.vertices.length - 1], this.vertices[1]]);
    }

    changeFillColor(fillColor){ this.fill = fillColor; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};