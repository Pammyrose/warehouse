<?php
function validate($data, $isPassword = false) {
    $data = trim($data);
    if (!$isPassword) {
        $data = htmlspecialchars(stripslashes($data));
    }
    return $data;
}