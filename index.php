<?php
    require 'config.php'; 
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Kółko i krzyżyk 2">
    <title>Kółko i krzyżyk 2</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script> 
</head>
<body>
    <div id="headerWrapper">
        <iframe src="header.html" style="width: 100%" title="header"></iframe> 
    </div>
    <main>
        <nav>
            <button>Button1</button> 
            <div class="line"></div>
            <button>Button2</button> 
            <div class="line"></div>
            <button>Button3</button> 
        </nav>
        <article>
            <form method="post">
                <input type="text" placeholder="Login">
                <input type="password" placeholder="Hasło">
                <input type="checkbox" value="remember" id="remember">
                <label for="remember">Zapamiętaj mnie</label>
                <input type="submit" value="Zaloguj">
            </form>
            <section class="buttonsContainer">
                <div class="buttonWrapper">
                    <button id="guest">Dołącz jako gość</button>
                </div>
                <div class="buttonWrapper">
                    <button id="newUser" onclick="document.getElementById('modalWrapper').style.display = 'block';">Zarejestruj się</button>
                </div>
            </section> 
            <section id="modalWrapper">
                <iframe src="modal.html" style="width: 100%; height: 100%;" title="Rejestracja"></iframe>
            <section>
        </article>
    </main>
    <footer>
        Adam Stachowicz, Wiktor Nenko-Samborek 2024
    </footer>
</body>
</html>