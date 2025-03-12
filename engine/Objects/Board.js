import Vertex, {Vertex2D} from "./Vertex.js";
import Color from "./Color.js";
import { Project, withinQuad } from "../engineFunctions.js";
import Engine from "../Engine.js";

import Cube from "./Cube.js";
import Cuboid from "./Cuboid.js";
import Plane from "./Plane.js";
import Cross from "./Cross.js";
import PseudoSphere from "./PseudoSphere.js";
import Cone from "./Cone.js";

export default class Board{
    constructor(canvas, cnvWidth, cnvHeight, X, Y, Z, size, prec, 
                fillClr = new Color(0, 0, 80, 0.1), lineClr = new Color(0, 0, 30, 0.3), lineWdt = 0.3, pawnClr = new Color(40, 40, 40, 0.35)){
        this.length = parseFloat(size * X);
        this.height = parseFloat(size * Y);
        this.width = parseFloat(size * Z);
        this.singleSize = parseFloat(size);
        this.precision = parseInt(prec);
        this.center = new Vertex(cnvWidth/2, cnvHeight/2, 0);

        this.canvasWidth = parseFloat(cnvWidth);
        this.canvasHeight = parseFloat(cnvHeight);
        this.X = X;
        this.Y = Y;
        this.Z = Z;

        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;
        this.pawnColor = pawnClr;
        
        this.vertices = [];
        this.faces = [];       // Ściany "w środku"
        this.planes = [];      // Kafelki "na powierzchni"
        this.projections= [];  // Projekcje kafelków do sprawdzania kliknięcia
        this.layers = Array.from({ length: Z }, () => []);
        this.chosenLayer = -1; // -1 oznacza brak wybranej warstwy

        this.#generateVertices();
        this.#generateFaces();
        this.#generatePlanes();

        this.projections = Project(this.planes);
        canvas.addEventListener("mousedown", this.#mouseClick.bind(this));
        this.engine = new Engine(canvas, this.canvasWidth, this.canvasHeight, this.faces.concat(this.planes), this.center);
    }

    #generateVertices(){
        for(var i = 0; i <= this.Z; i++){
            for(var j = 0; j <= this.Y; j++)
                for(var k = 0; k <= this.X; k++)
                    this.vertices.push( 
                    new Vertex(parseFloat(k * this.singleSize - this.length/2), 
                               parseFloat(j * this.singleSize - this.height/2), 
                               parseFloat(i * this.singleSize - this.width/2)));
        }
    }

