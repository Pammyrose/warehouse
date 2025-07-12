<?php
include('login_session.php');
include 'connect.php';

$user_id = $username = $name = '';

// Load user data if editing
if (isset($_GET['id'])) {
    $edit_id = intval($_GET['id']);
    $stmt = $db->prepare("SELECT login_id, user, name FROM user WHERE login_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $user_id = $row['login_id'];
        $username = $row['user'];
        $name = $row['name'];
    } else {
        echo "User not found.";
        exit;
    }
    $stmt->close();
} else {
    echo "Invalid user ID.";
    exit;
}
?>

<div>
    <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 class="text-xl font-semibold mb-6">Update User</h2>

    <form method="POST" action="user.php">
        <input type="hidden" name="login_id" value="<?php echo htmlspecialchars($user_id); ?>">
        <input type="hidden" name="original_username" value="<?php echo htmlspecialchars($username); ?>">
        <div class="mb-4">
            <input type="text" name="username" placeholder="Username" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        
        <div class="mb-4">
            <input type="password" name="password" placeholder="New Password (optional)" class="w-full border p-2 rounded">
        </div>
        <div class="mb-4">
            <input type="text" name="name" placeholder="Full Name" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="flex justify-end space-x-3">
            <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-gray-700 transition">Update</button>
            <button type="button" class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
        </div>
    </form>
</div>