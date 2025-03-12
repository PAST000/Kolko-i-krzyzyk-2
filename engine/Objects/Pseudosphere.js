import Color from "./Color.js";
import Vertex from "./Vertex.js";

export default class PseudoSphere {
    constructor(cntr, radius, prec, fillClr = new Color(0, 0, 50, 0.3), lineClr = new Color(0, 0, 80, 0.5), lineWdt = 0.4){  //precision - liczba wierzchołków w największym przekroju
        this.center = cntr;
        this.r = parseFloat(radius);
        this.precision = parseFloat(prec);
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
        if(this.precision < 4) return;
        this.faces = [];
        this.vertices = [];
        var theta = 2* Math.PI / this.precision;
        var num;

        for(var i = 0; i < this.precision; i++){
            for(var j = 0; j < this.precision; j++){
                num = this.precision * i + j;
                this.vertices.push(new Vertex(
                    this.center.x + Math.cos(j*theta)*Math.cos(i*theta)*this.r, 
                    this.center.y + Math.sin(j*theta)*this.r, 
                    this.center.z + Math.cos(j*theta)*Math.sin(i*theta)*this.r)
                );
                this.vertices[this.precision * i + j].text = num.toString();
            }
        }

        for(var i = 0; i < this.vertices.length; i++){
            if (this.vertices[i] === null || this.vertices[i] === undefined) continue;

            if((i + this.precision) >= this.vertices.length){ 
                if((i+1) % this.precision == 0) //Ostatni
                    this.faces.push([this.vertices[i],this.vertices[i + 1 - this.precision], this.vertices[0], this.vertices[this.precision - 1]]);
                else
                    this.faces.push([this.vertices[i],this.vertices[i + 1], this.vertices[i % this.precision + 1], this.vertices[i % this.precision]]);
            }
            else
                if((i+1) % this.precision == 0)  //Ostatni z "okręgu"
                    this.faces.push([this.vertices[i],this.vertices[i + 1 - this.precision], this.vertices[i + 1], this.vertices[i + this.precision]]);
                else
                    this.faces.push([this.vertices[i],this.vertices[i + 1], this.vertices[i + 1 + this.precision], this.vertices[i + this.precision]]);
        }
    }

    changeFillColor(fillColor){ this.fill = fillColor; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};