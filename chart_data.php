<?php
$db = new mysqli('localhost', 'root', '', 'warehouse');
if ($db->connect_error) {
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Get start and end from URL
$start = isset($_GET['start']) ? $_GET['start'] : null;
$end = isset($_GET['end']) ? $_GET['end'] : null;

if (!$start || !$end) {
    echo json_encode(['error' => 'Start and end dates are required']);
    exit;
}

function getSumTotal($db, $table, $start_date, $end_date) {
    $stmt = $db->prepare("SELECT DATE(date) as day, SUM(total) as stock_in FROM $table WHERE date BETWEEN ? AND ? GROUP BY DATE(date)");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[$row['day']] = (int)$row['stock_in'];
    }
    return $data;
}

function getSumSold($db, $table, $start_date, $end_date) {
    $stmt = $db->prepare("
        SELECT DATE(date) as day, 
               SUM(COALESCE(out_stock, 0) + COALESCE(pyesta_outstock, 0) + COALESCE(save5_outstock, 0)) as stock_out 
        FROM $table 
        WHERE date BETWEEN ? AND ? 
        GROUP BY DATE(date)
    ");
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[$row['day']] = (int)$row['stock_out'];
    }
    return $data;
}

function fillZeroData($period, $data) {
    $filled = [];
    foreach ($period as $day) {
        $filled[] = $data[$day] ?? 0;
    }
    return $filled;
}

// Generate list of dates between start and end
$periodDates = [];
$current = strtotime(date('Y-m-d', strtotime($start)));
$endTime = strtotime(date('Y-m-d', strtotime($end)));

while ($current <= $endTime) {
    $periodDates[] = date('Y-m-d', $current);
    $current = strtotime('+1 day', $current);
}

$stockIn = getSumTotal($db, 'stock_log', $start, $end);
$stockOut = getSumSold($db, 'stock_log', $start, $end);

$response = [
    'labels' => $periodDates,
    'stock_in' => fillZeroData($periodDates, $stockIn),
    'stock_out' => fillZeroData($periodDates, $stockOut),
];

echo json_encode($response);
?>