<?php    
    if(empty($_POST["login"])){
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=Brak+danych+wejsciowych');
        exit();
    }
    if(empty($_POST["password"])){
        header("Location: index.php?registrationError = Hasło jest wymagane.");
        exit();
    }

    $login = $_POST["login"];
    $password = $_POST["password"];

    if(strlen($login) > 20){
        header("Location: index.php?registrationError = Zbyt długi login. Maksymalna długość to 20 znaków.");
        exit();
    }
    if (!preg_match("/^[a-zA-Z1-9-' ]*$/",$login)) {
        header("Location: index.php?registrationError = Błędny login.");
        exit();
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
        echo 'Błędny login lub hasło';
    }

    mysqli_close($conn);
?>