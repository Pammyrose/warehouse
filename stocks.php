<?php
// Start session exactly once at the beginning
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration'] > 1800)) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

include 'connect.php'; // Ensure connect.php has no session_start() or output
include 'login_session.php'; // Validate session

// Log session details for debugging
error_log("Stocks: Session check, user_id=" . ($_SESSION['user_id'] ?? 'unset') . ", user=" . ($_SESSION['user'] ?? 'unset'));

// Ensure session is valid
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user'])) {
    error_log("Stocks: Session validation failed, redirecting to login");
    header("Location: login.php?error=notloggedin");
    exit();
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    $delete_query = "DELETE FROM stock_in WHERE stockin_id = ?";
    $stmt = $db->prepare($delete_query);
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $_SESSION['update_message'] = "Record deleted successfully.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=" . urlencode($_GET['tab'] ?? 'stock-entry'));
        exit;
    } else {
        error_log("Delete failed: " . $stmt->error);
        $_SESSION['update_message'] = "Failed to delete record.";
    }
}

// Handle search suggestions
if (isset($_GET['action']) && $_GET['action'] === 'get_suggestions') {
    $term = mysqli_real_escape_string($db, $_GET['term'] ?? '');
    $suggestions = [];
    if (!empty($term)) {
        $query = "SELECT DISTINCT `desc` FROM product WHERE `desc` LIKE ? LIMIT 5";
        $stmt = $db->prepare($query);
        $term = "%$term%";
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $suggestions[] = $row['desc'];
        }
    }
    header('Content-Type: application/json');
    echo json_encode($suggestions);
    exit;
}

// Handle stock starts AJAX
if (isset($_GET['action']) && $_GET['action'] === 'get_stock_starts') {
    $stock_date = mysqli_real_escape_string($db, $_GET['stock_date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stock_date)) {
        error_log("Invalid stock_date format: $stock_date");
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }
    $stock_starts = [];
    $query = "SELECT p.product_id, 
                     COALESCE(
                         (SELECT sl.ending 
                          FROM stock_log sl 
                          WHERE sl.product_id = p.product_id 
                          ORDER BY sl.date DESC 
                          LIMIT 1), 
                         p.stock
                     ) AS stock_start,
                     (SELECT sl.date 
                      FROM stock_log sl 
                      WHERE sl.product_id = p.product_id 
                      ORDER BY sl.date DESC 
                      LIMIT 1) AS last_log_date
              FROM product p";
    $result = mysqli_query($db, $query);
    if (!$result) {
        error_log("Error in get_stock_starts query: " . mysqli_error($db));
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database query failed']);
        exit;
    }
    while ($row = mysqli_fetch_assoc($result)) {
        $stock_starts[$row['product_id']] = [
            'stock_start' => (int)$row['stock_start'],
            'last_log_date' => $row['last_log_date'] ?? 'N/A'
        ];
    }
    error_log("get_stock_starts for date $stock_date: " . json_encode($stock_starts));
    header('Content-Type: application/json');
    echo json_encode($stock_starts);
    exit;
}

// Initialize variables
$activeTab = $_GET['tab'] ?? 'stock-entry';
$search_entry = $_GET['search_entry'] ?? '';
$sort_entry = $_GET['sort_entry'] ?? '';
$search_log = $_GET['search_log'] ?? '';
$sort_log = $_GET['sort_log'] ?? '';
$log_date = $_GET['log_date'] ?? '';
$stock_date = $_POST['stock_date'] ?? date('Y-m-d');

// Execute stock-entry query
$sql = "SELECT p.*, s.name AS supplier_name, 
        COALESCE(
            (SELECT sl.ending 
             FROM stock_log sl 
             WHERE sl.product_id = p.product_id 
             ORDER BY sl.date DESC 
             LIMIT 1), 
            p.stock
        ) AS last_ending
        FROM product p 
        LEFT JOIN supplier s ON p.supplier_id = s.supplier_id";

$conditions = [];
if (!empty($search_entry)) {
    $searchTerms = array_map('trim', explode(',', $search_entry));
    $searchConditions = [];
    foreach ($searchTerms as $term) {
        if (!empty($term)) {
            $escapedTerm = mysqli_real_escape_string($db, $term);
            $searchConditions[] = "(`desc` LIKE '%$escapedTerm%' OR stock LIKE '%$escapedTerm%')";
        }
    }
    if (!empty($searchConditions)) {
        $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }
}
if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}
if ($sort_entry === 'az') {
    $sql .= " ORDER BY `desc` ASC";
} elseif ($sort_entry === 'za') {
    $sql .= " ORDER BY `desc` DESC";
}
$result = mysqli_query($db, $sql);
if (!$result) {
    error_log("Error executing product query: " . mysqli_error($db));
    die('Error executing the product query: ' . mysqli_error($db));
}

