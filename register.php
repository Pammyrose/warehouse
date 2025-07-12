<?php
session_start();
include("connect.php");

ob_start();

function validate($data, $isPassword = false) {
    $data = trim($data);
    if (!$isPassword) {
        $data = htmlspecialchars(stripslashes($data));
    }
    return $data;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = validate($_POST['user']);
    $name = validate($_POST['name']);
    $pass = validate($_POST['pass'], true);

    // Check if username exists
    $stmt = $db->prepare("SELECT user FROM user WHERE user = ?");
    $stmt->bind_param("s", $uname);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        header("Location: login.php?register_error=exists");
        ob_end_flush();
        exit();
    }

    // Insert new user with plain text password
    $stmt = $db->prepare("INSERT INTO user (user, name, pass) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $uname, $name, $pass);
    if ($stmt->execute()) {
        header("Location: login.php?register=success");
        ob_end_flush();
        exit();
    } else {
        error_log("Register: Insert failed: " . $db->error);
        header("Location: login.php?register_error=fail");
        ob_end_flush();
        exit();
    }
}

ob_end_flush();
?>