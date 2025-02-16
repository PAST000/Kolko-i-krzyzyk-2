export default class Vertex{
    constructor(X, Y, Z){
        this.x = parseFloat(X);
        this.y = parseFloat(Y);
        this.z = parseFloat(Z);
    }

    cast2D(){ return new Vertex2D(this.x, this.y); }
    //project(fov = Number.MAX_SAFE_INTEGER){ return new Vertex2D((fov * this.x) / (fov + this.z), (fov * this.y) / (fov + this.z)); }
    project(){ return new Vertex2D(this.x, this.y); }
};

export class Vertex2D{
    constructor(X, Y){
        this.x = parseFloat(X);
        this.y = parseFloat(Y);
    }
}