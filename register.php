<?php
    header('Access-Control-Allow-Origin: *'); 
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS'); 
    header('Access-Control-Allow-Headers: Content-Type'); 

    echo "<script>console.log('register TEST')</script>";
    echo "register!!";

    if(empty($_POST["login"])){
        alert("Podaj login");
        exit();
    }
    if(empty($_POST["displayName"])){
        alert("Podaj wyświetlaną nazwę");
        exit();
    }
    if(empty($_POST["password"])){
        alert("Podaj hasło");
        exit();
    }
    if(empty($_POST["passwordCheck"])){
        alert("Podaj powtórzenie hasła");
        exit();
    }

    $login = $_POST["login"];
    $displayName = $_POST["displayName"];
    $email = "";
    $password = $_POST["password"];
    $passwordCheck = $_POST["passwordCheck"];

    if(!empty($_POST["email"])){
        $email = $_POST["email"];
    }
    
    if(strlen($login) > 20){
        alert("Zbyt długi login");
        exit();
    }
    if(strlen($displayName) > 50){
        alert("Zbyt długa wyświetlana nazwa");
        exit();
    }
    if(strlen($email) > 50){
        alert("Zbyt długi adres email");
        exit();
    }

    if (!preg_match("/^[a-zA-Z1-9-' ]*$/",$login)) {
        alert("Nie prawidłowy login");
        exit();
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        alert("Nie prawidłowy adres email");
        exit();
    }

    if($password !== $passwordCheck){
        alert("Hasło nie zgadza się z powtórzeniem");
        exit();
    }

    session_start();
    $conn = mysqli_connect("127.0.0.1", "root", "", "tictactoe2userdata");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    if(mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE login='$login'")) > 0){
        alert("Istnieje już użytkownik o podanym loginie");
        exit();
    }

    $hashedPass = hash('sha256',$password);
    $query = "INSERT INTO users (login, displayName, passwordHash, email) VALUES ('$login', '$displayName', '$hashedPass','$email')";
    mysqli_query($conn, $query);
    mysqli_close($conn);


    function alert($msg) {
        echo "<script type='text/javascript'>alert('$msg');</script>";
    }
?>