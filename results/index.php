<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Kółko i krzyżyk 2">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="Access-Control-Allow-Origin" content="modalLocal.html">

    </meat>
    <link rel="icon" type="image/x-icon" href="../resources/icon.ico">
    <title>Kółko i krzyżyk 2</title>
    <link rel="stylesheet" href="style.css">

</head>
<body>
    <header>
        <img src="../resources/icon.png" alt="Nie udało się załadować ikony">
        <h1 id="gameTitle">Kółko i krzyżyk 2</h1>
        <nav>
            <button id="howToButton">Jak grać</button> 
            <div class="line"></div>
            <button id="controlButton">Sterowanie</button> 
            <div class="line"></div>
            <a href="../communication">Komunikacja</a> 
        </nav>
    </header>
    <main>
        <form action="index.php" method="post">
            <div class="wrapper">
                <label for="idFrom">Początkowe id:</label>
                <input type="number" id="idFrom" name="idFrom" min="1" step="1">
            </div>
            <div class="wrapper">
                <label for="idTo">Końcowe id:</label>
                <input type="number" id="idTo" name="idTo" min="1" step="1">
            </div>

            <div class="wrapper">
                <label for="nick0">Pierwszy nick:</label>
                <input type="text" id="nick0" name="nick0">
            </div>
            <div class="wrapper">
                <label for="nick1">Drugi nick:</label>
                <input type="text" id="nick1" name="nick1">
            </div>
            <div class="wrapper">
                <label for="nick2">Trzeci nick:</label>
                <input type="text" id="nick2" name="nick2">
            </div>

            <div class="wrapper">
                <label for="winner">Zwycięzca:</label>
                <input type="text" id="winner" name="winner" placeholder="Puste jeśli remis">
            </div>

            <div class="wrapper">
                <label for="startDate">Po dacie:</label>
                <input type="datetime" id="startDate" name="startDate">
            </div>
            <div class="wrapper">
                <label for="endDate">Przed datą:</label>
                <input type="datetime" id="endDate" name="endDate">
            </div>

            <input type="submit" id="submit" value="Wyślij">
        </form>
        <?php
            $idFrom = $_POST["idFrom"] ?? null;
            $idTo = $_POST["idTo"] ?? null;
            $nick0 = $_POST["nick0"] ?? null;
            $nick1 = $_POST["nick1"] ?? null;
            $nick2 = $_POST["nick2"] ?? null;
            $winner = $_POST["winner"] ?? null;
            $startDate = $_POST["startDate"] ?? null;
            $endDate = $_POST["endDate"] ?? null;

            $query = "SELECT * FROM results WHERE 1";
            if(is_numeric($idFrom)) $query .= " AND id >= " . $idFrom;
            if(is_numeric($idTo)) $query .= " AND id <= " . $idTo;
            if(!empty($nick0)) $query .= " AND nick0 = '" . $nick0 . "'";
            if(!empty($nick1)) $query .= " AND nick1 = '" . $nick1 . "'";
            if(!empty($nick2)) $query .= " AND nick2 = '" . $nick2 . "'";
            if(!empty($winner)) $query .= " AND winner = '" . $winner . "'";
            if(!empty($startDate)) $query .= " AND startDate = '" . $startDate . "'";
            if(!empty($endDate)) $query .= " AND endDate = '" . $endDate . "'";
            $query .= ";";

            file_put_contents("query.txt", $query . PHP_EOL, FILE_APPEND);

            $conn = new mysqli("localhost", "root", "", "tictactoe2");
            if(!$conn->connect_error){
                $result = $conn->query($query);
            }
            $conn->close();
        ?>
    </main>
    <footer>
        Adam Stachowicz 2025
    </footer>

    <div id="howToWrapper" class="modalWrapper"> <iframe src="../modals/modalHowTo.html" title="howToPlay"></iframe> </div>
    <div id="controlWrapper" class="modalWrapper"> <iframe src="../modals/modalControl.html" title="control"></iframe> </div>
</body>
</html>

<script>
    document.getElementById("gameTitle").onclick = function(){ document.location.href = "../"; };
    document.getElementById("howToButton").onclick = function(){ document.getElementById("howToWrapper").style.display = "block"; };
    document.getElementById("controlButton").onclick = function(){ document.getElementById("controlWrapper").style.display = "block"; };

    window.addEventListener("message", function(event) {
        if(event.data === "closeModal"){
            document.getElementById("howToWrapper").style.display = "none"; 
            document.getElementById("controlWrapper").style.display = "none"; 
        }
    });
</script>