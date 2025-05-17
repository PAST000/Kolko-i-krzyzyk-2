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

function showError(text){
    if(!text) text = "Błąd w pobieraniu danych.";
    sessionStorage.removeItem("errorData");
    sessionStorage.setItem("errorData", JSON.stringify({ txt : text }));
    document.getElementById("errorWrapper").style.display = "block";
    document.getElementById("errorIframe").contentWindow.postMessage("showError", "*");
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
        const txt = document.createTextNode("[" + players[keys[i]] + "] " + keys.find(key => players[key] === players[keys[i]]));

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
        document.getElementById("errorWrapper").style.display = "none";
        document.getElementById("adminWrapper").style.display = "none";     
    }
    else if(event.data === "closeError"){
        document.getElementById("errorWrapper").style.display = "none";  
        window.location.href = "../";
    }
});

window.onload = function() {
    changeFillColor();
    changeLineColor();
    
    document.getElementById("howToWrapper").style.display = "none"; 
    document.getElementById("controlWrapper").style.display = "none"; 
    document.getElementById("winWrapper").style.display = "none";   
    document.getElementById("errorWrapper").style.display = "none"; 
    document.getElementById("adminWrapper").style.display = "none"; 

    document.getElementById("gameTitle").onclick = function(){ document.location.href = "../"; };
    document.getElementById("howToButton").onclick = function(){ document.getElementById("howToWrapper").style.display = "block"; };
    document.getElementById("controlButton").onclick = function(){ document.getElementById("controlWrapper").style.display = "block"; };
    document.getElementById("resultsButton").onclick = function(){ document.location.href = "../results"; }
    document.getElementById("error").ondblclick = function(){ document.getElementById("error").innerHTML = ""; }

    document.getElementById("adminButton").onclick = function(){ 
        let nodeList = document.getElementById("playersContainer").childNodes; 
        let data = [];
        for(let i = 0; i < nodeList.length; i++){
            let arr = nodeList[i].childNodes[0].innerText.split(' ');
            data.push(new Array(arr[0].substr(1).substr(arr[0].length - 3, 1), arr[1]));
        }
        sessionStorage.removeItem("adminData");
        sessionStorage.setItem("adminData", JSON.stringify({ players: data }));
        document.getElementById("adminWrapper").style.display = "block";
        document.getElementById("adminIframe").contentWindow.postMessage("showAdmin", "*"); 
    };
}