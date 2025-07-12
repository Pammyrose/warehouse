<?php
// Password hashing script has been disabled to store passwords in plain text.
// include("connect.php");
// 
// $result = $db->query("SELECT login_id, pass FROM user");
// while ($row = $result->fetch_assoc()) {
//     $hashedPass = password_hash($row['pass'], PASSWORD_DEFAULT);
//     $stmt = $db->prepare("UPDATE user SET pass = ? WHERE login_id = ?");
//     $stmt->bind_param("si", $hashedPass, $row['login_id']);
//     if ($stmt->execute()) {
//         error_log("Hashed password for login_id {$row['login_id']}");
//     } else {
//         error_log("Failed to hash password for login_id {$row['login_id']}: " . $db->error);
//     }
// }
// echo "Passwords hashed successfully.";
?>