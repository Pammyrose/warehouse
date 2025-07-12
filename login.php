<?php
session_start();
$error = '';
$register_success = false;
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'empty') $error = "Username and Password are required.";
    elseif ($_GET['error'] == 'invalidpassword') $error = "Incorrect password.";
    elseif ($_GET['error'] == 'usernotfound') $error = "Username not found.";
    else $error = "Login failed.";
}
if (isset($_GET['register']) && $_GET['register'] === 'success') {
    $register_success = true;
    $error = "Registration successful! You can now log in.";
}
if (isset($_GET['register_error'])) {
    if ($_GET['register_error'] === 'exists') $error = "Username already exists. Please try another.";
    elseif ($_GET['register_error'] === 'fail') $error = "Registration failed. Try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link href="/dist/tailwind.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Quicksand', sans-serif;
    }

    body {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        background: #f7fafc; /* Tailwind bg-gray-100 */
    }

    .card-wrapper {
        perspective: 1000px;
        width: 400px;
        height: 450px;
        position: relative;
    }

    .card {
        width: 100%;
        height: 100%;
        position: relative;
        transition: transform 0.8s;
        transform-style: preserve-3d;
    }

    .card.flipped {
        transform: rotateY(180deg);
    }

    .card .form-container {
        position: absolute;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        background: #ffffff; /* Tailwind bg-white */
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px;
        border-radius: 4px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
    }

    .card .form-container.back {
        transform: rotateY(180deg);
    }

    .form-container .content {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .form-container .content h2 {
        font-size: 2em;
        color: #3498db; /* Tailwind blue-600 */
        text-transform: uppercase;
        text-align: center;
    }

    .form-container .content .form {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .form-container .content .form .inputBox {
        position: relative;
        width: 100%;
    }

    .form-container .content .form .inputBox input {
        width: 100%;
        background: #edf2f7; /* Tailwind bg-gray-200 */
        border: none;
        outline: none;
        padding: 25px 10px 7.5px;
        border-radius: 4px;
        color: #2d3748; /* Tailwind text-gray-700 */
        font-weight: 500;
        font-size: 1em;
    }

    .form-container .content .form .inputBox i {
        position: absolute;
        left: 0;
        padding: 15px 10px;
        font-style: normal;
        color: #a0aec0; /* Tailwind gray-500 */
        transition: 0.5s;
        pointer-events: none;
    }

    .form-container .content .form .inputBox input:focus~i,
    .form-container .content .form .inputBox input:valid~i {
        transform: translateY(-7.5px);
        font-size: 0.8em;
        color: #2d3748; /* Tailwind text-gray-700 */
    }

    .form-container .content .form .inputBox input[type="submit"] {
        padding: 10px;
        background: #3498db; /* Tailwind bg-blue-600 */
        color: #ffffff; /* Tailwind text-white */
        font-weight: 600;
        font-size: 1.35em;
        letter-spacing: 0.05em;
        cursor: pointer;
    }

    .form-container .content .form .links {
        display: flex;
        justify-content: space-between;
        color: #2d3748; /* Tailwind text-gray-700 */
    }

    .form-container .content .form .links a {
        color: #3498db; /* Tailwind text-blue-600 */
        text-decoration: none;
        font-weight: 600;
    }

    .message-container {
        position: absolute;
        top: -60px;
        width: 100%;
        text-align: center;
    }

    .message {
        padding: 10px;
        border-radius: 4px;
        background: rgba(114, 114, 114, 0.7);
        color: #ffffff; /* Tailwind text-white */
    }
</style>
<body>
    <div class="card-wrapper">
        <div class="card<?php echo ($register_success || isset($_GET['register_error'])) ? ' flipped' : ''; ?>">
            <!-- Login Form (Front) -->
            <div class="message-container">
                            <?php if ($error && !$register_success && !isset($_GET['register_error'])): ?>
                                <p class="text-red-500 text-sm message"><?php echo htmlspecialchars($error); ?></p>
                            <?php elseif ($register_success): ?>
                                <p class="text-green-500 text-sm message"><?php echo htmlspecialchars($error); ?></p>
                            <?php endif; ?>
                        </div>
            <div class="form-container front">
                <div class="content">
                    <h2>Sign In</h2>
                    <form class="form" action="login_session.php" method="post" onsubmit="return validation()">

                        <div class="inputBox">
                            <input type="text" id="user" name="user" required> <i>Username</i>
                        </div>
                        <div class="inputBox">
                            <input type="password" id="pass" name="pass" required> <i>Password</i>
                        </div>
                        <div class="inputBox">
                            <input type="submit" value="Login">
                        </div>
                        <div class="links">
                            <span>Don't have an account?</span>
                            <a href="#" onclick="flipCard()">Register</a>
                        </div>
                    </form>
                </div>
            </div>
            <!-- Register Form (Back) -->
            <div class="message-container">
                            <?php if (isset($_GET['register_error'])): ?>
                                <p class="text-red-500 text-sm message"><?php echo htmlspecialchars($error); ?></p>
                            <?php endif; ?>
                        </div>
                        
            <div class="form-container back">
                <div class="content">
                    <h2>Register</h2>
                    <form class="form" action="register.php" method="post">

                        <div class="inputBox">
                            <input type="text" name="user" required> <i>Username</i>
                        </div>
                        <div class="inputBox">
                            <input type="text" name="name" required> <i>Full Name</i>
                        </div>
                        <div class="inputBox">
                            <input type="password" name="pass" required> <i>Password</i>
                        </div>
                        <div class="inputBox">
                            <input type="submit" value="Register">
                        </div>
                        <div class="links">
                            <span>Already have an account?</span>
                            <a href="#" onclick="flipCard()">Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function validation() {
            const id = document.getElementById('user').value;
            const ps = document.getElementById('pass').value;
            if (!id && !ps) {
                alert("Username and Password fields are empty");
                return false;
            }
            if (!id) {
                alert("Username field is empty");
                return false;
            }
            if (!ps) {
                alert("Password field is empty");
                return false;
            }
            return true;
        }

        function flipCard() {
            document.querySelector('.card').classList.toggle('flipped');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const messages = document.querySelectorAll('.message');
            messages.forEach(message => {
                setTimeout(() => {
                    message.classList.add('fade-out');
                    setTimeout(() => {
                        message.remove();
                    }, 500);
                }, 5000);
            });
        });

        <?php if ($register_success): ?>
        setTimeout(() => {
            document.querySelector('.card').classList.remove('flipped');
        }, 200);
        <?php endif; ?>
    </script>
</body>
</html>