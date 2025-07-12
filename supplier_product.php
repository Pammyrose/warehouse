<?php

include "connect.php";

// Check database connection
if (!$db || $db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

// Handle the deletion of a product
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    
    // Start a transaction to ensure atomicity
    $db->begin_transaction();
    try {
        // Delete related stock_log records first
        $delete_stock_log_query = "DELETE FROM stock_log WHERE product_id = '$delete_id'";
        if (!mysqli_query($db, $delete_stock_log_query)) {
            throw new Exception("Failed to delete related stock log records: " . mysqli_error($db));
        }

        // Delete the product record
        $delete_product_query = "DELETE FROM product WHERE product_id = '$delete_id'";
        if (!mysqli_query($db, $delete_product_query)) {
            throw new Exception("Failed to delete product: " . mysqli_error($db));
        }

        // Commit the transaction
        $db->commit();
        
        // No success message set for delete
        $params = $_GET;
        unset($params['delete_id']);
        $queryString = http_build_query($params);
        header("Location: " . $_SERVER['PHP_SELF'] . '?' . $queryString);
        exit;
    } catch (Exception $e) {
        // Rollback the transaction on error
        $db->rollback();
        $_SESSION['update_message'] = $e->getMessage();
        $params = $_GET;
        unset($params['delete_id']);
        $queryString = http_build_query($params);
        header("Location: " . $_SERVER['PHP_SELF'] . '?' . $queryString);
        exit;
    }
}

