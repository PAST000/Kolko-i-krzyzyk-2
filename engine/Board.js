import Vertex, {Vertex2D} from "./Objects/Vertex.js";
import Color from "./Objects/Color.js";
import { Project, withinQuad } from "./engineFunctions.js";
import Engine from "./Engine.js";

import Cube from "./Objects/Cube.js";
import Cuboid from "./Objects/Cuboid.js";
import Plane from "./Objects/Plane.js";
import Cross from "./Objects/Cross.js";
import PseudoSphere from "./Objects/PseudoSphere.js";
import Cone from "./Objects/Cone.js";

export default class Board{
    #fields;
    #vertices;

    constructor(canvas, cnvWidth, cnvHeight, X, Y, Z, size, prec, sens,
                fillClr = new Color(0, 0, 120, 0.2), lineClr = new Color(0, 0, 180, 0.2), lineWdt = 0.1, pawnClr = new Color(40, 40, 40, 0.35)){
        this.length = parseFloat(size * X);
        this.height = parseFloat(size * Y);
        this.width = parseFloat(size * Z);
        this.singleSize = parseFloat(size);
        this.precision = parseInt(prec);
        this.sensitivity = parseFloat(sens);
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
        this.layerColor = new Color(220, 0, 0, 0.3);
        this.chosenColor = new Color(0, 220, 0, 0.4);
        
        this.#vertices = [];
        this.#fields = [];
        this.layers = Array.from({ length: Z }, () => []);
        this.chosenLayer = -1; // -1 oznacza brak wybranej warstwy
        this.chosenField = -1; // -1 oznacza brak wybranego pola
        this.pawns = [];

        this.#generateVertices();
        this.#generateFields();

        canvas.addEventListener("mousedown", this.#mouseClick.bind(this));
        canvas.addEventListener("contextmenu", function (event) {
            if (this.chosenLayer >= 0) {
                event.preventDefault();
                return false; 
            }
        }, true);
        document.addEventListener("keydown", (e) => {
            if(this.chosenField < 0 || !(e.ctrlKey || e.shiftKey)) return;
            let cords = this.idToArr(this.chosenField);  // [x,y,z]

            if(e.ctrlKey)
                switch(e.code){
                    case "ArrowRight":
                        cords[0]++;
                        cords[0] %= this.X;
                        break;
                    case "ArrowLeft":
                        cords[0]--;
                        if(cords[0] < 0) cords[0] = this.X - 1;
                        break;
                    case "ArrowUp":
                        cords[1]--;
                        if(cords[1] < 0) cords[1] = this.Y - 1;
                        break;
                    case "ArrowDown":
                        cords[1]++;
                        cords[1] %= this.Y;
                        break;
                    default: return;
                }
            else if(e.shiftKey)
                switch(e.code){
                    case "ArrowDown":
                        cords[2]++;
                        cords[2] %= this.Z;
                        break;
                    case "ArrowUp":
                        cords[2]--;
                        if(cords[2] < 0) cords[2] = this.Z - 1;
                        break;
                    default: return;
                }

            if(cords[2] != this.chosenLayer){
                this.hideLayer(this.chosenLayer);
                this.chosenLayer = cords[2];
                this.showLayer(cords[2]);
            }
            this.hideField(this.chosenField);
            this.chosenField = this.arrToId(cords);
            this.showField(this.chosenField);
            this.draw();
        });

        this.engine = new Engine(canvas, this.canvasWidth, this.canvasHeight, [...this.#fields], this.center, this.sensitivity);
    }

    #generateVertices(){
        for(var i = 0; i <= this.Z; i++){
            for(var j = 0; j <= this.Y; j++)
                for(var k = 0; k <= this.X; k++)
                    this.#vertices.push( 
                    new Vertex(parseFloat(k * this.singleSize - this.length/2), 
                               parseFloat(j * this.singleSize - this.height/2), 
                               parseFloat(i * this.singleSize - this.width/2)));
        }
    }

    #generateFields(){
        for(let i = 0; i < this.Z; i++)
            for(let j = 0; j < this.Y; j++)
                for(let k = 0; k < this.X; k++){
                    let center = new Vertex(-this.singleSize*((this.X - 1)/2 - k),
                                            -this.singleSize*((this.Y - 1)/2 - j), 
                                            -this.singleSize*((this.Z - 1)/2 - i));
                    this.#fields.push(new Cube(center, this.singleSize, this.fillColor, this.lineColor, this.lineWidth));
                    this.layers[i].push(this.#fields[this.#fields.length - 1]);
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

    idToArr(id){
        if(id < 0 || id >= this.#fields.length) return false;
        let arr = [0,0,0];
        let m = this.X*this.Y;

        arr[2] = Math.floor(id / m);
        id -= arr[2] * m;
        m /= this.Y;

        arr[1] = Math.floor(id / m);
        id -= arr[1] * m;
        m /= this.X;

        arr[0] = Math.floor(id / m);
        return arr;
    }

    arrToId(v){ return v.length < 3 ? false : v[0] + v[1]*this.X + v[2]*this.X*this.Y; }


    hideField(field){
        if(field instanceof Cube){
            field.changeFillColor(this.chosenColor);
            return true;
        }

        if(field < 0 || field >= this.#fields.length) return false;
        if(this.chosenLayer < 0) this.#fields[field].changeFillColor(this.fillColor);
        else this.#fields[field].changeFillColor(
                this.layers[this.chosenLayer].includes(this.#fields[field]) ?
                this.layerColor : this.fillColor
             );
        return true;
    }

    showField(field){
        if(field instanceof Cube){
            field.changeFillColor(this.chosenColor);
            return true;
        }

        if(field < 0 || field >= this.#fields.length) return false;
        this.#fields[field].changeFillColor(this.chosenColor);
        return true;
    }

    hideLayer(id){
        if(id < 0 || id >= this.Z) return;
        for(let i = 0; i < this.layers[id].length; i++) 
            this.layers[id][i].changeFillColor(this.fillColor);
        return true;
    }

    showLayer(id){
        if(id < 0 || id >= this.Z) return;
        for(let i = 0; i < this.layers[id].length; i++) 
            this.layers[id][i].changeFillColor(this.layerColor);
        return true;
    }


    #mouseClick(M){
        if(M.button === 2){
            if(this.chosenLayer < 0) return;
            M.preventDefault();
            this.hideLayer(this.chosenLayer);
            this.chosenLayer = -1;
            return;
        }

        let clicked = [];
        let maxZ = new Cube(new Vertex(0,0, Number.MIN_SAFE_INTEGER), 0);

        for(let i = 0; i < this.#fields.length; i++)
            if(this.#fields[i] instanceof Cube)
                if(this.#fields[i].checkClick( new Vertex2D(M.clientX - this.center.x, M.clientY - this.center.y))) 
                    clicked.push(i);
        if(clicked.length < 1) return;

        if(this.chosenLayer < 0){
            for(let i = 0; i < clicked.length; i++)
                if(this.#fields[clicked[i]].center.z > maxZ.center.z) maxZ = this.#fields[clicked[i]];

            for(let i = 0; i < this.layers.length; i++)
                if(this.layers[i].includes(maxZ)){
                    this.chosenLayer = i; 
                    break;
                }

            this.showLayer(this.chosenLayer);
        }
        else{
            for(let i = 0; i < clicked.length; i++)
                if(this.#fields[clicked[i]].center.z > maxZ.center.z && this.layers[this.chosenLayer].includes(this.#fields[clicked[i]]))
                    maxZ = this.#fields[clicked[i]];

            if(!this.layers[this.chosenLayer].includes(maxZ)) return;
            this.hideField(this.chosenField);
            this.chosenField = this.#fields.indexOf(maxZ);
            maxZ.changeFillColor(this.chosenColor);
        }
        this.draw();
    }

    setPrecision(prec){ 
        this.precision = parseInt(prec); 
        this.engine.setPrecision(this.precision);
    }
    draw(){ this.engine.draw(); }
};