import Vertex, {Vertex2D} from "./Objects/Vertex.js";
import Plane from "./Objects/Plane.js";

export function withinQuad(A, B, C, D, P){  // Sprawdzamy, czy punkt znajduje się w czworokącie. WAŻNE! A,B,C i D muszą być KOLEJNYMI wierzchołkami (A i C nie mogą leżeć na tym samym boku)
    if(!(A instanceof Vertex2D && B instanceof Vertex2D && C instanceof Vertex2D && D instanceof Vertex2D && P instanceof Vertex2D)) {
        console.log("errpr");
        return false;
    }

    var quadArea = det(A, B, C, true) + det(A, C, D, true);  // Pomijamy mnożenie przez 1/2
    var PArea = det(P, A, B, true) + det(P, B, C, true) + det(P, C, D, true) + det(P, D, A, true);
    //return( sameSide(A, B, P, D) && sameSide(B, C, P, A) && sameSide(C, D, P, B) && sameSide(D, A, P, C) );
    //console.log(quadArea, PArea, P);
    return quadArea >= PArea;
}

export function det(A, B, C, abs = false){  // Wyznacznik między wektorami AB i AC, abs - czy wartość bezwględna
    if(!(A instanceof Vertex2D && B instanceof Vertex2D && C instanceof Vertex2D)) return false;
    if(abs) return Math.abs((B.x - A.x)*(C.y - A.y) - (B.y - A.y)*(C.x - A.x));
    return (B.x - A.x)*(C.y - A.y) - (B.y - A.y)*(C.x - A.x);
}

export function Project(faces, fov = Number.MAX_SAFE_INTEGER){
    if(faces.length == 0) return;
    var arr = [];
    var projection = []; // Pojekcja pojedynczej ściany

    if(faces[0] instanceof Plane){
        for(var i = 0; i < faces.length; i++){
            for(var j = 0; j < faces[i].vertices.length; j++) projection.push(faces[i].vertices[j].project(fov));
            arr.push(projection);
            projection = [];
        }
        return arr;
    }

    for(var i = 0; i < faces.length; i++){
        for(var j = 0; j < faces[i].length; j++)
            projection.push(new Vertex2D((fov * faces[i][j].x) / (fov + faces[i][j].z), (fov * faces[i][j].y) / (fov + faces[i][j].z)));
        arr.push(projection);
        projection = [];
    }
    
    return arr;
}

function sameSide(A, B, P1, P2){  // Sprawdzenie czy punkty P1 i P2 leżą po tej samej stronie wektora AB
    const a = (B.x - A.x)*(P1.y - A.y) - (B.y - A.y)*(P1.x - A.x);
    const b = (B.x - A.x)*(P2.y - A.y) - (B.y - A.y)*(P2.x - A.x);
    return a*b >= 0;
}