<?php
include('login_session.php');
include 'connect.php'; // Ensure this file sets $db

$user_id = $username = $password = $name = '';

// Handle form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_POST['user_id'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $name = $_POST['name'];

    if (!empty($user_id)) {
        // Update user
        $stmt = $db->prepare("UPDATE user SET username=?, password=?, name=? WHERE user_id=?");
        $stmt->bind_param("sssi", $username, $password, $name, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new user
        $stmt = $db->prepare("INSERT INTO user (user, pass, name) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $username, $password, $name);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: user.php");
    exit();
}

// Load user data if editing
if (isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $result = $db->query("SELECT * FROM user WHERE user_id = $edit_id");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $user_id = $row['user_id'];
        $username = $row['user'];
        $password = $row['pass'];
        $name = $row['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>User Form</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    #myModal {
      display: flex;
      justify-content: center;
      align-items: center;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      z-index: 50;
    }
  </style>
</head>
<body class="bg-gray-100">

<?php include("sidebar.php"); ?>

<!-- Modal -->
<div id="myModal" class="fixed inset-0 ml-70 flex justify-center items-center z-50">
  <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">
    <button id="closeModal" onclick="window.location.href='user.php'"
      class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 class="text-xl font-semibold mb-6"><?php echo $user_id ? 'Update User' : 'Add User'; ?></h2>

    <form method="POST" action="">
      <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">

      <div class="mb-4">
        <input type="text" name="username" placeholder="Username" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($username); ?>" required>
      </div>

      <div class="mb-4">
        <input type="password" name="password" placeholder="Password" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($password); ?>" required>
      </div>

      <div class="mb-4">
        <input type="text" name="name" placeholder="Full Name" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($name); ?>" required>
      </div>

      <div class="flex justify-end space-x-3">
        <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-gray-700 transition">
          <?php echo $user_id ? 'Update' : 'Save'; ?>
        </button>
        <button type="button" onclick="window.location.href='user.php'"
          class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
