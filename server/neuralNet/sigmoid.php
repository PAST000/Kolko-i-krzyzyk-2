<?php
function sigmoid($x){ 
    return 1/(1 + pow(M_E, -$x)); 
}
function sigmoidDerivative($x){
    $s = sigmoid($x);
    return $s * (1 - $s);
}
?>