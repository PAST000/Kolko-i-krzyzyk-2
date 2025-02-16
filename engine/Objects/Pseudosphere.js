import Color from "./Color.js";
import Vertex from "./Vertex.js";

export default class PseudoSphere {
    constructor(cntr, radius, precision, fillClr = new Color(0, 0, 50, 0.3), lineClr = new Color(0, 0, 80, 0.5), lineWdt = 0.4){  //precision - liczba wierzchołków w największym przekroju
        this.center = cntr;
        this.r = parseFloat(radius);
        this.p = parseFloat(precision);
        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;

        this.vertices = [
            new Vertex(this.center.x + this.r, this.center.y, this.center.z),
            new Vertex(this.center.x, this.center.y + this.r, this.center.z),
            new Vertex(this.center.x - this.r, this.center.y, this.center.z),
            new Vertex(this.center.x, this.center.y - this.r, this.center.z),
            new Vertex(this.center.x, this.center.y, this.center.z + this.r),
            new Vertex(this.center.x, this.center.y, this.center.z - this.r)
        ];

        this.faces = [
            [this.vertices[0],this.vertices[1],this.vertices[4]],
            [this.vertices[0],this.vertices[1],this.vertices[5]],
            [this.vertices[0],this.vertices[3],this.vertices[4]],
            [this.vertices[0],this.vertices[3],this.vertices[5]],
            [this.vertices[2],this.vertices[1],this.vertices[4]],
            [this.vertices[2],this.vertices[1],this.vertices[5]],
            [this.vertices[2],this.vertices[3],this.vertices[4]],
            [this.vertices[2],this.vertices[3],this.vertices[5]]
        ];

        this.createVerticies();
    }

    createVerticies(){
        if(this.p < 4) return;
        this.faces = [];
        this.vertices = [];
        var theta = 2* Math.PI / this.p;
        var num;

        for(var i = 0; i < this.p; i++){
            for(var j = 0; j < this.p; j++){
                num = this.p*i + j;
                this.vertices.push(new Vertex(
                    this.center.x + Math.cos(j*theta)*Math.cos(i*theta)*this.r, 
                    this.center.y + Math.sin(j*theta)*this.r, 
                    this.center.z + Math.cos(j*theta)*Math.sin(i*theta)*this.r)
                );
                this.vertices[this.p*i + j].text = num.toString();
            }
        }

        for(var i = 0; i < this.vertices.length; i++){
            if (this.vertices[i] === null || this.vertices[i] === undefined) continue;

            if((i+this.p) >= this.vertices.length){ 
                if((i+1) % this.p == 0) //Ostatni
                    this.faces.push([this.vertices[i],this.vertices[i + 1 - this.p], this.vertices[0], this.vertices[this.p - 1]]);
                else
                    this.faces.push([this.vertices[i],this.vertices[i + 1], this.vertices[i % this.p + 1], this.vertices[i % this.p]]);
            }
            else
                if((i+1) % this.p == 0)  //Ostatni z "okręgu"
                    this.faces.push([this.vertices[i],this.vertices[i + 1 - this.p], this.vertices[i + 1], this.vertices[i + this.p]]);
                else
                    this.faces.push([this.vertices[i],this.vertices[i + 1], this.vertices[i + 1 + this.p], this.vertices[i + this.p]]);
        }
    }

    changeFillColor(fillColor){ this.fill = fillColor; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};