export default class Vertex{
    constructor(X, Y, Z){
        this.x = parseFloat(X);
        this.y = parseFloat(Y);
        this.z = parseFloat(Z);
    }

    length(){ return Math.sqrt(this.x*this.x + this.y*this.y + this.z*this.z); }
    project(){ return new Vertex2D(this.x, this.y); }

    dot(other){ 
        if(!other instanceof Vertex) return false;
        return this.x * other.x + this.y * other.y + this.z * other.z;
    }
    crossVec(other){
        if(!other instanceof Vertex) return false;
        return new Vertex(this.y*other.z - this.z*other.y, 
                          this.z*other.x - this.x*other.z,
                          this.x*other.y - this.y*other.x
                         );
    }
    crossLen(other){
        if(!other instanceof Vertex) return false;
        return this.crossVec(other).length();
    }
};

export class Vertex2D{
    constructor(X, Y){
        this.x = parseFloat(X);
        this.y = parseFloat(Y);
    }
}