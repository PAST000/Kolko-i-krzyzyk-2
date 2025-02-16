import Vertex from "./Vertex.js";
import Color from "./Color.js";

export default class Cross{
    constructor(cntr, len, arm = 0.11, fillColor = new Color(0, 0, 50, 0.5), lineClr = new Color(0, 0, 80, 0.7), lineWdt = 0.4){ 
        this.center = cntr;
        this.length = parseFloat(len);
        this.armFactor = parseFloat(arm);   // (0;1), współczynnik szerokości ramienia do ogólnej szerokości
        this.armSize = parseFloat(len*arm);
        this.fill = fillColor;
        this.lineColor = lineClr;
        this.lineWidth = lineWdt;

        this.vertices = [  // Kolejność wierzchołków: góra, prawo, dół, lewo, tył, przód, środek
            new Vertex(cntr.x + this.armSize/2, cntr.y + len/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x + this.armSize/2, cntr.y + len/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y + len/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y + len/2, cntr.z + this.armSize/2),

            new Vertex(cntr.x + len/2, cntr.y + this.armSize/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x + len/2, cntr.y - this.armSize/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x + len/2, cntr.y - this.armSize/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x + len/2, cntr.y + this.armSize/2, cntr.z - this.armSize/2),

            new Vertex(cntr.x + this.armSize/2, cntr.y - len/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x + this.armSize/2, cntr.y - len/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y - len/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y - len/2, cntr.z + this.armSize/2),

            new Vertex(cntr.x - len/2, cntr.y + this.armSize/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x - len/2, cntr.y - this.armSize/2, cntr.z + this.armSize/2),
            new Vertex(cntr.x - len/2, cntr.y - this.armSize/2, cntr.z - this.armSize/2),
            new Vertex(cntr.x - len/2, cntr.y + this.armSize/2, cntr.z - this.armSize/2),

            new Vertex(cntr.x + this.armSize/2, cntr.y + this.armSize/2, cntr.z + len/2),
            new Vertex(cntr.x + this.armSize/2, cntr.y - this.armSize/2, cntr.z + len/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y - this.armSize/2, cntr.z + len/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y + this.armSize/2, cntr.z + len/2),

            new Vertex(cntr.x + this.armSize/2, cntr.y + this.armSize/2, cntr.z - len/2),
            new Vertex(cntr.x + this.armSize/2, cntr.y - this.armSize/2, cntr.z - len/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y - this.armSize/2, cntr.z - len/2),
            new Vertex(cntr.x - this.armSize/2, cntr.y + this.armSize/2, cntr.z - len/2),

            new Vertex(cntr.x + this.armSize/2, cntr.y + this.armSize/2, cntr.z + this.armSize/2),  
            new Vertex(cntr.x + this.armSize/2, cntr.y - this.armSize/2, cntr.z + this.armSize/2),  
            new Vertex(cntr.x - this.armSize/2, cntr.y - this.armSize/2, cntr.z + this.armSize/2),  
            new Vertex(cntr.x - this.armSize/2, cntr.y + this.armSize/2, cntr.z + this.armSize/2),  
            new Vertex(cntr.x + this.armSize/2, cntr.y + this.armSize/2, cntr.z - this.armSize/2),  
            new Vertex(cntr.x + this.armSize/2, cntr.y - this.armSize/2, cntr.z - this.armSize/2),  
            new Vertex(cntr.x - this.armSize/2, cntr.y - this.armSize/2, cntr.z - this.armSize/2),  
            new Vertex(cntr.x - this.armSize/2, cntr.y + this.armSize/2, cntr.z - this.armSize/2)   
        ];

        this.faces = [ 
            [this.vertices[0], this.vertices[1], this.vertices[2], this.vertices[3]],
            [this.vertices[0], this.vertices[24], this.vertices[28], this.vertices[1]],
            [this.vertices[1], this.vertices[28], this.vertices[31], this.vertices[2]],
            [this.vertices[2], this.vertices[31], this.vertices[27], this.vertices[3]],
            [this.vertices[3], this.vertices[27], this.vertices[24], this.vertices[0]],

            [this.vertices[4], this.vertices[5], this.vertices[6], this.vertices[7]],
            [this.vertices[4], this.vertices[24], this.vertices[25], this.vertices[5]],
            [this.vertices[5], this.vertices[25], this.vertices[29], this.vertices[6]],
            [this.vertices[6], this.vertices[29], this.vertices[28], this.vertices[7]],
            [this.vertices[7], this.vertices[28], this.vertices[24], this.vertices[4]],

            [this.vertices[9], this.vertices[8], this.vertices[11], this.vertices[10]],
            [this.vertices[9], this.vertices[29], this.vertices[25], this.vertices[8]],
            [this.vertices[8], this.vertices[25], this.vertices[26], this.vertices[11]],
            [this.vertices[11], this.vertices[26], this.vertices[30], this.vertices[10]],
            [this.vertices[10], this.vertices[30], this.vertices[29], this.vertices[9]],

            [this.vertices[15], this.vertices[14], this.vertices[13], this.vertices[12]],
            [this.vertices[15], this.vertices[31], this.vertices[30], this.vertices[14]],
            [this.vertices[14], this.vertices[30], this.vertices[26], this.vertices[13]],
            [this.vertices[13], this.vertices[26], this.vertices[27], this.vertices[12]],
            [this.vertices[12], this.vertices[27], this.vertices[31], this.vertices[15]],

            [this.vertices[19], this.vertices[18], this.vertices[17], this.vertices[16]],
            [this.vertices[19], this.vertices[27], this.vertices[26], this.vertices[18]],
            [this.vertices[18], this.vertices[26], this.vertices[25], this.vertices[17]],
            [this.vertices[17], this.vertices[25], this.vertices[24], this.vertices[16]],
            [this.vertices[16], this.vertices[24], this.vertices[27], this.vertices[19]],

            [this.vertices[20], this.vertices[21], this.vertices[22], this.vertices[23]],
            [this.vertices[20], this.vertices[28], this.vertices[29], this.vertices[21]],
            [this.vertices[21], this.vertices[29], this.vertices[30], this.vertices[22]],
            [this.vertices[22], this.vertices[30], this.vertices[31], this.vertices[23]],
            [this.vertices[23], this.vertices[31], this.vertices[28], this.vertices[20]]
        ];
    }

    changeFillColor(fillColor){ this.fill = fillColor; }
    changeLineColor(lineClr){ this.lineColor = lineClr; }
    changeLineWidth(width){ this.lineWidth = width; }
};