// Execute stock-log query
if ($activeTab === 'stock-log' && !empty($log_date)) {
    $logDate = mysqli_real_escape_string($db, $log_date);
    $logSql = "SELECT sl.*, p.`desc`, p.price, p.stock, s.name AS supplier_name
            FROM stock_log sl
            JOIN product p ON sl.product_id = p.product_id
            LEFT JOIN supplier s ON p.supplier_id = s.supplier_id
            WHERE DATE(sl.date) = ?";
    $stmt = $db->prepare($logSql);
    $stmt->bind_param("s", $logDate);
    $stmt->execute();
    $logResult = $stmt->get_result();
    if (!$logResult) {
        error_log("Error executing stock log query: " . $db->error);
        die('Error executing the stock log query: ' . $db->error);
    }
}

// Handle form submission
if (isset($_POST['save_totals'])) {
    error_log("save_totals: Starting, user_id={$_SESSION['user_id']}, user={$_SESSION['user']}, POST data=" . json_encode($_POST));
    $date = $_POST['stock_date'] ?? date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        error_log("Invalid stock_date format in save_totals: $date");
        $_SESSION['update_message'] = "Invalid date format.";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=stock-entry&search_entry=" . urlencode($search_entry) . "&sort_entry=" . urlencode($sort_entry));
        exit;
    }
    mysqli_begin_transaction($db);
    try {
        foreach ($_POST['total'] as $product_id => $total_value) {
            $product_id = (int)$product_id;
            $total = ($total_value !== '' && $total_value !== null) ? (int)$total_value : null;
            $stock_start = isset($_POST['stock_start'][$product_id]) && $_POST['stock_start'][$product_id] !== '' ? (int)$_POST['stock_start'][$product_id] : null;
            $additional = isset($_POST['additional'][$product_id]) && $_POST['additional'][$product_id] !== '' ? (int)$_POST['additional'][$product_id] : null;
            $pyesta_outstock = isset($_POST['pyesta_outstock'][$product_id]) && $_POST['pyesta_outstock'][$product_id] !== '' ? (int)$_POST['pyesta_outstock'][$product_id] : null;
            $save5_outstock = isset($_POST['save5_outstock'][$product_id]) && $_POST['save5_outstock'][$product_id] !== '' ? (int)$_POST['save5_outstock'][$product_id] : null;
            $out_stock = isset($_POST['out_stock'][$product_id]) && $_POST['out_stock'][$product_id] !== '' ? (int)$_POST['out_stock'][$product_id] : null;
            $ending = ($total !== null && $pyesta_outstock !== null && $save5_outstock !== null && $out_stock !== null)
                ? max(0, $total - ($pyesta_outstock + $save5_outstock + $out_stock))
                : null;
            if ($stock_start === null && $additional === null && $total === null && $pyesta_outstock === null && $save5_outstock === null && $out_stock === null && $ending === null) {
                error_log("Skipping product_id=$product_id: No meaningful input provided");
                continue;
            }
            error_log("Processing product_id=$product_id, date=$date, stock_start=$stock_start, additional=$additional, total=$total, pyesta_outstock=$pyesta_outstock, save5_outstock=$save5_outstock, out_stock=$out_stock, ending=$ending");
            $stmt = $db->prepare("SELECT log_id FROM stock_log WHERE product_id = ? AND DATE(date) = ?");
            $stmt->bind_param("is", $product_id, $date);
            $stmt->execute();
            $checkResult = $stmt->get_result();
            if ($checkResult->num_rows > 0) {
                $row = $checkResult->fetch_assoc();
                $log_id = (int)$row['log_id'];
                $updateFields = [];
                $updateValues = [];
                $types = '';
                if ($stock_start !== null) {
                    $updateFields[] = "stock_start = ?";
                    $updateValues[] = $stock_start;
                    $types .= 'i';
                }
                if ($additional !== null) {
                    $updateFields[] = "additional = ?";
                    $updateValues[] = $additional;
                    $types .= 'i';
                }
                if ($total !== null) {
                    $updateFields[] = "total = ?";
                    $updateValues[] = $total;
                    $types .= 'i';
                }
                if ($pyesta_outstock !== null) {
                    $updateFields[] = "pyesta_outstock = ?";
                    $updateValues[] = $pyesta_outstock;
                    $types .= 'i';
                }
                if ($save5_outstock !== null) {
                    $updateFields[] = "save5_outstock = ?";
                    $updateValues[] = $save5_outstock;
                    $types .= 'i';
                }
                if ($out_stock !== null) {
                    $updateFields[] = "out_stock = ?";
                    $updateValues[] = $out_stock;
                    $types .= 'i';
                }
                if ($ending !== null) {
                    $updateFields[] = "ending = ?";
                    $updateValues[] = $ending;
                    $types .= 'i';
                }
                if (!empty($updateFields)) {
                    $updateQuery = "UPDATE stock_log SET " . implode(', ', $updateFields) . " WHERE log_id = ?";
                    $stmt = $db->prepare($updateQuery);
                    $types .= 'i';
                    $updateValues[] = $log_id;
                    $stmt->bind_param($types, ...$updateValues);
                    if (!$stmt->execute()) {
                        error_log("Error updating stock log for log_id=$log_id: " . $stmt->error);
                        throw new Exception("Error updating stock log: " . $stmt->error);
                    }
                    error_log("Updated stock_log for log_id=$log_id");
                }
            } else {
                $insertFields = ['product_id', 'date'];
                $insertValues = [$product_id, $date];
                $placeholders = ['?', '?'];
                $types = 'is';
                if ($stock_start !== null) {
                    $insertFields[] = 'stock_start';
                    $insertValues[] = $stock_start;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($additional !== null) {
                    $insertFields[] = 'additional';
                    $insertValues[] = $additional;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($total !== null) {
                    $insertFields[] = 'total';
                    $insertValues[] = $total;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($pyesta_outstock !== null) {
                    $insertFields[] = 'pyesta_outstock';
                    $insertValues[] = $pyesta_outstock;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($save5_outstock !== null) {
                    $insertFields[] = 'save5_outstock';
                    $insertValues[] = $save5_outstock;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($out_stock !== null) {
                    $insertFields[] = 'out_stock';
                    $insertValues[] = $out_stock;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                if ($ending !== null) {
                    $insertFields[] = 'ending';
                    $insertValues[] = $ending;
                    $placeholders[] = '?';
                    $types .= 'i';
                }
                $insertQuery = "INSERT INTO stock_log (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $db->prepare($insertQuery);
                $stmt->bind_param($types, ...$insertValues);
                if (!$stmt->execute()) {
                    error_log("Error inserting stock log for product_id=$product_id: " . $stmt->error);
                    throw new Exception("Error inserting stock log: " . $stmt->error);
                }
                error_log("Inserted new stock_log for product_id=$product_id");
            }
            if ($ending !== null) {
                $stmt = $db->prepare("SELECT date FROM stock_log WHERE product_id = ? ORDER BY date DESC LIMIT 1");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $latestLogResult = $stmt->get_result();
                if ($latestLogResult && $latestLogRow = $latestLogResult->fetch_assoc()) {
                    if ($date === date('Y-m-d', strtotime($latestLogRow['date']))) {
                        $stmt = $db->prepare("UPDATE product SET stock = ? WHERE product_id = ?");
                        $stmt->bind_param("ii", $ending, $product_id);
                        if (!$stmt->execute()) {
                            error_log("Error updating product stock for product_id=$product_id: " . $stmt->error);
                            throw new Exception("Error updating product stock: " . $stmt->error);
                        }
                        error_log("Updated product stock for product_id=$product_id to $ending");
                    }
                }
            }
        }
        mysqli_commit($db);
        $_SESSION['update_message'] = "Stock logged successfully!";
        error_log("save_totals: Transaction committed successfully");
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=stock-entry&search_entry=" . urlencode($search_entry) . "&sort_entry=" . urlencode($sort_entry));
        exit;
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log("save_totals: Transaction failed: " . $e->getMessage());
        $_SESSION['update_message'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=stock-entry&search_entry=" . urlencode($search_entry) . "&sort_entry=" . urlencode($sort_entry));
        exit;
    }
}

if (isset($_POST['update_log_all'])) {
    error_log("update_log_all: Starting, user_id={$_SESSION['user_id']}, user={$_SESSION['user']}, POST data=" . json_encode($_POST));
    mysqli_begin_transaction($db);
    try {
        foreach ($_POST['log_id'] as $log_id => $id) {
            $log_id = (int)$log_id;
            $stock_start = isset($_POST['stock_start'][$log_id]) ? (int)$_POST['stock_start'][$log_id] : 0;
            $additional = isset($_POST['additional'][$log_id]) ? (int)$_POST['additional'][$log_id] : 0;
            $total = isset($_POST['total'][$log_id]) ? (int)$_POST['total'][$log_id] : 0;
            $pyesta_outstock = isset($_POST['pyesta_outstock'][$log_id]) ? (int)$_POST['pyesta_outstock'][$log_id] : 0;
            $save5_outstock = isset($_POST['save5_outstock'][$log_id]) ? (int)$_POST['save5_outstock'][$log_id] : 0;
            $out_stock = isset($_POST['out_stock'][$log_id]) ? (int)$_POST['out_stock'][$log_id] : 0;
            $ending = max(0, $total - ($pyesta_outstock + $save5_outstock + $out_stock));
            error_log("Updating log_id=$log_id: stock_start=$stock_start, additional=$additional, total=$total, pyesta_outstock=$pyesta_outstock, save5_outstock=$save5_outstock, out_stock=$out_stock, ending=$ending");
            $stmt = $db->prepare("UPDATE stock_log SET stock_start = ?, additional = ?, total = ?, pyesta_outstock = ?, save5_outstock = ?, out_stock = ?, ending = ? WHERE log_id = ?");
            $stmt->bind_param("iiiiiiii", $stock_start, $additional, $total, $pyesta_outstock, $save5_outstock, $out_stock, $ending, $log_id);
            if (!$stmt->execute()) {
                error_log("Error updating stock log for log_id=$log_id: " . $stmt->error);
                throw new Exception("Error updating stock log: " . $stmt->error);
            }
            error_log("Updated stock_log for log_id=$log_id");
            $stmt = $db->prepare("SELECT product_id, date FROM stock_log WHERE log_id = ?");
            $stmt->bind_param("i", $log_id);
            $stmt->execute();
            $product_id_result = $stmt->get_result();
            if ($product_id_row = $product_id_result->fetch_assoc()) {
                $product_id = (int)$product_id_row['product_id'];
                $log_date = date('Y-m-d', strtotime($product_id_row['date']));
                $stmt = $db->prepare("SELECT date FROM stock_log WHERE product_id = ? ORDER BY date DESC LIMIT 1");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $latest_log_result = $stmt->get_result();
                if ($latest_log_result && $latest_log_row = $latest_log_result->fetch_assoc()) {
                    if ($log_date === date('Y-m-d', strtotime($latest_log_row['date']))) {
                        $stmt = $db->prepare("UPDATE product SET stock = ? WHERE product_id = ?");
                        $stmt->bind_param("ii", $ending, $product_id);
                        if (!$stmt->execute()) {
                            error_log("Error updating product stock for product_id=$product_id: " . $stmt->error);
                            throw new Exception("Error updating product stock: " . $stmt->error);
                        }
                        error_log("Updated product stock for product_id=$product_id to $ending");
                    }
                }
            }
        }
        mysqli_commit($db);
        error_log("update_log_all: Transaction committed successfully");
        $_SESSION['update_message'] = "Stock log updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=stock-log&log_date=" . urlencode($log_date) . "&search_log=" . urlencode($search_log) . "&sort_log=" . urlencode($sort_log));
        exit;
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log("update_log_all: Transaction failed: " . $e->getMessage());
        $_SESSION['update_message'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF'] . "?tab=stock-log&log_date=" . urlencode($log_date) . "&search_log=" . urlencode($search_log) . "&sort_log=" . urlencode($sort_log));
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .suggestions {
            position: absolute;
            z-index: 20;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.875rem;
            color: #374151;
        }
        .suggestion-item:hover {
            background-color: #f3f4f6;
        }
        .loading::after {
            content: 'Loading...';
            color: #999;
            font-size: 0.8rem;
            margin-left: 10px;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            50% { opacity: 0.5; }
        }
        .message {
            animation: fadeOut 5s ease-in-out forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; }
        }
    </style>
    <script>
        function updateSort(sortValue, tab) {
            console.log(`Updating sort: ${sortValue} for tab: ${tab}`);
            const url = new URL(window.location.href);
            if (tab === 'stock-entry') {
                url.searchParams.set('sort_entry', sortValue);
                url.searchParams.set('search_entry', url.searchParams.get('search_entry') || '');
                url.searchParams.set('tab', 'stock-entry');
            } else if (tab === 'stock-log') {
                url.searchParams.set('sort_log', sortValue);
                url.searchParams.set('search_log', url.searchParams.get('search_log') || '');
                url.searchParams.set('log_date', url.searchParams.get('log_date') || '');
                url.searchParams.set('tab', 'stock-log');
            }
            window.location.href = url.toString();
        }

        function showSuggestions(input, suggestionsContainer, tab) {
            const query = input.value.trim();
            if (query.length < 1) {
                suggestionsContainer.innerHTML = '';
                suggestionsContainer.classList.add('hidden');
                return;
            }
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_suggestions&term=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    suggestionsContainer.innerHTML = '';
                    if (data.length > 0) {
                        data.forEach(item => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.textContent = item;
                            div.addEventListener('click', () => {
                                const currentValue = input.value.split(',').map(s => s.trim()).filter(s => s);
                                if (!currentValue.includes(item)) {
                                    currentValue.push(item);
                                    input.value = currentValue.join(', ');
                                }
                                suggestionsContainer.innerHTML = '';
                                suggestionsContainer.classList.add('hidden');
                                input.form.submit();
                            });
                            suggestionsContainer.appendChild(div);
                        });
                        suggestionsContainer.classList.remove('hidden');
                    } else {
                        suggestionsContainer.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error fetching suggestions:', error);
                    suggestionsContainer.classList.add('hidden');
                });
        }

        function calculateTotal(row, id, tab) {
            const startStockInput = row.querySelector(`input[name="stock_start[${id}]"]`);
            const additionalInput = row.querySelector(`input[name="additional[${id}]"]`);
            const totalInput = row.querySelector(`input[name="total[${id}]"]`);
            if (!startStockInput || !additionalInput || !totalInput) {
                console.error(`Missing elements for total calculation in ${tab} tab, id ${id}`, row);
                return;
            }
            const startStock = parseInt(startStockInput.value) || 0;
            const additional = parseInt(additionalInput.value) || 0;
            const total = startStock + additional;
            console.log(`Calculating total for ${tab} tab, id ${id}: startStock=${startStock}, additional=${additional}, total=${total}`);
            totalInput.value = total >= 0 ? total : 0;
            return total;
        }

        function calculateEnding(row, id, tab) {
            const totalInput = row.querySelector(`input[name="total[${id}]"]`);
            const pyestaInput = row.querySelector(`input[name="pyesta_outstock[${id}]"]`);
            const save5Input = row.querySelector(`input[name="save5_outstock[${id}]"]`);
            const outInput = row.querySelector(`input[name="out_stock[${id}]"]`);
            const endingInput = row.querySelector(`input[name="ending[${id}]"]`);
            if (!totalInput || !pyestaInput || !save5Input || !outInput || !endingInput) {
                console.error(`Missing elements for ending calculation in ${tab} tab, id ${id}`, row);
                return;
            }
            const total = parseInt(totalInput.value) || 0;
            const pyesta = parseInt(pyestaInput.value) || 0;
            const save5 = parseInt(save5Input.value) || 0;
            const out = parseInt(outInput.value) || 0;
            const ending = total - (pyesta + save5 + out);
            console.log(`Calculating ending for ${tab} tab, id ${id}: total=${total}, pyesta=${pyesta}, save5=${save5}, out=${out}, ending=${ending}`);
            endingInput.value = ending >= 0 ? ending : 0;
        }

        function debounce(func, wait) {
            let timeout;
            return function (...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        }

        function updateStockStarts(stockDate) {
            if (!stockDate || !/^\d{4}-\d{2}-\d{2}$/.test(stockDate)) {
                console.warn('Invalid or empty stock date, skipping updateStockStarts:', stockDate);
                return;
            }
            console.log(`Fetching stock starts for date: ${stockDate}`);
            const stockDateInput = document.querySelector('input[name="stock_date"]');
            if (!stockDateInput) {
                console.error('Stock date input not found');
                return;
            }
            stockDateInput.classList.add('loading');
            fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_stock_starts&stock_date=${encodeURIComponent(stockDate)}`)
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    console.log('Received stock starts:', data);
                    if (data.error) {
                        console.error('Server error:', data.error);
                        alert('Failed to fetch stock start values: ' + data.error);
                        return;
                    }
                    document.querySelectorAll('#stock-entry tr').forEach(row => {
                        const idInput = row.querySelector('input[name^="total"]');
                        if (idInput) {
                            const id = idInput.name.match(/\[(.*?)\]/)[1];
                            const startStockInput = row.querySelector(`input[name="stock_start[${id}]"]`);
                            if (startStockInput && data[id]) {
                                const oldValue = startStockInput.value;
                                startStockInput.value = data[id].stock_start;
                                console.log(`Updated stock_start for product_id=${id}: ${oldValue} -> ${data[id].stock_start} (from log date ${data[id].last_log_date})`);
                                calculateTotal(row, id, 'stock-entry');
                                calculateEnding(row, id, 'stock-entry');
                            } else {
                                console.warn(`No stock_start data for product_id=${id} or missing input`);
                            }
                        }
                    });
                    stockDateInput.classList.remove('loading');
                })
                .catch(error => {
                    console.error('Error fetching stock starts:', error);
                    stockDateInput.classList.remove('loading');
                    alert('Error fetching stock start values. Check console for details.');
                });
        }

        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM fully loaded, initializing...');
            const toggles = {
                'entry': document.getElementById('dropdownToggleEntry'),
                'log': document.getElementById('dropdownToggleLog')
            };
            const menus = {
                'entry': document.getElementById('dropdownMenuEntry'),
                'log': document.getElementById('dropdownMenuLog')
            };
            Object.keys(toggles).forEach(tab => {
                if (toggles[tab] && menus[tab]) {
                    toggles[tab].addEventListener('click', () => {
                        console.log(`Toggling dropdown for ${tab}`);
                        menus[tab].classList.toggle('hidden');
                    });
                    document.addEventListener('click', (event) => {
                        if (!toggles[tab].contains(event.target) && !menus[tab].contains(event.target)) {
                            menus[tab].classList.add('hidden');
                        }
                    });
                }
            });
            const entrySearch = document.querySelector('input[name="search_entry"]');
            const logSearch = document.querySelector('input[name="search_log"]');
            const entrySuggestions = document.createElement('div');
            const logSuggestions = document.createElement('div');
            entrySuggestions.className = 'suggestions hidden';
            logSuggestions.className = 'suggestions hidden';
            entrySearch.parentElement.appendChild(entrySuggestions);
            logSearch.parentElement.appendChild(logSuggestions);
            const debouncedShowSuggestions = debounce((input, container, tab) => showSuggestions(input, container, tab), 300);
            entrySearch.addEventListener('input', () => debouncedShowSuggestions(entrySearch, entrySuggestions, 'stock-entry'));
            logSearch.addEventListener('input', () => debouncedShowSuggestions(logSearch, logSuggestions, 'stock-log'));
            document.addEventListener('click', (event) => {
                if (!entrySearch.contains(event.target) && !entrySuggestions.contains(event.target)) {
                    entrySuggestions.classList.add('hidden');
                }
                if (!logSearch.contains(event.target) && !logSuggestions.contains(event.target)) {
                    logSuggestions.classList.add('hidden');
                }
            });
            const stockDateInput = document.querySelector('input[name="stock_date"]');
            if (stockDateInput) {
                const debouncedUpdateStockStarts = debounce(stockDate => updateStockStarts(stockDate), 300);
                stockDateInput.addEventListener('change', () => {
                    const stockDate = stockDateInput.value;
                    console.log(`Stock date changed to: ${stockDate}`);
                    debouncedUpdateStockStarts(stockDate);
                });
                stockDateInput.addEventListener('input', () => {
                    const stockDate = stockDateInput.value;
                    console.log(`Stock date input: ${stockDate}`);
                    debouncedUpdateStockStarts(stockDate);
                });
            } else {
                console.error('Stock date input not found');
            }
            function clearStockInputs() {
                console.log('Clearing stock inputs');
                const form = document.getElementById('stockForm');
                ['stock_start', 'additional', 'pyesta_outstock', 'save5_outstock', 'out_stock'].forEach(field => {
                    const inputs = form.querySelectorAll(`input[name^="${field}"]`);
                    inputs.forEach(input => {
                        input.value = '0';
                    });
                });
                document.querySelectorAll('#stock-entry tr').forEach(row => {
                    const idInput = row.querySelector('input[name^="total"]');
                    if (idInput) {
                        const id = idInput.name.match(/\[(.*?)\]/)[1];
                        calculateTotal(row, id, 'stock-entry');
                        calculateEnding(row, id, 'stock-entry');
                    }
                });
                const stockDateInput = form.querySelector('input[name="stock_date"]');
                if (stockDateInput) {
                    stockDateInput.value = '<?php echo date('Y-m-d'); ?>';
                    updateStockStarts(stockDateInput.value);
                }
            }
            const clearButton = document.querySelector('button[onclick="clearStockInputs()"]');
            if (clearButton) clearButton.addEventListener('click', clearStockInputs);
            const tabButtons = document.querySelectorAll('.tab-button');
            const tabContents = document.querySelectorAll('.tab-content');
            function activateTab(tabId) {
                console.log(`Activating tab: ${tabId}`);
                tabButtons.forEach(btn => {
                    const isActive = btn.getAttribute('data-tab') === tabId;
                    btn.classList.toggle('border-gray-700', isActive);
                    btn.classList.toggle('text-black', isActive);
                    btn.classList.toggle('active-tab', isActive);
                });
                tabContents.forEach(content => {
                    content.classList.toggle('hidden', content.id !== tabId);
                });
                if (tabId === 'stock-entry' && stockDateInput) {
                    updateStockStarts(stockDateInput.value);
                }
            }
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tabId);
                    window.history.pushState({}, '', url);
                    activateTab(tabId);
                });
            });
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || 'stock-entry';
            activateTab(tab);
            ['stock-entry', 'stock-log'].forEach(tab => {
                document.querySelectorAll(`#${tab} input[name^="stock_start"], #${tab} input[name^="additional"]`).forEach(input => {
                    input.addEventListener('input', function() {
                        const row = this.closest('tr');
                        const id = this.name.match(/\[(.*?)\]/)[1];
                        console.log(`Input changed in ${tab} tab, id ${id}: ${this.name}=${this.value}`);
                        calculateTotal(row, id, tab);
                        calculateEnding(row, id, tab);
                    });
                });
                document.querySelectorAll(`#${tab} input[name^="total"], #${tab} input[name^="pyesta_outstock"], #${tab} input[name^="save5_outstock"], #${tab} input[name^="out_stock"]`).forEach(input => {
                    input.addEventListener('input', function() {
                        const row = this.closest('tr');
                        const id = this.name.match(/\[(.*?)\]/)[1];
                        console.log(`Input changed in ${tab} tab, id ${id}: ${this.name}=${this.value}`);
                        calculateEnding(row, id, tab);
                    });
                });
            });
            console.log('Initializing total and ending values for all rows');
            ['stock-entry', 'stock-log'].forEach(tab => {
                document.querySelectorAll(`#${tab} tr`).forEach(row => {
                    const idInput = row.querySelector('input[name^="total"]');
                    if (idInput) {
                        const id = idInput.name.match(/\[(.*?)\]/)[1];
                        console.log(`Initializing row in ${tab} tab, id ${id}`);
                        calculateTotal(row, id, tab);
                        calculateEnding(row, id, tab);
                    }
                });
            });
            if (stockDateInput && tab === 'stock-entry') {
                updateStockStarts(stockDateInput.value);
            }
            // Fade out update message after 5 seconds
            const messageDiv = document.querySelector('.message');
            if (messageDiv) {
                setTimeout(() => {
                    messageDiv.style.display = 'none';
                }, 5000); // Remove from DOM after animation
            }
        });
    </script>
</head>
<body>
<?php include("sidebar.php"); ?>
<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-5 lg:ml-[250px]">
    <?php if (isset($_SESSION['update_message'])): ?>
        <div class="message fixed top-20 left-2/3 transform -translate-x-1/2 -translate-y-1/2 z-50 bg-red-100 text-red-700 font-semibold px-6 py-3 rounded-lg shadow-lg flex justify-start items-start">
            <?php echo htmlspecialchars($_SESSION['update_message']); unset($_SESSION['update_message']); ?>
        </div>
    <?php endif; ?>
    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4 mt-10 w-full">
        <div class="w-full flex justify-start mb-4 border-b border-gray-300">
            <button class="tab-button px-4 py-2 font-semibold text-gray-700 hover:text-black border-b-2 border-transparent hover:border-gray-700 <?php echo $activeTab === 'stock-entry' ? 'active-tab border-gray-700 text-black' : ''; ?>" data-tab="stock-entry">Stocks</button>
            <button class="tab-button px-4 py-2 font-semibold text-gray-700 hover:text-black border-b-2 border-transparent hover:border-gray-700 <?php echo $activeTab === 'stock-log' ? 'active-tab border-gray-700 text-black' : ''; ?>" data-tab="stock-log">Stock Log</button>
        </div>
        <div id="stock-entry" class="tab-content <?php echo $activeTab === 'stock-entry' ? 'block' : 'hidden'; ?>">
            <div class="flex justify-between items-center mb-4">
                <div class="relative inline-block">
                    <button id="dropdownToggleEntry" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                        Sort by Supplier
                        <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                        </svg>
                    </button>
                    <div id="dropdownMenuEntry" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                        <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggleEntry">
                            <li>
                                <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                    <input id="filter-radio-az-entry" type="radio" value="az" name="filter-radio-entry" <?php if ($sort_entry === 'az') echo 'checked'; ?> onclick="updateSort('az', 'stock-entry')" class="w-4 h-4">
                                    <label for="filter-radio-az-entry" class="ms-2 text-sm font-medium">A – Z</label>
                                </div>
                            </li>
                            <li>
                                <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                    <input id="filter-radio-za-entry" type="radio" value="za" name="filter-radio-entry" <?php if ($sort_entry === 'za') echo 'checked'; ?> onclick="updateSort('za', 'stock-entry')" class="w-4 h-4">
                                    <label for="filter-radio-za-entry" class="ms-2 text-sm font-medium">Z – A</label>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
                <form method="GET" class="flex space-x-2 items-center">
                    <input type="hidden" name="tab" value="stock-entry">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                            <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <input type="text" name="search_entry" value="<?php echo htmlspecialchars($search_entry); ?>" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search (e.g., apple,orange)" autocomplete="off" />
                    </div>
                </form>
            </div>
            <form method="POST" action="" id="stockForm">
                <input type="hidden" name="tab" value="stock-entry">
                <div class="flex justify-between">
                    <div class="flex items-center space-x-2">
                        <input type="date" name="stock_date" value="<?php echo htmlspecialchars($stock_date); ?>" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-4 py-2 w-48" required>
                    </div>
                    <div>
                        <button type="submit" name="save_totals" class="bg-gray-900 text-white px-4 py-2 rounded hover:bg-gray-700 mt-5 mb-2">
                            Save
                        </button>
                        <button type="button" onclick="clearStockInputs()" class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition mb-2">
                            Clear
                        </button>
                    </div>
                </div>
                <div class="flex-auto w-full">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                        <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                            <tr>
                                <th class="px-4 py-2">No</th>
                                <th class="px-4 py-2">Supplier</th>
                                <th class="px-4 py-2">Item</th>
                                <th class="px-4 py-2">Start Stock</th>
                                <th class="px-4 py-2">Additional Stock</th>
                                <th class="px-4 py-2">Total Stock</th>
                                <th class="px-4 py-2">Pyesta Outstock</th>
                                <th class="px-4 py-2">Save5 Outstock</th>
                                <th class="px-4 py-2">Out Stock</th>
                                <th class="px-4 py-2">Ending Inventory</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            if ($result && mysqli_num_rows($result) > 0):
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)) : 
                                    $stock_start = $row['last_ending'] !== null ? (int)$row['last_ending'] : (int)$row['stock'];
                                    $initial_total = $stock_start;
                                    $initial_ending = $initial_total;
                                    ?>
                                    <tr class="bg-white text-black text-center border-b">
                                        <td class="px-4 py-2"><?php echo $counter++; ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></td>
                                        <td class="px-4 py-2"><?php echo htmlspecialchars($row['desc']); ?></td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="stock_start[<?php echo $row['product_id']; ?>]" value="<?php echo $stock_start; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" readonly />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="additional[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="total[<?php echo $row['product_id']; ?>]" value="<?php echo $initial_total; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" readonly />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="pyesta_outstock[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="save5_outstock[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="out_stock[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                        </td>
                                        <td class="px-1 py-2">
                                            <input type="number" name="ending[<?php echo $row['product_id']; ?>]" value="<?php echo $initial_ending; ?>" readonly class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center px-4 py-4 text-gray-500 bg-white">
                                        No records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
        <div id="stock-log" class="tab-content <?php echo $activeTab === 'stock-log' ? 'block' : 'hidden'; ?>">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center space-x-2">
                    <div class="relative inline-block">
                        <button id="dropdownToggleLog" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                            Sort by Supplier
                            <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                            </svg>
                        </button>
                        <div id="dropdownMenuLog" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                            <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggleLog">
                                <li>
                                    <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                        <input id="filter-radio-az-log" type="radio" value="az" name="filter-radio-log" <?php if ($sort_log === 'az') echo 'checked'; ?> onclick="updateSort('az', 'stock-log')" class="w-4 h-4">
                                        <label for="filter-radio-az-log" class="ms-2 text-sm font-medium">A – Z</label>
                                    </div>
                                </li>
                                <li>
                                    <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                        <input id="filter-radio-za-log" type="radio" value="za" name="filter-radio-log" <?php if ($sort_log === 'za') echo 'checked'; ?> onclick="updateSort('za', 'stock-log')" class="w-4 h-4">
                                        <label for="filter-radio-za-log" class="ms-2 text-sm font-medium">Z – A</label>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <form method="GET" class="flex items-center space-x-2">
                        <input type="hidden" name="tab" value="stock-log">
                        <div class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5">
                            <input type="date" id="log_date" name="log_date" class="bg-transparent focus:outline-none" value="<?php echo htmlspecialchars($log_date); ?>">
                        </div>
                        <button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-700">View</button>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                                <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                            </svg>
                            </div>
                            <input type="text" name="search_log" value="<?php echo htmlspecialchars($search_log); ?>" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search (e.g., apple,orange)" autocomplete="off" />
                        </div>
                    </form>
                </div>
            </div>
            <?php if (!empty($log_date) && isset($logResult)): ?>
                <h3 class="text-lg font-bold mb-4">Stock Log for <?php echo htmlspecialchars($log_date); ?></h3>
            <?php endif; ?>
            <?php if (isset($logResult) && mysqli_num_rows($logResult) > 0): ?>
                <form method="POST">
                    <input type="hidden" name="tab" value="stock-log">
                    <button type="submit" name="update_log_all" class="bg-gray-900 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm mb-4">
                        Update All Logs
                    </button>
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500">
                        <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                            <tr>
                                <th class="px-4 py-2">No</th>
                                <th class="px-4 py-2">Supplier</th>
                                <th class="px-4 py-2">Item</th>
                                <th class="px-4 py-2">Date/Time</th>
                                <th class="px-4 py-2">Start Stock</th>
                                <th class="px-4 py-2">Additional Stock</th>
                                <th class="px-4 py-2">Total Stock</th>
                                <th class="px-4 py-2">Pyesta Outstock</th>
                                <th class="px-4 py-2">Save5 Outstock</th>
                                <th class="px-4 py-2">Out Stock</th>
                                <th class="px-4 py-2">Ending Inventory</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $logCounter = 1;
                            while ($row = mysqli_fetch_assoc($logResult)) : 
                                $initial_total = (int)$row['stock_start'] + (int)$row['additional'];
                                $initial_ending = $initial_total - ((int)$row['pyesta_outstock'] + (int)$row['save5_outstock'] + (int)$row['out_stock']);
                                ?>
                                <tr class="bg-white text-black text-center border-b">
                                    <input type="hidden" name="log_id[<?php echo $row['log_id']; ?>]" value="<?php echo $row['log_id']; ?>">
                                    <td class="px-4 py-2"><?php echo $logCounter++; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['supplier_name'] ?? 'N/A'); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['desc']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['date']); ?></td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="stock_start[<?php echo $row['log_id']; ?>]" value="<?php echo $row['stock_start']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="additional[<?php echo $row['log_id']; ?>]" value="<?php echo $row['additional']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="total[<?php echo $row['log_id']; ?>]" value="<?php echo $initial_total; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" readonly />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="pyesta_outstock[<?php echo $row['log_id']; ?>]" value="<?php echo $row['pyesta_outstock']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="save5_outstock[<?php echo $row['log_id']; ?>]" value="<?php echo $row['save5_outstock']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="out_stock[<?php echo $row['log_id']; ?>]" value="<?php echo $row['out_stock']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" min="0" />
                                    </td>
                                    <td class="px-1 py-2">
                                        <input type="number" name="ending[<?php echo $row['log_id']; ?>]" value="<?php echo $initial_ending; ?>" readonly class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </form>
            <?php else: ?>
                <div class="text-center text-gray-500 py-8">
                    No stock log records found for this date.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php $db->close(); ?>
</body>
</html>