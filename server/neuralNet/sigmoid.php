<?php
function sigmoid($x){ return 1/(1 + pow(M_E, -$x)); }
function sigmoidDerivative($x){ return pow(M_E, $x)/pow(pow(M_E, $x) + 1,2); }
?>