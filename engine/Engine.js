import Vertex, {Vertex2D} from "./Objects/Vertex.js";
import Color from "./Objects/Color.js";
import { withinQuad, Project } from "./engineFunctions.js";

import Cube from "./Objects/Cube.js";
import Cuboid from "./Objects/Cuboid.js";
import Plane from "./Objects/Plane.js";
import Cross from "./Objects/Cross.js";
import PseudoSphere from "./Objects/PseudoSphere.js";
import Cone from "./Objects/Cone.js";

export default class Engine{
    #cnv; 
    #ctx;
    #keys = {};  // Wciśniete klawisze
    #objects;
    projections = [];
    vertices = [];    // Do obracania

    constructor(canvas, width, height, objs = [], cntr = null, sens = 4, prec = 7){
        this.#cnv = canvas;
        this.#cnv.width = this.#cnv.clientWidth;
        this.#cnv.height = this.#cnv.clientHeight;
        this.#ctx = this.#cnv.getContext("2d");

        this.#objects = [...objs];
        this.center = (cntr === null ? new Vertex(width/2, height/2, 0) : cntr);
        this.sensitivity = sens;
        this.sensitivityFactor = 1.1;  // Współczynnik czułości dla sztrałek
        this.precision = prec;
        this.defaultFillColor = new Color(0, 0, 90, 0.12);
        this.defaultLineColor = new Color(0, 0, 100, 0.12);
        this.defaultLineWidth = 1;

        this.rotationX = 0;  //
        this.rotationY = 0;  // Różnica obrótu od stanu początkowego
        this.rotationZ = 0;  //

        this.mouseDown = false;
        this.mouseX = 0;   // Koordynaty w których kliknięto mysz
        this.mouseY = 0;   //
        this.isRotating = false;
        this.rotationLoop = null;
        this.rotateLoop = () => {
            //if (!this.isRotating) return; 
            if (!this.isRotating) {
                this.isRotating = true;
                requestAnimationFrame(this.rotateLoop);
            }

            let rotX = 0, rotY = 0, rotZ = 0;
        
            if(!this.#keys["ShiftRight"] && !this.#keys["ShiftLeft"]){
                if (this.#keys["ArrowUp"]) rotY += this.sensitivityFactor * this.sensitivity * Math.PI / 180;
                if (this.#keys["ArrowDown"]) rotY -= this.sensitivityFactor * this.sensitivity * Math.PI / 180;
            }
            if (this.#keys["ArrowRight"]) rotX -= this.sensitivityFactor * this.sensitivity * Math.PI / 180;
            if (this.#keys["ArrowLeft"]) rotX += this.sensitivityFactor * this.sensitivity * Math.PI / 180;
            if (this.#keys["ShiftRight"] || this.#keys["ShiftLeft"]) {  
                if (this.#keys["ArrowRight"]) rotZ += this.sensitivityFactor * this.sensitivity * Math.PI / 180;
                if (this.#keys["ArrowLeft"]) rotZ -= this.sensitivityFactor * this.sensitivity * Math.PI / 180;
            }
            if (rotX !== 0 || rotY !== 0 || rotZ !== 0) {
                this.rotateAll(rotX, rotY, rotZ);
                this.draw();
            }
        
            requestAnimationFrame(this.rotateLoop); 
        };

        this.#cnv.addEventListener("mousedown", this.#mouseClick.bind(this));
        document.addEventListener("mousemove", this.#mouseMove.bind(this));
        document.addEventListener("mouseup", this.#mouseStop.bind(this));
        document.addEventListener("keydown", (e) => {
            if(e.ctrlKey) return;

            if (!this.#keys[e.code]) {
                this.#keys[e.code] = true;
                if (!this.isRotating) {
                    this.isRotating = true;
                    requestAnimationFrame(this.rotateLoop);
                }
            }
        });
        document.addEventListener("keyup", (e) => {
            this.#keys[e.code] = false;
            if (!Object.values(this.#keys).includes(true)) this.isRotating = false;
        });

        this.updateVertices();
        this.draw();
    }

    draw(){
        this.#ctx.clearRect(0, 0, this.#cnv.offsetWidth, this.#cnv.offsetHeight);

        for(let i = 0; i < this.#objects.length; i++){
            if(!(this.#objects[i] instanceof Array))
                if(this.#objects[i].faces.length == 0) continue;

            if(this.#objects[i] instanceof Array) this.projections = Project([this.#objects[i]]);
            else this.projections = Project(this.#objects[i].faces);
            if(this.projections.length == 0) continue;

            this.setStyle(this.#objects[i].fillColor === undefined ? this.defaultFillColor : this.#objects[i].fillColor, 
                          this.#objects[i].lineColor === undefined ? this.defaultLineColor : this.#objects[i].lineColor,
                          this.#objects[i].lineWidth === undefined ? this.defaultLineWidth : this.#objects[i].lineWidth);

            for(let j = 0; j < this.projections.length; j++){
                this.#ctx.beginPath();
                this.#ctx.moveTo(this.center.x + this.projections[j][0].x, this.center.y + this.projections[j][0].y);
                for(let k = 1; k < this.projections[j].length; k++)
                    this.#ctx.lineTo(this.center.x + this.projections[j][k].x, this.center.y + this.projections[j][k].y);
                this.#ctx.closePath();
                this.#ctx.stroke();
                this.#ctx.fill();
            }
        }
    }

    updateVertices(){
        this.vertices = [];
        for(let i = 0; i < this.#objects.length; i++){
            if(this.#objects[i] instanceof Array){
                for(let j = 0; j < this.#objects[i].length;j++)
                    if(!this.vertices.includes(this.#objects[i][j]))
                        this.vertices.push(this.#objects[i][j]);
            }
            else if(this.checkType(this.#objects[i])){
                for(let j = 0; j < this.#objects[i].vertices.length; j++)
                    if(!this.vertices.includes(this.#objects[i].vertices[j]))
                        this.vertices.push(this.#objects[i].vertices[j]);
            }
        }
    }

    addObject(obj){
        this.#objects.push(obj);
        this.updateVertices();
        return this.#objects.length - 1;
    }


    rotateAll(rotX, rotY, rotZ = 0){
        this.rotationX += rotX;
        this.rotationY += rotY;
        this.rotationZ += rotZ;

        for(let i = 0; i < this.vertices.length; i++)
            this.rotateVert(this.vertices[i], rotX, rotY, rotZ);
    }

    rotateObj(obj, rotX, rotY, rotZ = 0){
        console.log("rotObj1");
        if(typeof obj === "number"){
            if(obj < 0 || obj >= this.#objects.length) return false;
            obj = this.#objects[obj];
        }
        console.log("rotObj2");

        for(let i = 0; i < obj.vertices.length; i++)
            this.rotateVert(obj.vertices[i], rotX, rotY, rotZ);
    }

    rotateVert(vert, rotX, rotY, rotZ = 0){
        const vertex = vert;
        let originalX = vertex.x;
        let originalY = vertex.y;
        let originalZ = vertex.z;

        // Obrót wokół osi Y 
        vertex.x = originalX * Math.cos(rotX) - originalZ * Math.sin(rotX);
        vertex.z = originalX * Math.sin(rotX) + originalZ * Math.cos(rotX);
        originalY = vertex.y;
        originalZ = vertex.z;

        // Obrót wokół osi X 
        vertex.y = originalY * Math.cos(rotY) - originalZ * Math.sin(rotY);
        vertex.z = originalY * Math.sin(rotY) + originalZ * Math.cos(rotY);
        originalX = vertex.x;
        originalY = vertex.y;

        // Obrót wokół osi Z 
        vertex.x = originalX * Math.cos(rotZ) - originalY * Math.sin(rotZ);
        vertex.y = originalX * Math.sin(rotZ) + originalY * Math.cos(rotZ);
    }

    #mouseClick(M){
        this.mouseX = M.clientX;
        this.mouseY = M.clientY;
        this.mouseDown = true;
    }

    #mouseStop(){ this.mouseDown = false; }

    #mouseMove(M){
        if(!this.mouseDown) return;

        this.rotateAll((M.clientX - this.mouseX) * (0.3 + this.sensitivity/5) * Math.PI / 360, 
                       (this.mouseY - M.clientY) * (0.3 + this.sensitivity/5) * Math.PI / 360);
        this.mouseX = M.clientX;
        this.mouseY = M.clientY;
        this.draw();
    }


    setStyle(fillColor = this.defaultFillColor, lineColor = this.defaultLineColor, lineWidth = this.defaultLineWidth){
        if(fillColor instanceof Color) this.#ctx.fillStyle = fillColor.toString();
        if(lineColor instanceof Color) this.#ctx.strokeStyle = lineColor.toString();
        if(typeof lineWidth === "number" && !isNaN(lineWidth)) this.#ctx.lineWidth = lineWidth;
    }

    updateCenter(){
        this.center = new Vertex(this.#cnv.width/2, this.#cnv.height/2, 0);
        this.draw();
    }

    checkType(obj) {
        return [Cube, Cuboid, Plane, Cross, PseudoSphere, Cone].some(type => obj instanceof type);
    }

    setSensitivity(sens){ this.sensitivity = parseFloat(sens); }
    setSensitivityFactor(fac){ this.sensitivityFactor = fac; }
    setPrecision(prec) { this.precision = prec;}
};