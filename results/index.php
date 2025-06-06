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
            <section id="colsWrapper">
                <section id="leftCol">
                    <div class="dataWrapper">
                        <label for="idFrom">Początkowe id:</label>
                        <input type="number" id="idFrom" name="idFrom" min="1" step="1">
                    </div>
                    <div class="dataWrapper">
                        <label for="idTo">Końcowe id:</label>
                        <input type="number" id="idTo" name="idTo" min="1" step="1">
                    </div>
                    <div class="dataWrapper">
                        <label for="nick0">Pierwszy nick:</label>
                        <input type="text" id="nick0" name="nick0">
                    </div>
                    <div class="dataWrapper">
                        <label for="nick1">Drugi nick:</label>
                        <input type="text" id="nick1" name="nick1">
                    </div>
                    <div class="dataWrapper">
                        <label for="nick2">Trzeci nick:</label>
                        <input type="text" id="nick2" name="nick2">
                    </div>
                    <div class="dataWrapper">
                        <label for="winner">Zwycięzca:</label>
                        <input type="text" id="winner" name="winner" placeholder="Puste jeśli remis">
                    </div>
                    <div class="dataWrapper">
                        <label for="startDate">Po dacie:</label>
                        <input type="datetime" id="startDate" name="startDate">
                    </div>
                    <div class="dataWrapper">
                    <label for="endDate">Przed datą:</label>
                    <input type="datetime" id="endDate" name="endDate">
                </div>
                </section>
                <section id="rightCol">
                    <h4>Pokaż: </h4>
                    <section id="showContainer">
                        <section class="checkCol">
                            <div class="checkWrapper">
                                <input type="checkbox" id="idCheck" name="idCheck" checked>
                                <label for="idCheck">ID</label>
                            </div>
                            <div class="checkWrapper">
                                <input type="checkbox" id="nick0Check" name="nick0Check" checked>
                                <label for="nick0Check">1. nick</label>
                            </div>
                            <div class="checkWrapper">
                                <input type="checkbox" id="nick1Check" name="nick1Check" checked>
                                <label for="nick1Check">2. nick</label>
                            </div>
                            <div class="checkWrapper">
                                <input type="checkbox" id="nick2Check" name="nick2Check" checked>
                                <label for="nick2Check">3. nick</label>
                            </div>
                        </section>
                        <section class="checkCol">
                            <div class="checkWrapper">
                                <input type="checkbox" id="winnerCheck" name="winnerCheck" checked>
                                <label for="winnerCheck">Zwycięzca</label>
                            </div>
                            <div class="checkWrapper">
                                <input type="checkbox" id="startCheck" name="startCheck" checked>
                                <label for="startCheck">Data początkowa</label>
                            </div>
                            <div class="checkWrapper">
                                <input type="checkbox" id="endCheck" name="endCheck" checked>
                                <label for="endCheck">Data końcowa</label>
                            </div>
                        </section>
                    </section>
                    <section id="groupSortWrapper">
                        <section id="sortContainer">
                            <h4>Sortuj: </h4>
                            <div class="sortWrapper">ID</div>
                            <div class="sortWrapper">Pierwszy nick</div>
                            <div class="sortWrapper">Drugi nick</div>
                            <div class="sortWrapper">Trzeci nick</div>
                            <div class="sortWrapper">Zwycięzca</div>
                            <div class="sortWrapper">Data początkowa</div>
                            <div class="sortWrapper">Data końcowa</div>
                        </section>
                        <section id="groupContainer">
                            <h4>Grupuj: </h4>
                            <div class="groupWrapper">ID</div>
                            <div class="groupWrapper">Pierwszy nick</div>
                            <div class="groupWrapper">Drugi nick</div>
                            <div class="groupWrapper">Trzeci nick</div>
                            <div class="groupWrapper">Zwycięzca</div>
                            <div class="groupWrapper">Data początkowa</div>
                            <div class="groupWrapper">Data końcowa</div>
                        </section>
                    </section>
                </section>
            </section>
            <input type="submit" id="submit" value="Wyślij">
        </form>
        <table id="dataTable">
            <tr>
                <th>ID</th>
                <th>1. nick</th>
                <th>2. nick</th>
                <th>3. nick</th>
                <th>Zwycięzca</th>
                <th>Data początkowa</th>
                <th>Data końcowa</th>
            </tr>
        <?php
            if($_SERVER['REQUEST_METHOD'] === 'POST'){ // TODO PDO !!!!
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

                try{
                    $this->conn = new PDO("mysql:host=localhost;dbname=tictactoe2", "root", "");
                    $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                }
                catch (PDOException $e) {
                    echo "Błąd połączenia z bazą danych.";
                }
                $result = $conn->query($query);

                echo "<script>document.getElementById('dataTable').style.display = 'block';</script>";
                while($row = $result->fetch_assoc())
                    echo "<tr>
                            <td>" . $row["id"] . "</td>
                            <td>" . $row["nick0"] . "</td>
                            <td>" . $row["nick1"] . "</td>
                            <td>" . $row["nick2"] . "</td>
                            <td>" . $row["winner"] . "</td>
                            <td>" . $row["startDate"] . "</td>
                            <td>" . $row["endDate"] . "</td>
                        </tr>";
            }
        ?>
        </table>
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