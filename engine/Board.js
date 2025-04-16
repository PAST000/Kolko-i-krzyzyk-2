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
    #fields = [];
    #vertices = [];
    #pawns = [];
    #separator = ',';
    #playerIDs = [];
    #pawnTypes = [];  

    constructor(cnv, cnvWidth, cnvHeight, X, Y, Z, size, prec, sens,
                fillClr = new Color(0, 0, 120, 0.2), lineClr = new Color(0, 0, 180, 0.2), lineWdt = 0.1, pawnClr = new Color(40, 40, 40, 0.35)){
        this.length = parseFloat(size * X);  //
        this.height = parseFloat(size * Y);  // Rozmiary planszy
        this.width = parseFloat(size * Z);   //
        this.singleSize = parseFloat(size);
        this.precision = parseInt(prec);
        this.armFactor = 0.11;

        this.canvas = cnv;
        this.canvasRect = this.canvas.getBoundingClientRect();
        this.center = new Vertex(cnvWidth/2, cnvHeight/2, 0);
        this.X = X;
        this.Y = Y;
        this.Z = Z;

        this.fillColor = fillClr;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;
        this.pawnColor = pawnClr;
        this.layerColor = new Color(220, 0, 0, 0.3);
        this.chosenColor = new Color(0, 220, 0, 0.4);
        
        this.#pawns = Array.from({ length: this.X*this.Y*this.Z });
        this.layers = Array.from({ length: this.Z }, () => []);
        this.chosenLayer = -1;  // -1 oznacza brak wybranej warstwy
        this.chosenField = -1;  // -1 oznacza brak wybranego pola
        this.#playerIDs = ['O', 'X', 'P'];
        this.#pawnTypes = {'O': "Sphere", 'X': "Cross", 'P': "Cone"};

        this.#generateVertices();
        this.#generateFields();

        this.canvas.addEventListener("mousedown", this.#mouseClick.bind(this));
        this.canvas.addEventListener("contextmenu", (e) => { e.preventDefault(); });
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
        
        this.engine = new Engine(cnv, cnvWidth, cnvHeight, [...this.#fields], this.center, parseFloat(sens));
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
        let id = pos;

        if(pos instanceof Array){
            if(pos.length < 3) return false;
            id = this.arrToId(pos);
        }
        else if(!pos instanceof Number) return false;
        else pos = this.idToArr(pos);

        type = type.toLowerCase();
        let v1 = this.#fields[id].vertices[0];
        let v2 = this.#fields[id].vertices[6];
        let center = new Vertex((v1.x + v2.x)/2, (v1.y + v2.y)/2, (v1.z + v2.z)/2);  // Średnia dwóch wierzchołków leżacych na tej samej przekątnej

        switch(type){
            case "cube":
                this.#pawns[id] = new Cube(center, this.singleSize * 0.75, this.pawnColor);
                break;

            case "cuboid":
                this.#pawns[id] = new Cuboid(center, this.singleSize * 0.75, this.singleSize * 0.65, this.singleSize * 0.6, this.pawnColor);
                break;

            case "cross":
                this.#pawns[id] = new Cross(center, this.singleSize * 0.85, this.armFactor, this.pawnColor);
                break;

            case "sphere":
            case "pseudosphere":
                this.#pawns[id] = new PseudoSphere(center, this.singleSize * 0.45, this.precision, this.pawnColor);
                break;

            case "cone":
                this.#pawns[id] = new Cone(center, this.singleSize * 0.45, this.singleSize * 0.8, this.precision, this.pawnColor);
                break;
            default: 
                return false;    
        }
        this.engine.addObject(this.#pawns[id]);
        return true;
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
            this.draw();
            return;
        }

        let clientX = M.clientX - this.canvasRect.left;
        let clientY = M.clientY - this.canvasRect.top;
        let clicked = [];
        let maxZ = new Cube(new Vertex(0,0, Number.MIN_SAFE_INTEGER), 0);

        for(let i = 0; i < this.#fields.length; i++)
            if(this.#fields[i] instanceof Cube)
                if(this.#fields[i].checkClick( new Vertex2D(clientX - this.center.x, clientY - this.center.y))) 
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

    resize(){
        this.canvas.width = this.canvas.offsetWidth;
        this.canvas.height = this.canvas.offsetHeight;
        this.center = new Vertex(this.canvas.width / 2, this.canvas.height / 2, 0);
        this.canvasRect = this.canvas.getBoundingClientRect();
        this.engine.updateCenter();
    }

    setPrecision(prec){ 
        if(typeof prec !== number || prec < 4) return false;
        this.precision = parseInt(prec); 
        this.engine.setPrecision(this.precision);

        for(let i = 0; i < this.#pawns.length; i++)
            if(typeof(this.#pawns[i].precision) === "PseudoSphere")
                this.#pawns[i] = new PseudoSphere(this.#pawns[i].center, this.#pawns[i].radius, this.precision, 
                                                  this.#pawns[i].fillColor, this.#pawns[i].lineClr, this.#pawns[i].lineWidth);
            else if(typeof(this.#pawns[i].precision) === "Cone")
                this.#pawns[i] = new Cone(this.#pawns[i].center, this.#pawns[i].radius, this.#pawns[i].height, this.precision, 
                                                  this.#pawns[i].fillColor, this.#pawns[i].lineClr, this.#pawns[i].lineWidth);
        return true;
    }

    setFieldsStyle(fillClr, lineClr, width){
        width = parseFloat(width);
        if(!(fillClr instanceof Color) || !(lineClr instanceof Color) || typeof(width) !== "number") return false;

        for(let i = 0; i < this.#fields.length; i++){
            if(this.idToArr(i)[2] === this.chosenLayer){
                this.#fields[i].fillColor.setOpacity(fillClr.a);
                this.#fields[i].lineColor.setOpacity(lineClr.a);
            }
            else{
                this.#fields[i].changeFillColor(fillClr);
                this.#fields[i].changeLineColor(lineClr);
            }
            this.#fields[i].changeLineWidth(width);
        }
        this.engine.setStyle(fillClr, lineClr, width);
        this.draw();
        return true;
    }

    updatePawns(txt){
        let board = txt.split(this.#separator);
        if(board.length < this.#pawns.length) return false;

        for(let i = 0; i < board.length; i++){
            if(this.#playerIDs.includes(board[i])){
                if(this.#pawns[i] === null)  // TODO: nie tylko null, zły typ także - czyszczenie
                    this.addPawn(this.#pawnTypes[board[i]], i);
            }
            else
                if(this.#pawns[i] !== null)
                    this.#pawns[i] = null;
        }
        this.draw();
        return true;
    }

    draw(){ this.engine.draw(); }
    setSensitivity(sens){ this.engine.setSensitivity(sens); }
    getSizes(){ return [this.X, this.Y, this.Z]; }
};