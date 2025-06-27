<?php
include('login_session.php');
include "connect.php";

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    $delete_query = "DELETE FROM stock_in WHERE stockin_id = '$delete_id'";
    
    if (mysqli_query($db, $delete_query)) {
        $_SESSION['update_message'] = "Record deleted successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['update_message'] = "Failed to delete record.";
    }
}

// Search and sort
$sort = $_GET['sort'] ?? '';
$search = $_GET['search'] ?? '';
$searchEscaped = mysqli_real_escape_string($db, $search);

$sql = "SELECT * FROM product ";

$conditions = [];

$activeTab = $_GET['tab'] ?? 'stock-entry';

if ($activeTab === 'stock-log' && isset($_GET['log_date']) && $_GET['log_date']) {
    $logDate = mysqli_real_escape_string($db, $_GET['log_date']);
    $logSql = "SELECT sl.*, p.`desc`, p.price, p.stock
            FROM stock_log sl
            JOIN product p ON sl.product_id = p.product_id
            WHERE sl.date = '$logDate'";

    if (!empty($search)) {
        $logSql .= " AND (p.`desc` LIKE '%$searchEscaped%' OR sl.stock_start LIKE '%$searchEscaped%' OR sl.total_expenses LIKE '%$searchEscaped%')";
    }

    if ($sort === 'az') {
        $logSql .= " ORDER BY p.`desc` ASC";
    } elseif ($sort === 'za') {
        $logSql .= " ORDER BY p.`desc` DESC";
    }

    $logResult = mysqli_query($db, $logSql);
} else {
    $sql = "SELECT * FROM product";

    $conditions = [];

    if (!empty($search)) {
        $conditions[] = "(`desc` LIKE '%$searchEscaped%' OR stock LIKE '%$searchEscaped%' OR total_expenses LIKE '%$searchEscaped%')";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    if ($sort === 'az') {
        $sql .= " ORDER BY `desc` ASC";
    } elseif ($sort === 'za') {
        $sql .= " ORDER BY `desc` DESC";
    }

    $result = mysqli_query($db, $sql);
}


$result = mysqli_query($db, $sql);
if (!$result) {
    die('Error executing the query: ' . mysqli_error($db));
}

if (isset($_POST['save_totals'])) {
    $date = mysqli_real_escape_string($db, $_POST['stock_date']);
    $classification = mysqli_real_escape_string($db, $_POST['classification'] ?? '');

    foreach ($_POST['additional'] as $product_id => $additional_value) {
        $product_id = (int)$product_id;
        $additional = (int)$additional_value;
        $ending = isset($_POST['ending'][$product_id]) ? (int)$_POST['ending'][$product_id] : 0;

        $query = "SELECT stock, price FROM product WHERE product_id = $product_id";
        $res = mysqli_query($db, $query);

        if ($row = mysqli_fetch_assoc($res)) {
            $stock_start = (int)$row['stock']; // Fix: Define stock_start
            $total = $stock_start + $additional;
            $sold = $total - $ending;
            $total_expenses = $sold * $row['price'];

            // Log to stock_log
            $logQuery = "INSERT INTO stock_log 
                (product_id, date, stock_start, additional, total, ending, sold, total_expenses, classification)
                VALUES 
                ($product_id, '$date', $stock_start, $additional, $total, $ending, $sold, $total_expenses, '$classification')";
            mysqli_query($db, $logQuery);

            // Update product current stock
            $updateProduct = "UPDATE product SET stock = $ending WHERE product_id = $product_id";
            mysqli_query($db, $updateProduct);
        }
    }

    $_SESSION['update_message'] = "Stock logged successfully!";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_GET['log_date']) && $_GET['log_date']) {
    $logDate = mysqli_real_escape_string($db, $_GET['log_date']);
    $logSql = "SELECT sl.*, p.`desc`, p.price, p.stock
            FROM stock_log sl
            JOIN product p ON sl.product_id = p.product_id
            WHERE sl.date = '$logDate'
            ORDER BY p.desc";
    $logResult = mysqli_query($db, $logSql); // Use separate variable to avoid conflict
}

$activeTab = $_GET['tab'] ?? 'stock-entry';

