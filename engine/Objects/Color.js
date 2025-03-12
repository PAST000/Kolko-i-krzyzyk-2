export default class Color{
    constructor(R,G,B,A){
        this.r = parseFloat(R);
        this.g = parseFloat(G);
        this.b = parseFloat(B);
        this.a = parseFloat(A);
    }

    toString(){
        return "rgba(" + parseInt(this.r) + ", " + parseInt(this.g) + ", " + parseInt(this.b) + ", " + this.a + ")";
    }
};

export function rgbToColor(txt){  // #RGBA lub #RGB
    if(txt.length < 7 || !(typeof(txt) === "string" || txt instanceof String)) return false;
    txt = txt.substring(1);
    let r = parseInt(txt.substring(0, 2), 16);
    let g = parseInt(txt.substring(2, 4), 16);
    let b = parseInt(txt.substring(4, 6), 16);
    let a = txt.length >= 8 ? parseInt(txt.substring(6,8), 16) : 1;
    return new Color(r, g, b, a);
}