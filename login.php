<?php
   include("connect.php");
   session_start();
   $error='';
   if($_SERVER["REQUEST_METHOD"] == "POST") {
   
      // username and password sent from form 
      $myusername = mysqli_real_escape_string($db,$_POST['user']);
      $mypassword = mysqli_real_escape_string($db,$_POST['pass']); 

      $sql = "SELECT * FROM user WHERE user = '$myusername' and pass = '$mypassword'";

      $result = mysqli_query($db,$sql);      
      $row = mysqli_num_rows($result);      
      $count = mysqli_num_rows($result);

      if($count == 1) {
	  
         // session_register("myusername");
         $_SESSION['login_user'] = $myusername;
         header("location: dashboard.php");
      } else {
         $error = "Your Login Name or Password is invalid";
      }
   }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link href="/dist/tailwind.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css"
    />
</head>
<style>
    .form1{
        background-color: #3498db;
        border: 2px;
    }
.font-semibold{
    font-family: Century Gothic,CenturyGothic,AppleGothic,sans-serif; 
}


</style>
<body>
<body class="flex flex-col items-center justify-center w-screen h-screen bg-gray-200 text-gray-700">

<!-- Component Start -->
<h1 class="font-bold text-2xl">Welcome Back :)</h1>
<form class="form1 flex flex-col bg-white rounded shadow-lg p-12 mt-12" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">

    <label class="font-semibold text-xs text-white" for="usernameField">Username or Email</label>
    <input class="flex items-center h-12 px-4 w-64 bg-gray-200 mt-2 rounded focus:outline-none focus:ring-2" type="text" id="user" name="user">
    <label class="font-semibold text-xs mt-3 text-white" for="passwordField">Password</label>
    <input class="flex items-center h-12 px-4 w-64 bg-gray-200 mt-2 rounded focus:outline-none focus:ring-2"type="password" id="pass" name="pass">
    <button class="b1 flex items-center justify-center h-12 px-6 w-64 bg-blue-600 mt-8 rounded font-semibold text-sm text-blue-100 hover:bg-blue-700" type="submit">Login</button>

</form>
<!-- Component End  -->

</body>
</body>
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
</script>
</html>