if ($activeTab === 'stock-log' && isset($_GET['log_date']) && $_GET['log_date']) {
    $logDate = mysqli_real_escape_string($db, $_GET['log_date']);
    $logSql = "SELECT sl.*, p.`desc`, p.price, p.stock
            FROM stock_log sl
            JOIN product p ON sl.product_id = p.product_id
            WHERE sl.date = '$logDate'";

    if (!empty($search)) {
        $logSql .= " AND (p.`desc` LIKE '%$searchEscaped%' OR sl.stock_start LIKE '%$searchEscaped%' OR sl.total_expenses LIKE '%$searchEscaped%')";
    }

    if ($sort === 'az') {
        $logSql .= " ORDER BY p.`desc` ASC";
    } elseif ($sort === 'za') {
        $logSql .= " ORDER BY p.`desc` DESC";
    }

    $logResult = mysqli_query($db, $logSql);
} else {
    $sql = "SELECT * FROM product";

    $conditions = [];

    if (!empty($search)) {
        $conditions[] = "(`desc` LIKE '%$searchEscaped%' OR stock LIKE '%$searchEscaped%' OR total_expenses LIKE '%$searchEscaped%')";
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    if ($sort === 'az') {
        $sql .= " ORDER BY `desc` ASC";
    } elseif ($sort === 'za') {
        $sql .= " ORDER BY `desc` DESC";
    }

    $result = mysqli_query($db, $sql);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stock List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script>
function updateSort(sortValue) {
    const url = new URL(window.location.href);
    const currentSearch = url.searchParams.get('search');
    const currentTab = url.searchParams.get('tab') || 'stock-entry';

    url.searchParams.set('sort', sortValue);
    if (currentSearch) url.searchParams.set('search', currentSearch);
    url.searchParams.set('tab', currentTab);

    window.location.href = url.toString();
}


        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('dropdownToggle');
            const menu = document.getElementById('dropdownMenu');

            toggle.addEventListener('click', () => {
                menu.classList.toggle('hidden');
            });

            document.addEventListener('click', function(event) {
                if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        });

        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('dropdownToggleLoc');
            const menu = document.getElementById('dropdownLoc');

            toggle.addEventListener('click', () => {
                menu.classList.toggle('hidden');
            });

            document.addEventListener('click', function(event) {
                if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        });

        function clearStockInputs() {
            const form = document.getElementById('stockForm');
            ['additional', 'ending'].forEach(field => {
                const inputs = form.querySelectorAll(`input[name^="${field}"]`);
                inputs.forEach(input => input.value = '0');
            });
            const totalInputs = form.querySelectorAll('input[name^="total"]');
            totalInputs.forEach(input => input.value = '');
            const soldInputs = form.querySelectorAll('input[readonly]:not([name^="total"]):not([name^="total_expenses"])');
            soldInputs.forEach(input => input.value = '');
            const expenseInputs = form.querySelectorAll('input[name^="total_expenses"]');
            expenseInputs.forEach(input => input.value = '');
            const stockDateInput = form.querySelector('input[name="stock_date"]');
            if (stockDateInput) stockDateInput.value = '';
            const classificationSelect = form.querySelector('select[name="classification"]');
            if (classificationSelect) classificationSelect.value = '';
        }
    </script>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-5 lg:ml-[250px]">
    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4 mt-10 w-full">

        <div class="flex items-center space-x-2">
            <!-- Sort Dropdown -->
            <div class="relative inline-block">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($_GET['tab'] ?? 'stock-entry'); ?>">

                <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                    Sort by Name
                    <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                    </svg>
                </button>

                <div id="dropdownMenu" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                    <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggle">
                        <li>
                            <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                <input id="filter-radio-az" type="radio" value="az" name="filter-radio" <?php if ($sort === 'az') echo 'checked'; ?> onclick="updateSort('az')" class="w-4 h-4">
                                <label for="filter-radio-az" class="ms-2 text-sm font-medium">A – Z</label>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                <input id="filter-radio-za" type="radio" value="za" name="filter-radio" <?php if ($sort === 'za') echo 'checked'; ?> onclick="updateSort('za')" class="w-4 h-4">
                                <label for="filter-radio-za" class="ms-2 text-sm font-medium">Z – A</label>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="relative justify-end">
            <form method="GET" class="flex space-x-2 items-center">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($_GET['tab'] ?? 'stock-entry'); ?>">

                <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." />
                <a href="stock_in_add.php" class="inline-flex bg-gray-900 text-white font-lg rounded-lg text-md px-3 py-1.5 hover:bg-gray-700">+</a>
            </form>
        </div>

        <div class="w-full flex justify-start mb-4 border-b border-gray-300">
    <button class="tab-button px-4 py-2 font-semibold text-gray-700 hover:text-black border-b-2 border-transparent hover:border-gray-700 active-tab" data-tab="stock-entry">Stock Entry</button>
    <button class="tab-button px-4 py-2 font-semibold text-gray-700 hover:text-black border-b-2 border-transparent hover:border-gray-700" data-tab="stock-log">Stock Log</button>
</div>
<div id="stock-entry" class="tab-content block">
        <form method="POST" action="" id="stockForm">
            <div class="flex justify-between">

          
            <div class="flex items-center space-x-2">
    <input type="date" name="stock_date" value="<?php echo htmlspecialchars($_POST['stock_date'] ?? date('Y-m-d')); ?>" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-4 py-2 w-48" required>
    <select name="classification" class="w-32 border p-2 rounded" required>
        <option value="" disabled <?php echo empty($_POST['classification']) ? 'selected' : ''; ?>>Classification</option>
        <option value="Direct Materials - Bakery" <?php echo ($_POST['classification'] ?? '') === 'Direct Materials - Bakery' ? 'selected' : ''; ?>>Direct Materials - Bakery</option>
        <option value="Direct Materials - Beverage" <?php echo ($_POST['classification'] ?? '') === 'Direct Materials - Beverage' ? 'selected' : ''; ?>>Direct Materials - Beverage</option>
        <option value="Direct Materials - Kitchen" <?php echo ($_POST['classification'] ?? '') === 'Direct Materials - Kitchen' ? 'selected' : ''; ?>>Direct Materials - Kitchen</option>
        <option value="Supplies & Packaging - Bakery" <?php echo ($_POST['classification'] ?? '') === 'Supplies & Packaging - Bakery' ? 'selected' : ''; ?>>Supplies & Packaging - Bakery</option>
        <option value="Supplies & Packaging - Beverage" <?php echo ($_POST['classification'] ?? '') === 'Supplies & Packaging - Beverage' ? 'selected' : ''; ?>>Supplies & Packaging - Beverage</option>
        <option value="Supplies & Packaging - Kitchen" <?php echo ($_POST['classification'] ?? '') === 'Supplies & Packaging - Kitchen' ? 'selected' : ''; ?>>Supplies & Packaging - Kitchen</option>
        <option value="Cleaning Materials" <?php echo ($_POST['classification'] ?? '') === 'Cleaning Materials' ? 'selected' : ''; ?>>Cleaning Materials</option>
        <option value="Office Supplies" <?php echo ($_POST['classification'] ?? '') === 'Office Supplies' ? 'selected' : ''; ?>>Office Supplies</option>
    </select>
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
                            <th class="px-4 py-2">Item</th>
                            <th class="px-4 py-2">Start</th>
                            <th class="px-4 py-2">Additional</th>
                            <th class="px-4 py-2">Total</th>
                            <th class="px-4 py-2">Ending</th>
                            <th class="px-4 py-2">Sold</th>
                            <th class="px-4 py-2">Total Expenses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        if (mysqli_num_rows($result) > 0):
                            mysqli_data_seek($result, 0); // Reset result pointer
                            while ($row = mysqli_fetch_assoc($result)) : ?>
                                <tr class="bg-white text-black text-center border-b">
                                    <td class="px-4 py-2"><?php echo $counter++; ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['desc']); ?></td>
                                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['stock']); ?></td>
                                    <td class="px-4 py-2">
                                        <input type="number" name="additional[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" name="total[<?php echo $row['product_id']; ?>]" value="" readonly class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" name="ending[<?php echo $row['product_id']; ?>]" value="0" class="text-center border border-gray-300 rounded px-2 py-1 w-full" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" readonly value="" class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" readonly name="total_expenses[<?php echo $row['product_id']; ?>]" value="" class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center px-4 py-4 text-gray-500 bg-white">
                                    No records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
        </div>



        <div id="stock-log" class="tab-content hidden">
    <?php if (isset($_GET['log_date']) && $_GET['log_date'] && isset($logResult)): ?>
        <h3 class="text-lg font-bold mb-4">Stock Log for <?php echo htmlspecialchars($_GET['log_date']); ?></h3>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-4">
    <form method="GET" class="flex items-center space-x-2">
        <div class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5">
            <input type="date" id="log_date" name="log_date" class="bg-transparent focus:outline-none" value="<?php echo htmlspecialchars($_GET['log_date'] ?? ''); ?>">
        </div>
        <input type="hidden" name="tab" value="stock-log">
        <button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-gray-700">View</button>
    </form>

    <?php if (isset($logResult) && mysqli_num_rows($logResult) > 0): ?>
        <form method="POST">
            <button type="submit" name="update_log_all" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm">
                Update All Logs
            </button>
        </form>
    <?php endif; ?>
