<?php
    $servername = "localhost";
    $username = "admin";
    $password = "ZAQ!2wsxCDE#";

    $login = $_POST["post"];
    $displayName = $_POST["displayName"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $passwordCheck = $_POST["passwordCheck"];

    if(empty($login)){
        header("Location: index.php?registrationError = Login jest wymagany.");
        exit();
    }
    if(empty($displayName)){
        header("Location: index.php?registrationError = Wyświetlana nazwa jest wymagana.");
        exit();
    }
    if(empty($password)){
        header("Location: index.php?registrationError = Hasło jest wymagane.");
        echo("test");
        exit();
    }
    if(empty($passwordCheck)){
        header("Location: index.php?registrationError = Powtórzenie hasła jest wymagane.");
        exit();
    }

    if(strlen($login) > 20){
        header("Location: index.php?registrationError = Zbyt długi login. Maksymalna długość to 20 znaków.");
        exit();
    }
    if(strlen($displayName) > 50){
        header("Location: index.php?registrationError = Zbyt długa wyświetlana nazwa. Maksymalna długość to 50 znaków.");
        exit();
    }
    if(strlen($email) > 50){
        header("Location: index.php?registrationError = Zbyt długi adres email. Maksymalna długość to 50 znaków.");
        exit();
    }

    if (!preg_match("/^[a-zA-Z1-9-' ]*$/",$login)) {
        header("Location: index.php?registrationError = Login może zawierać tylko duże i małe litery, cyfry i spacje.");
        exit();
    }
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
        header("Location: index.php?registrationError = Nie poprawny format adresu email.");
        exit();
    }

    if($password !== $passwordCheck){
        header("Location: index.php?registrationError = Hasło nie zgadza się ze sprawdzeniem hasła.");
        exit();
    }

    session_start();
    $conn = new mysqli($servername, $username, $password);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    } 

    if(mysqli_num_rows(mysqli_query($conn, "SELECT * FROM users WHERE login='$login'")) > 0){
        header("Location: modal.php?registrationError = Istnieje już użytkownik o tym loginie.");
        exit();
    }

    $insertQuery = "INSERT INTO users (login, displayName, passwordHash, email) VALUES ('$login', '$displayName', 'hash('sha256','$password')','$email')";
    mysqli_query($conn, $insertQuery);
?>