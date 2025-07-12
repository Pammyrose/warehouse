<?php
include('login_session.php');
include 'connect.php';

$user_id = $username = $password = $name = '';
?>

<div >
    <button id="closeModal" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 class="text-xl font-semibold mb-6">Add User</h2>

    <form method="POST" action="user.php">
        <input type="hidden" name="login_id" value="">
        <div class="mb-4">
            <input type="text" name="username" placeholder="Username" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($username); ?>" required>
        </div>
        <div class="mb-4">
            <input type="password" name="password" placeholder="Password" class="w-full border p-2 rounded" required>
        </div>
        <div class="mb-4">
            <input type="text" name="name" placeholder="Full Name" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>
        <div class="flex justify-end space-x-3">
            <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-gray-700 transition">Save</button>
            <button type="button" class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
        </div>
    </form>
</div>