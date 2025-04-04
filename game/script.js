function changeFillColor(){
    const preview = document.getElementById("previewFill");
    preview.style.backgroundColor = document.getElementById("fillColor").value;
    preview.style.opacity = (document.getElementById("fillAlpha").value/100);
}

function changeLineColor(){
    const preview = document.getElementById("previewLine");
    preview.style.backgroundColor = document.getElementById("lineColor").value;
    preview.style.opacity = (document.getElementById("lineAlpha").value/100);
}

function refreshInfo(players, target, turn, yourID, admin){
    document.getElementById("turn").innerHTML = "Tura gracza: " + turn;
    document.getElementById("yourID").innerHTML = "Twoje ID: " + yourID;
    document.getElementById("target").innerHTML = "Cel: " + target;
    let childrenArray = [];
    let keys = Object.keys(players);

    for(let i = 0; i < keys.length; i++){
        const wrapper = document.createElement("div");
        const par = document.createElement("p");
        const txt = document.createTextNode("[" + i + "] " + keys.find(key => players[key] === i));

        wrapper.classList.add("playerWrapper");
        par.appendChild(txt);
        wrapper.appendChild(par);
        childrenArray.push(wrapper);
    }
    document.getElementById("playersContainer").replaceChildren(...childrenArray);
}

window.addEventListener("message", function(event) {
    if(event.data === "closeModal"){
        document.getElementById("howToWrapper").style.display = "none"; 
        document.getElementById("controlWrapper").style.display = "none"; 
        document.getElementById("winWrapper").style.display = "none";   
    }
});

window.onload = function() {
    changeFillColor();
    changeLineColor();
}