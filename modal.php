<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <style>
        body,html{
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            z-index: 10;
            overflow: hidden;
        }

        main{
            width: 500px;
            box-sizing: border-box;
            margin: 14% auto; 
        }

        form{
            box-sizing: border-box;
            display: inline-block;
            border: black solid 3px;
            border-radius: 8px;
            width: 100%;
            height: 100%;
            min-width: 300px;
            min-height: 210px;
            text-align: center;
            padding: 10px 14px;
            background-color: #f2f3f4;
            box-shadow: 2px 2px 10px;
        }

        input[type="text"], input[type="password"], input[type="submit"]{
            box-sizing: border-box;
            width: 100%;
            height: 42px;
            border-radius: 6px;
            padding-left: 10px;
            font-size: 18px;
            margin-top: 13px;
        }

        input[type="text"]:focus, input[type="password"]:focus{
            border: 2px solid black;
            outline: 0px;
            width: 100%;
        }

        input[type="checkbox"]{
            width: 18px;
            height: 18px;
            margin: 12px 2px 2px;
        }

        #close{
            float: right;
            color: red;
            background-color: transparent;
            border: 0;
            margin: 3px;
            margin-bottom: 0;
            font-weight: bold;
            font-size: 12px;
        }

        #close::before{
            content: url(resources/close.svg);
        }

        #error{
            box-sizing: border-box;
            width: 100%;
            height: 42px;
            border-radius: 6px;
            padding-left: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <main>
        <form method="post" action="register.php" id="form">
            <?php if(isset($_GET["registrationError"])){ ?>
                    <p id="error"> <?php echo($_GET["registrationError"]); ?> </p>
            <?php } ?>

            <button id="close" onclick="parent.document.getElementById('modalWrapper').style.display = 'none';"> </button>
            <input type="text" id="login" name="login" placeholder="Login">
            <input type="text" id="displayName" name="displayName" placeholder="Wyświetlana nazwa" onfocus="writeDisplayName = false;">
            <input type="text" name="email" placeholder="adres@poczta.com">
            <input type="password" name="password" placeholder="Hasło">
            <input type="password" name="passwordCheck" placeholder="Potwierdź hasło">

            <input type="checkbox" value="remember" id="remember">
            <label for="remember">Zapamiętaj mnie</label>
            <input type="submit" value="Zarejestruj">
        </form>
    </main>

    <script>
        var writeDisplayName = true;
        const loginInput = document.getElementById('login');
        const displayNameInput = document.getElementById('displayName');

        loginInput.addEventListener('input', () => {
            if (writeDisplayName) {
                displayNameInput.value = loginInput.value; 
            }
        });

        document.getElementById('form').addEventListener('submit', 
            function(e) {
                console.log("fetchTest");
                e.preventDefault(); // Zatrzymanie odświeżania strony
                const formData = new FormData(this);

                fetch('register.php', {
                    method: 'POST',
                    //body: formData,
                })
                .then(response => response.text())
                .then(data => console.log(data))
                .catch(error => console.error('Fetch error:', error));
        });
    </script>
</body>
</html>