<?php   
    function setError($txt){
        header('Location: ' . dirname($_SERVER['REQUEST_URI']) . "?loginError=" . urlencode(trim(json_encode($txt), '"')));
        exit();
    }

    header('Content-Type: text/html; charset=UTF-8');
    unset($_GET['loginError']);

    if(empty($_POST["login"])){
        setError("Podaj login");
    }
    if(empty($_POST["password"])){
        setError("Podaj haslo");
    }

    $login = $_POST["login"];
    $password = $_POST["password"];

    if(strlen($login) > 20){
        setError("Zbyt dlugi login");
    }
    if (!preg_match("/^[a-zA-Z1-9-' ]*$/",$login)) {
        setError("Nieprawidlowy login");
    }

    session_start();
    $conn = mysqli_connect("127.0.0.1", "root", "", "tictactoe2userdata");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    $hashedPass = hash('sha256', $password);
    if(mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE login = '$login' AND passwordHash = '$hashedPass'")) === 1){
        echo 'Zalogowano';
    }
    else{
        $_GET["loginError"] = "Nieprawidlowy login.";
    }

    mysqli_close($conn);
    header('Location: ' . dirname($_SERVER['REQUEST_URI']));
?>