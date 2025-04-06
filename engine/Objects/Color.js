export default class Color{
    constructor(R,G,B,A){
        this.r = parseFloat(R);
        this.g = parseFloat(G);
        this.b = parseFloat(B);
        this.a = parseFloat(A);
    }

    setOpacity(val){
        if(typeof val !== "number" || val < 0 || val > 1) return false;
        this.a = parseFloat(val);
    }

    toString(){
        return "rgba(" + parseInt(this.r) + ", " + parseInt(this.g) + ", " + parseInt(this.b) + ", " + this.a + ")";
    }
};

export function rgbToColor(txt, alpha = 1){  // #RGBA lub #RGB
    if(txt.length < 7 || !(typeof(txt) === "string" || txt instanceof String)) return false;
    if(txt[0] === '#') txt = txt.substring(1);
    let r = parseInt(txt.substring(0, 2), 16);
    let g = parseInt(txt.substring(2, 4), 16);
    let b = parseInt(txt.substring(4, 6), 16);
    return new Color(r, g, b, parseFloat(alpha));
}