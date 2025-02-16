export default class Color{
    constructor(R,G,B,A){
        this.r = R;
        this.g = G
        this.b = B;
        this.a = A;
    }

    toString(){
        return "rgba(" + parseInt(this.r) + ", " + parseInt(this.g) + ", " + parseInt(this.b) + ", " + this.a + ")";
    }
};