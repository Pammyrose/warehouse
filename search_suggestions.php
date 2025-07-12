<?php
include('login_session.php');
include "connect.php";

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Acceptable types and their corresponding tables/fields
$types = [
    'entry' => ['table' => 'stock_in', 'fields' => ['item', 'supplier']],
    'log'   => ['table' => 'stock_log', 'fields' => ['item', 'supplier']]
];

$type = $_GET['type'] ?? 'entry';
$search = $_GET['q'] ?? '';

if (!array_key_exists($type, $types)) {
    echo json_encode([]);
    exit;
}

$table = $types[$type]['table'];
$fields = $types[$type]['fields'];

$suggestions = [];

if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $priorTerms = array_slice($searchTerms, 0, -1);
    $lastTerm = end($searchTerms);
    $lastTermEscaped = $db->real_escape_string($lastTerm);

    $conditions = [];

    foreach ($fields as $field) {
        $conditions[] = "LOWER($field) LIKE LOWER('%$lastTermEscaped%')";
    }

    $whereClause = implode(' OR ', $conditions);
    $query = "SELECT DISTINCT " . implode(", ", $fields) . " FROM $table WHERE $whereClause LIMIT 20";
    $result = $db->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            foreach ($fields as $field) {
                if (!empty($row[$field])) {
                    $suggestions[] = $row[$field];
                }
            }
        }
    }

    // Remove duplicates and sort naturally
    $suggestions = array_unique($suggestions);
    usort($suggestions, 'strnatcasecmp');
}

echo json_encode(array_values($suggestions), JSON_HEX_QUOT | JSON_HEX_TAG);
?>
