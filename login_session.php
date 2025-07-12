<?php
// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("connect.php"); // Ensure connect.php defines $db

// Start output buffering to prevent stray output
ob_start();

// Validate input
function validate($data, $isPassword = false) {
    $data = trim($data);
    if (!$isPassword) {
        $data = htmlspecialchars(stripslashes($data));
    }
    return $data;
}

// Log session details for debugging
error_log("Login_session: Session check, user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", user=" . ($_SESSION['user'] ?? 'unset') . ", request_method={$_SERVER['REQUEST_METHOD']}");

// Handle POST requests for login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user']) && isset($_POST['pass'])) {
    $uname = validate($_POST['user']);
    $pass = validate($_POST['pass'], true);

    // Log input for debugging
    error_log("Login: Username=$uname");

    // Prepare and execute query
    $stmt = $db->prepare("SELECT login_id, user, pass FROM user WHERE user = ?");
    if (!$stmt) {
        error_log("Login: Prepare failed: " . $db->error);
        header("Location: login.php?error=invalidrequest");
        ob_end_flush();
        exit();
    }
    $stmt->bind_param("s", $uname);
    if (!$stmt->execute()) {
        error_log("Login: Execute failed: " . $stmt->error);
        header("Location: login.php?error=invalidrequest");
        ob_end_flush();
        exit();
    }
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $storedPass = $row['pass'];
        error_log("Login: Stored Password=$storedPass");

        // Compare plain text password
        if ($pass === $storedPass) {
            // Set session variables
            if (!isset($row['login_id'])) {
                error_log("Login: login_id column missing for $uname");
                header("Location: login.php?error=invalidrequest");
                ob_end_flush();
                exit();
            }
            $_SESSION['user_id'] = $row['login_id'];
            $_SESSION['user'] = $row['user'];
            $_SESSION['last_activity'] = time(); // Track last activity for timeout
            error_log("Login: Successful for $uname, setting session");
            header("Location: dashboard.php");
            ob_end_flush();
            exit();
        } else {
            error_log("Login: Password verification failed for $uname");
            header("Location: login.php?error=invalidpassword");
            ob_end_flush();
            exit();
        }
    } else {
        error_log("Login: Username $uname not found");
        header("Location: login.php?error=usernotfound");
        ob_end_flush();
        exit();
    }
}

// Check session for non-login POST requests and non-POST requests
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    error_log("Login_session: Session invalid, user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", user=" . ($_SESSION['user'] ?? 'unset'));
    if ($isAjax) {
        // For AJAX requests, return JSON response
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please log in.', 'redirect' => 'login.php']);
        exit;
    } else {
        header("Location: login.php?error=notloggedin");
        ob_end_flush();
        exit;
    }
}

// Check for session timeout (30 minutes of inactivity)
$timeout_duration = 1800; // 30 minutes
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout_duration)) {
    error_log("Login_session: Session timed out for user_id={$_SESSION['user_id']}");
    session_unset();
    session_destroy();
    header("Location: login.php?error=notloggedin");
    ob_end_flush();
    exit;
}

// Update last activity time
$_SESSION['last_activity'] = time();

ob_end_flush();
?>