    #generateFaces(){
        for(var i = 0; i <= this.X; i++){
            this.faces.push(new Plane(new Vertex(0,0,0), 0, this.fillColor));
            this.faces[this.faces.length - 1].constructByVertices(
                this.vertices[i], this.vertices[i + (this.X + 1)*(this.Y)],
                this.vertices[i + (this.X + 1)*(this.Y + 1)*(this.Z) + (this.X + 1)*(this.Y)], this.vertices[i + (this.X + 1)*(this.Y + 1)*(this.Z)]
            );
        }
        for(var i = 0; i <= this.Y; i++){
            this.faces.push(new Plane(new Vertex(0,0,0), 0, this.fillColor));
            this.faces[this.faces.length - 1].constructByVertices(
                this.vertices[i*(this.X + 1)], this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + i*(this.X + 1)], 
                this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + i*(this.X + 1) + this.X], this.vertices[i*(this.X + 1) + this.X]
            );
        }
        for(var i = 0; i <= this.Z; i++){
            this.faces.push(new Plane(new Vertex(0,0,0), 0, this.fillColor));
            this.faces[this.faces.length - 1].constructByVertices(
                this.vertices[i*(this.X + 1)*(this.Y + 1)], this.vertices[i*(this.X + 1)*(this.Y + 1) + this.X], 
                this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) - 1], this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) - 1 - this.X]
            );
            if(i != this.Z) this.layers[i].push(this.faces[this.faces.length - 1]);
            if(i != 0) this.layers[i - 1].push(this.faces[this.faces.length - 1]);
        }

    }

    #generatePlanes(){
        for(var i = 0; i < this.X; i++)
            for(var j = 0; j < this.Y; j++){
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[j*(this.X + 1) + i], 
                    this.vertices[j*(this.X + 1) + 1 + i], 
                    this.vertices[(j + 1)*(this.X + 1) + 1 + i], 
                    this.vertices[(j + 1)*(this.X + 1) + i]
                );
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + j*(this.X + 1) + i], 
                    this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + j*(this.X + 1) + 1 + i], 
                    this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + (j + 1)*(this.X + 1) + 1 + i], 
                    this.vertices[(this.X + 1)*(this.Y + 1)*(this.Z) + (j + 1)*(this.X + 1) + i]
                );
                
                this.layers[0].push(this.planes[this.planes.length - 2]);
                this.layers[this.layers.length - 1].push(this.planes[this.planes.length - 1]);
            }
        for(var i = 0; i < this.Z; i++)
            for(var j = 0; j < this.Y; j++){
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j*(this.X + 1)], 
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j*(this.X + 1)],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + (j + 1)*(this.X + 1)],
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + (j + 1)*(this.X + 1)]
                );
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j*(this.X + 1) + this.X], 
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j*(this.X + 1) + this.X],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + (j + 1)*(this.X + 1) + this.X],
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + (j + 1)*(this.X + 1) + this.X]
                );

                this.layers[i].push(this.planes[this.planes.length - 2]);
                this.layers[i].push(this.planes[this.planes.length - 1]);
            }

        for(var i = 0; i < this.Z; i++)
            for(var j = 0; j < this.X; j++){
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j], 
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j + 1],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j + 1],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j]
                );
                this.planes.push(new Plane(new Vertex(0,0,0), 0, this.fillColor, this.lineColor, this.lineWidth));
                this.planes[this.planes.length - 1].constructByVertices(
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j + (this.X + 1)*this.Y], 
                    this.vertices[i*(this.X + 1)*(this.Y + 1) + j + 1 + (this.X + 1)*this.Y],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j + 1 + (this.X + 1)*this.Y],
                    this.vertices[(i + 1)*(this.X + 1)*(this.Y + 1) + j + (this.X + 1)*this.Y]
                );

                this.layers[i].push(this.planes[this.planes.length - 2]);
                this.layers[i].push(this.planes[this.planes.length - 1]);
            }
    }


    addPawn(type, pos){
        if(!pos instanceof Array) return;
        if(pos.length < 3) return;
        type.toLowerCase();
        let center = new Vertex(-this.singleSize*((this.X - 1)/2 - pos[0]),
                                -this.singleSize*((this.Y - 1)/2 - pos[1]), 
                                this.singleSize*((this.Z - 1)/2 - pos[2]));

        switch(type){
            case "cube":
                this.engine.addObject(new Cube(center, this.singleSize * 0.75, this.pawnColor));
                break;

            case "cuboid":
                this.engine.addObject(new Cuboid(center, this.singleSize * 0.75, this.singleSize * 0.65, this.singleSize * 0.6, this.pawnColor));
                break;

            case "cross":
                this.engine.addObject(new Cross(center, this.singleSize * 0.85, this.pawnColor));
                break;

            case "sphere":
            case "pseudosphere":
                this.engine.addObject(new PseudoSphere(center, this.singleSize * 0.45, this.precision, this.pawnColor));
                break;

            case "cone":
                this.engine.addObject(new Cone(center, this.singleSize * 0.45, this.singleSize * 0.8, this.precision, this.pawnColor));
                break;
            default: break;
        }
    }

    #mouseClick(M){
        if(M.button === 2){
            for(let i = 0; i < this.layers[this.chosenLayer].length; i++)
                this.layers[this.chosenLayer][i].changeFillColor(this.fillColor);
            this.draw();
            this.chosenLayer = -1;
            return;
        }

        let clicked = [];
        let maxZ = new Plane(new Vertex(0,0, Number.MIN_SAFE_INTEGER), 0);

        for(let i = 0; i < this.planes.length; i++)
            if(withinQuad(this.planes[i].vertices[0].project(), this.planes[i].vertices[1].project(), this.planes[i].vertices[2].project(), 
                          this.planes[i].vertices[3].project(), new Vertex2D(M.clientX - this.center.x, M.clientY - this.center.y)))
                clicked.push(i);
        if(clicked.length < 1) return;

        for(let i = 0; i < clicked.length; i++)
            if(this.planes[clicked[i]].center.z > maxZ.center.z) maxZ = this.planes[clicked[i]];

        for(let i = 0; i < this.layers.length; i++)
            if(this.layers[i].includes(maxZ)){
                this.chosenLayer = i; 
                break;
            }

        for(let i = 0; i < this.layers[this.chosenLayer].length; i++)
            this.layers[this.chosenLayer][i].changeFillColor(new Color(255, 0, 0, 0.5));
        this.draw();
    }

    setPrecision(prec){ this.precision = parseInt(prec); }
    draw(){ this.engine.draw(); }
};