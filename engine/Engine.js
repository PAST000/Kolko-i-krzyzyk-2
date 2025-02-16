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
    constructor(canvas, width, height, objs = [], cntr = null, sensitivity = 5, f = Number.MAX_SAFE_INTEGER){
        this.cnv = canvas;
        this.cnv.width = width;
        this.cnv.height = height;
        this.ctx = this.cnv.getContext("2d");

        this.objects = objs;
        this.projections = [];
        this.clickables = [];
        this.vertices = [];   // Do obracania

        this.center = (cntr === null ? new Vertex(width/2, height/2, 0) : cntr);
        this.fov = f;
        this.sensitivity = sensitivity;
        this.defaultFillColor = new Color(0, 0, 100, 0.12);
        this.defaultLineColor = new Color(0, 0, 100, 0.1);
        this.defaultLineWidth = 0.3;

        this.mouseDown = false;
        this.mouseX = 0;   // Koordynaty w których kliknięto mysz
        this.mouseY = 0;   //
        this.cnv.addEventListener("mousedown", this.#mouseClick.bind(this));
        document.addEventListener("mousemove", this.#mouseMove.bind(this));
        document.addEventListener("mouseup", this.#mouseStop.bind(this));
        document.addEventListener("keydown", (e) => {
            if(e.shiftKey)
                switch(e.code){
                    case "ArrowRight":
                        this.rotateAll(0, 0, this.sensitivity * Math.PI / 180);
                        break;
                    case "ArrowLeft":
                        this.rotateAll(0, 0, -this.sensitivity * Math.PI / 180);
                        break;
                    default: break;
                }
            else
                switch(e.code){
                    case "ArrowUp":
                        this.rotateAll(0, this.sensitivity * Math.PI / 180);
                        break;
                    case "ArrowDown":
                        this.rotateAll(0, -this.sensitivity * Math.PI / 180);
                        break;
                    case "ArrowRight":
                        this.rotateAll(-this.sensitivity * Math.PI / 180, 0);
                        break;
                    case "ArrowLeft":
                        this.rotateAll(this.sensitivity * Math.PI / 180, 0);
                        break;
                    default: break;
                }
            this.draw();
        });

        this.updateVertices();
        this.draw();
    }

    draw(){
        this.ctx.clearRect(0, 0, this.cnv.width, this.cnv.height);

        for(let i = 0; i < this.objects.length; i++){
            if(!this.objects[i] instanceof Array) 
                if(this.objects[i].faces.length == 0) continue;

            if(this.objects[i] instanceof Array) this.projections = Project([this.objects[i]], this.fov);
            else this.projections = Project(this.objects[i].faces, this.fov);
            if(this.projections.length == 0) continue;
            //this.setStyle(this.defaultFillColor, this.defaultLineColor, this.defaultLineWidth);
            this.setStyle(this.objects[i].fillColor, this.objects[i].lineColor, this.objects[i].lineWidth);

            for(let j = 0; j < this.projections.length; j++){
                this.ctx.beginPath();
                this.ctx.moveTo(this.center.x + this.projections[j][0].x, this.center.y + this.projections[j][0].y);
                for(let k = 1; k < this.projections[j].length; k++)
                    this.ctx.lineTo(this.center.x + this.projections[j][k].x, this.center.y + this.projections[j][k].y);
                this.ctx.closePath();
                this.ctx.stroke();
                this.ctx.fill();
            }
        }
    }

    updateVertices(){
        this.vertices = [];
        for(let i = 0; i < this.objects.length; i++){
            if(this.objects[i] instanceof Array){
                for(let j = 0; j < this.objects[i].length;j++)
                    if(!this.vertices.includes(this.objects[i][j]))
                        this.vertices.push(this.objects[i][j]);
            }
            else if(this.checkType(this.objects[i])){
                for(let j = 0; j < this.objects[i].vertices.length; j++)
                    if(!this.vertices.includes(this.objects[i].vertices[j]))
                        this.vertices.push(this.objects[i].vertices[j]);
            }
        }
    }

    addObject(obj){
        this.objects.push(obj);
        this.updateVertices();
        return this.objects.length - 1;
    }


    rotateAll(rotX, rotY, rotZ = 0){
        for(let i = 0; i < this.vertices.length; i++)
            this.rotateVert(this.vertices[i], rotX, rotY, rotZ);
    }

    rotate(obj, vert, rotX, rotY){
        const vertex = this.objects[obj].vertices[vert]; 
        const originalX = vertex.x;
        const originalY = vertex.y;
        const originalZ = vertex.z;

        // Obrót wokół osi Y 
        vertex.x = originalX * Math.cos(rotX) - originalZ * Math.sin(rotX);
        vertex.z = originalX * Math.sin(rotX) + originalZ * Math.cos(rotX);

        // Obrót wokół osi X 
        vertex.y = originalY * Math.cos(rotY) - vertex.z * Math.sin(rotY);
        vertex.z = originalY * Math.sin(rotY) + vertex.z * Math.cos(rotY);
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

        /*for(let i = 0; i < this.clickables.length; i++)
            for(let j = 0; j < this.clickables[i].faces.length; j++){
                if(this.clickables[i].faces.length < 4) continue;
                if(withinQuad(this.clickables[i].faces[j][0].cast2D(), 
                            this.clickables[i].faces[j][1].cast2D(), 
                            this.clickables[i].faces[j][2].cast2D(), 
                            this.clickables[i].faces[j][3].cast2D(), 
                            new Vertex2D(this.mouseX - this.center.x, this.mouseY - this.center.y))) 
                    console.log("click");
            }*/
    }

    #mouseStop(){ this.mouseDown = false; }

    #mouseMove(M){
        if(!this.mouseDown) return;

        this.rotateAll((M.clientX - this.mouseX) * Math.PI / 360, (this.mouseY - M.clientY) * Math.PI / 360);
        this.mouseX = M.clientX;
        this.mouseY = M.clientY;
        this.draw();
    }


    setStyle(fillColor = new Color(0, 0, 20, 0.5), lineColor = new Color(0, 0, 30, 0.7), width = this.defaultLineWidth){
        this.ctx.fillStyle = fillColor.toString();
        this.ctx.strokeStyle = lineColor.toString();
        this.ctx.lineWidth = width.toString();
    }

    addClickable(obj){
        if(obj instanceof Number){
            this.clickables.push(this.objects[obj]);
            return true;
        }
        else if(this.checkType(obj)){
            this.clickables.push(obj);
            return true;
        }
        return false;
    }

    checkType(obj){
        if(obj instanceof Cube) return true;
        if(obj instanceof Cuboid) return true;
        if(obj instanceof Plane) return true;
        if(obj instanceof Cross) return true;
        if(obj instanceof PseudoSphere) return true;
        if(obj instanceof Cone) return true;
        return false;
    }
};