</div>



    <?php if (isset($_GET['log_date']) && $_GET['log_date'] && isset($logResult)): ?>
        <table class="w-full text-sm text-left rtl:text-right text-gray-500">  <table class="w-full text-sm text-left rtl:text-right text-gray-500">
            <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                <tr>
                    <th class="px-4 py-2">No</th>
                    <th class="px-4 py-2">Item</th>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Start</th>
                    <th class="px-4 py-2">Additional</th>
                    <th class="px-4 py-2">Total</th>
                    <th class="px-4 py-2">Ending</th>
                    <th class="px-4 py-2">Sold</th>
                    <th class="px-4 py-2">Total Expenses</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $logCounter = 1;
                if (mysqli_num_rows($logResult) > 0):
                    while ($row = mysqli_fetch_assoc($logResult)): ?>
<tr class="bg-white text-black text-center border-b">
<form method="POST" action="">
    <tbody>
        <?php 
        $logCounter = 1;
        if (mysqli_num_rows($logResult) > 0):
            while ($row = mysqli_fetch_assoc($logResult)): ?>
                <tr class="bg-white text-black text-center border-b">
                    <input type="hidden" name="log_id[<?php echo $row['log_id']; ?>]" value="<?php echo $row['log_id']; ?>">
                    <td class="px-4 py-2"><?php echo $logCounter++; ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['desc']); ?></td>
                    <td class="px-4 py-2"><?php echo htmlspecialchars($row['date']); ?></td>
                    <td class="px-4 py-2">
                        <input type="number" name="stock_start[<?php echo $row['log_id']; ?>]" value="<?php echo $row['stock_start']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="additional[<?php echo $row['log_id']; ?>]" value="<?php echo $row['additional']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="total[<?php echo $row['log_id']; ?>]" value="<?php echo $row['total']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="ending[<?php echo $row['log_id']; ?>]" value="<?php echo $row['ending']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="sold[<?php echo $row['log_id']; ?>]" value="<?php echo $row['sold']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="total_expenses[<?php echo $row['log_id']; ?>]" value="<?php echo $row['total_expenses']; ?>" class="text-center border border-gray-300 rounded px-2 py-1 w-full bg-gray-100" />
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="9" class="text-center px-4 py-4 text-gray-500 bg-white">
                    No stock log records found for this date.
                </td>
            </tr>
        <?php endif; ?>
    </tbody>

</form>

</tr>

                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center px-4 py-4 text-gray-500 bg-white">
                            No stock log records found for this date.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="text-gray-600 text-center py-8">
            Please select a date above and click "View" to load the stock log.
        </div>
    <?php endif; ?>
</div>


<?php $db->close(); ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');

    function activateTab(tabId) {
        tabButtons.forEach(btn => {
            const isActive = btn.getAttribute('data-tab') === tabId;
            btn.classList.toggle('border-gray-700', isActive);
            btn.classList.toggle('text-black', isActive);
            btn.classList.toggle('active-tab', isActive);
        });

        tabContents.forEach(content => {
            content.classList.toggle('hidden', content.id !== tabId);
        });
    }

    // Click event for tabs
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            activateTab(tabId);
        });
    });

    // Activate based on URL param
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab') || 'stock-entry'; // Default to entry
    activateTab(tab);
});


</script>

</body>
</html>