// Handle form submission from supplier_product_add.php or supplier_product_view.php modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['subclass'], $_POST['desc'], $_POST['price'], $_POST['uom'], $_POST['stock'], $_POST['supplier_id'])) {
    $subclass = mysqli_real_escape_string($db, $_POST['subclass']);
    $desc = mysqli_real_escape_string($db, $_POST['desc']);
    $price = floatval($_POST['price']);
    $uom = mysqli_real_escape_string($db, $_POST['uom']);
    $stock = intval($_POST['stock']);
    $supplier_id = intval($_POST['supplier_id']);
    $product_id = !empty($_POST['product_id']) ? mysqli_real_escape_string($db, $_POST['product_id']) : '';

    if (!empty($product_id)) {
        // Update existing product
        $stmt = $db->prepare("UPDATE product SET subclass=?, `desc`=?, price=?, uom=?, stock=?, supplier_id=? WHERE product_id=?");
        if (!$stmt) {
            $_SESSION['update_message'] = "Prepare failed: " . $db->error;
        } else {
            $stmt->bind_param("ssdsdii", $subclass, $desc, $price, $uom, $stock, $supplier_id, $product_id);
            if ($stmt->execute()) {
                $_SESSION['update_message'] = "Product updated successfully.";
            } else {
                $_SESSION['update_message'] = "Failed to update product: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        // Insert new product
        $stmt = $db->prepare("INSERT INTO product (subclass, `desc`, price, uom, stock, supplier_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $_SESSION['update_message'] = "Prepare failed: " . $db->error;
        } else {
            $stmt->bind_param("ssdsdi", $subclass, $desc, $price, $uom, $stock, $supplier_id);
            if ($stmt->execute()) {
                $_SESSION['update_message'] = "Product added successfully.";
            } else {
                $_SESSION['update_message'] = "Failed to add product: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?id=$supplier_id");
    exit();
}

// Get and validate supplier_id
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($supplier_id <= 0) {
    echo "Invalid supplier ID.";
    exit;
}

// Fetch supplier name
$supplier_result = mysqli_query($db, "SELECT name FROM supplier WHERE supplier_id = $supplier_id");
if (!$supplier_result) {
    die("Error fetching supplier: " . mysqli_error($db));
}
$supplier_data = mysqli_fetch_assoc($supplier_result);
$supplier_name = $supplier_data['name'] ?? 'Unknown Supplier';

// Handle search and sort
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$searchEscaped = mysqli_real_escape_string($db, $search);

// Build the query for product list
$sql = "SELECT * FROM product WHERE supplier_id = $supplier_id";
if (!empty($search)) {
    // Split search terms by comma and trim whitespace
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $conditions = [];
    foreach ($searchTerms as $term) {
        if (!empty($term)) {
            $termEscaped = mysqli_real_escape_string($db, $term);
            $conditions[] = "(`desc` LIKE '%$termEscaped%' OR subclass LIKE '%$termEscaped%' OR CAST(price AS CHAR) LIKE '%$termEscaped%' OR uom LIKE '%$termEscaped%' OR CAST(stock AS CHAR) LIKE '%$termEscaped%')";
        }
    }
    if (!empty($conditions)) {
        $sql .= " AND " . implode(" AND ", $conditions);
    }
}
// Sorting
if ($sort === 'az') {
    $sql .= " ORDER BY subclass ASC";
} elseif ($sort === 'za') {
    $sql .= " ORDER BY subclass DESC";
}

$result = mysqli_query($db, $sql);
if (!$result) {
    die('Error executing product query: ' . mysqli_error($db));
}

// Fetch suggestions for search
$suggestions_sql = "SELECT DISTINCT subclass, `desc`, price, uom, stock FROM product WHERE supplier_id = $supplier_id";
if (!empty($search)) {
    // Apply earlier terms (all except the last) to filter suggestions
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $priorTerms = array_slice($searchTerms, 0, -1); // Exclude last term
    $suggestionConditions = [];
    foreach ($priorTerms as $term) {
        if (!empty($term)) {
            $termEscaped = mysqli_real_escape_string($db, $term);
            $suggestionConditions[] = "(`desc` LIKE '%$termEscaped%' OR subclass LIKE '%$termEscaped%' OR CAST(price AS CHAR) LIKE '%$termEscaped%' OR uom LIKE '%$termEscaped%' OR CAST(stock AS CHAR) LIKE '%$termEscaped%')";
        }
    }
    if (!empty($suggestionConditions)) {
        $suggestions_sql .= " AND " . implode(" AND ", $suggestionConditions);
    }
}
$suggestions_result = mysqli_query($db, $suggestions_sql);
if (!$suggestions_result) {
    error_log("Suggestion query failed: " . mysqli_error($db));
    $suggestions = [];
} else {
    $suggestions = [];
    while ($row = mysqli_fetch_assoc($suggestions_result)) {
        if (!empty($row['subclass'])) $suggestions[] = $row['subclass'];
        if (!empty($row['desc'])) $suggestions[] = $row['desc'];
        if (!empty($row['price'])) $suggestions[] = number_format($row['price'], 2);
        if (!empty($row['uom'])) $suggestions[] = $row['uom'];
        if (!empty($row['stock'])) $suggestions[] = (string)$row['stock'];
    }
    $suggestions = array_unique($suggestions); // Remove duplicates
    usort($suggestions, 'strnatcasecmp'); // Sort case-insensitive
}
$suggestions_json = json_encode(array_values($suggestions)); // Convert to JSON for JavaScript

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Supplier Products</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .suggestions-container {
            position: absolute;
            z-index: 10;
            width: 320px; /* Matches input width-80 */
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
        }
        .suggestion-item:hover {
            background-color: #f3f4f6;
        }
        #addProductModal {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
        }
        #addProductModal.show {
            display: flex;
        }
    </style>
</head>
<body>



<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex flex-col items-start justify-start min-h-screen p-2 lg:ml-[250px]">
    <div class="w-full mt-5">
        <h1 class="text-2xl font-bold mb-4">Products from <?php echo htmlspecialchars($supplier_name); ?></h1>

        <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between">
            <!-- Sort dropdown and Back button -->
            <div class="relative inline-block">
                <a href="supplier.php" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">
                    <svg class="w-4 h-4 text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/>
                    </svg>
                </a>
                <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                    Sort By
                    <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
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

            <!-- Search and Add button -->
            <div class="relative justify-end">
                <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                    <svg class="w-5 h-5 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <form id="searchForm" method="GET" action="" class="flex space-x-2 items-center">
                    <input type="hidden" name="id" value="<?php echo $supplier_id; ?>">
                    <div class="relative">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." autocomplete="off" />
                        <div id="suggestions" class="suggestions-container hidden"></div>
                    </div>
                    <button type="button" id="addProductBtn" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</button>
                </form>
            </div>
        </div>

        <!-- Product Table -->
        <div class="w-full">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 mt-2">
                <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                    <tr>
                        <th scope="col" class="px-7 py-2" style="width: 50px;">No</th>
                        <th scope="col" class="px-7 py-2" style="width: 300px;">Subclass</th>
                        <th scope="col" class="px-7 py-2" style="width: 300px;">Description</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">Price</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">UOM</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">Stock</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr class="bg-white text-black text-center border-b">
                                <td class="px-7 py-4"><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($row['subclass']); ?></td>
                                <td><?php echo htmlspecialchars($row['desc']); ?></td>
                                <td>₱<?php echo number_format($row['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($row['uom']); ?></td>
                                <td><?php echo htmlspecialchars($row['stock']); ?></td>
                                <td>
                                    <button type="button" class="editProductBtn font-medium text-yellow-500 hover:underline mr-3" data-id="<?php echo $row['product_id']; ?>">Edit</button>
                                    <a href="?id=<?php echo $supplier_id; ?>&delete_id=<?php echo $row['product_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this product and its related stock logs?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-gray-500">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Product Modal (for both Add and Edit) -->
    <div id="addProductModal">
        <div id="modalContent" class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative"></div>
    </div>
</div>

<script>
    const suggestions = <?php echo $suggestions_json; ?>;

    function updateSort(value) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', value);
        if (!url.searchParams.get('id')) {
            url.searchParams.set('id', <?php echo $supplier_id; ?>);
        }
        const searchInput = document.getElementById('table-search');
        if (searchInput && searchInput.value) {
            url.searchParams.set('search', searchInput.value);
        }
        window.location.href = url.toString();
    }

    function loadModalContent(url, isEdit = false, productId = '') {
        const addProductModal = document.getElementById('addProductModal');
        const modalContent = document.getElementById('modalContent');
        
        fetch(url, {
            headers: { 'Accept': 'text/html' }
        })
            .then(response => {
                if (!response.ok) throw new Error(`Failed to load form: ${response.statusText}`);
                return response.text();
            })
            .then(data => {
                // Parse the HTML and extract the modal content
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const modalDiv = doc.querySelector('.rounded-lg');
                if (modalDiv) {
                    modalContent.innerHTML = modalDiv.innerHTML;
                    // Update form action to point to supplier_product.php
                    const form = modalContent.querySelector('form');
                    if (form) {
                        form.action = 'supplier_product.php';
                        // Ensure product_id is set correctly for edit/add
                        const productIdInput = form.querySelector('input[name="product_id"]');
                        if (productIdInput && !isEdit) productIdInput.value = '';
                        // Handle form submission via AJAX
                        form.addEventListener('submit', (e) => {
                            e.preventDefault();
                            const formData = new FormData(form);
                            fetch('supplier_product.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (!response.ok) throw new Error('Failed to process product');
                                return response.text();
                            })
                            .then(() => {
                                addProductModal.classList.remove('show');
                                location.reload();
                            })
                            .catch(error => {
                                console.error('Form submission error:', error);
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                                errorDiv.textContent = 'Failed to process product: ' + error.message;
                                modalContent.prepend(errorDiv);
                            });
                        });
                    }
                    // Add event listeners for close and cancel buttons
                    const closeBtn = modalContent.querySelector('button[aria-label="Close modal"]');
                    const cancelBtn = modalContent.querySelector('button[type="button"]');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => {
                            addProductModal.classList.remove('show');
                        });
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', () => {
                            addProductModal.classList.remove('show');
                        });
                    }
                    addProductModal.classList.add('show');
                } else {
                    throw new Error('Modal content not found');
                }
            })
            .catch(error => {
                console.error('Error loading modal content:', error);
                modalContent.innerHTML = '<p class="text-red-700">Failed to load form: ' + error.message + '</p>';
                addProductModal.classList.add('show');
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('dropdownToggle');
        const menu = document.getElementById('dropdownMenu');
        const searchInput = document.getElementById('table-search');
        const suggestionsContainer = document.getElementById('suggestions');
        const addProductBtn = document.getElementById('addProductBtn');
        const addProductModal = document.getElementById('addProductModal');
        const editProductButtons = document.querySelectorAll('.editProductBtn');

        // Dropdown toggle for sorting
        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Load supplier_product_add.php for add button
        addProductBtn.addEventListener('click', () => {
            loadModalContent('supplier_product_add.php?supplier_id=<?php echo $supplier_id; ?>');
        });

        // Load supplier_product_view.php for edit buttons
        editProductButtons.forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-id');
                loadModalContent(`supplier_product_view.php?id=${productId}`, true, productId);
            });
        });

        // Hide dropdown, suggestions, and modal when clicking outside
        document.addEventListener('click', function(event) {
            if (!toggle.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.add('hidden');
            }
            if (!searchInput.contains(event.target) && !suggestionsContainer.contains(event.target)) {
                suggestionsContainer.classList.add('hidden');
            }
            if (!document.getElementById('modalContent').contains(event.target) && !addProductBtn.contains(event.target) && !Array.from(editProductButtons).some(btn => btn.contains(event.target)) && addProductModal.classList.contains('show')) {
                addProductModal.classList.remove('show');
            }
        });

        // Search suggestions
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            suggestionsContainer.innerHTML = '';
            
            if (query.length < 2) {
                suggestionsContainer.classList.add('hidden');
                return;
            }

            // Split terms and handle edge cases (e.g., multiple commas, trailing commas)
            const terms = query.split(',').map(term => term.trim()).filter(term => term !== '');
            if (terms.length === 0) {
                suggestionsContainer.classList.add('hidden');
                return;
            }
            const lastTerm = terms[terms.length - 1].toLowerCase();

            if (lastTerm.length < 2) {
                suggestionsContainer.classList.add('hidden');
                return;
            }

            const filteredSuggestions = suggestions
                .filter(item => {
                    console.log('Checking suggestion:', item, 'against:', lastTerm);
                    return item.toLowerCase().includes(lastTerm);
                })
                .slice(0, 5); // Limit to 5 suggestions

            console.log('Filtered suggestions:', filteredSuggestions);

            if (filteredSuggestions.length > 0) {
                filteredSuggestions.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = item;
                    div.addEventListener('click', () => {
                        // Replace the last term with the selected suggestion
                        terms[terms.length - 1] = item;
                        searchInput.value = terms.join(', ');
                        suggestionsContainer.classList.add('hidden');
                        searchInput.form.submit();
                    });
                    suggestionsContainer.appendChild(div);
                });
                suggestionsContainer.classList.remove('hidden');
            } else {
                suggestionsContainer.classList.add('hidden');
            }
        });

        // Close modal on Esc key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && addProductModal.classList.contains('show')) {
                addProductModal.classList.remove('show');
            }
        });
    });
</script>

</body>
</html>