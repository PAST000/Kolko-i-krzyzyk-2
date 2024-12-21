<?php
    function setError($error){
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error]);
        file_put_contents('errors.txt', "json_encode(['success' => false, 'error' => $error])", FILE_APPEND);
        exit();
    }

    header('Content-Type: text/html; charset=UTF-8');
    unset($_GET['registrationError']);

    if(empty($_POST["login"])){
        setError("Podaj login");
    }
    if(empty($_POST["displayName"])){
        setError("Podaj wyswietlana nazwe");
    }
    if(empty($_POST["password"])){
        setError("Podaj haslo");
    }
    if(empty($_POST["passwordCheck"])){
        setError("Podaj powtorzenie hasla");
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
        setError("Zbyt długi login");
    }
    if(strlen($displayName) > 50){
        setError("Zbyt długa wyswietlana nazwa");
    }
    if(strlen($email) > 50){
        setError("Zbyt długi adres email");
    }

    if (!preg_match("/^[a-zA-Z1-9-' ]*$/",$login)) {
        setError("Nie prawidłowy login");
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        setError("Nie prawidłowy adres email");
    }

    if($password !== $passwordCheck){
        setError("Hasło nie zgadza się z powtórzeniem");
    }
    
    session_start();
    $conn = mysqli_connect("127.0.0.1", "root", "", "tictactoe2userdata");
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    if(mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE login='$login'")) > 0){
        setError("Istnieje już użytkownik o podanym loginie");
    }

    $hashedPass = hash('sha256',$password);
    $query = "INSERT INTO users (login, displayName, passwordHash, email) VALUES ('$login', '$displayName', '$hashedPass','$email')";
    mysqli_query($conn, $query);
    mysqli_close($conn);
?>