<?php
include('login_session.php');
include "connect.php";

// Check database connection
if (!$db || $db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

// Handle product addition or update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subclass'])) {
    // Retrieve and sanitize form inputs
    $subclass = mysqli_real_escape_string($db, $_POST['subclass']);
    $desc = mysqli_real_escape_string($db, $_POST['desc']);
    $price = floatval($_POST['price']);
    $uom = mysqli_real_escape_string($db, $_POST['uom']);
    $stock = intval($_POST['stock']);
    $supplier_id = intval($_POST['supplier_id']);
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

    // Validate inputs
    if (empty($subclass) || empty($desc) || $price <= 0 || empty($uom) || $stock < 0 || $supplier_id <= 0) {
        $_SESSION['update_message'] = "Error: All fields are required and must be valid.";
        header("Location: product.php");
        exit;
    }

    if ($product_id > 0) {
        // Update existing product
        $stmt = $db->prepare("UPDATE product SET subclass = ?, `desc` = ?, price = ?, uom = ?, stock = ?, supplier_id = ? WHERE product_id = ?");
        $stmt->bind_param("ssdssii", $subclass, $desc, $price, $uom, $stock, $supplier_id, $product_id);

        if ($stmt->execute()) {
            $_SESSION['update_message'] = "Product updated successfully.";
        } else {
            $_SESSION['update_message'] = "Error updating product: " . $db->error;
        }
    } else {
        // Add new product
        $stmt = $db->prepare("INSERT INTO product (subclass, `desc`, price, uom, stock, supplier_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdssi", $subclass, $desc, $price, $uom, $stock, $supplier_id);

        if ($stmt->execute()) {
            $_SESSION['update_message'] = "Product added successfully.";
        } else {
            $_SESSION['update_message'] = "Error adding product: " . $db->error;
        }
    }

    $stmt->close();
    header("Location: product.php");
    exit;
}

// Handle deletion of a product
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    
    // Start a transaction
    $db->begin_transaction();
    try {
        // Delete stock_log records for the product
        $delete_stock_log_query = "DELETE FROM stock_log WHERE product_id = '$delete_id'";
        if (!mysqli_query($db, $delete_stock_log_query)) {
            throw new Exception("Failed to delete stock log records: " . mysqli_error($db));
        }

        // Delete the product
        $delete_product_query = "DELETE FROM product WHERE product_id = '$delete_id'";
        if (!mysqli_query($db, $delete_product_query)) {
            throw new Exception("Failed to delete product: " . mysqli_error($db));
        }

        // Commit the transaction
        $db->commit();
        $_SESSION['update_message'] = "Product and related stock logs deleted successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } catch (Exception $e) {
        // Rollback the transaction on error
        $db->rollback();
        $_SESSION['update_message'] = $e->getMessage();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Search and sort parameters
$sort = $_GET['sort'] ?? '';
$search = $_GET['search'] ?? '';
$searchEscaped = mysqli_real_escape_string($db, $search);

// Base SQL for fetching products
$sql = "SELECT product_id, subclass, `desc`, price, uom, stock FROM product";
$conditions = [];

if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $lastTerm = end($searchTerms);
    $lastTermEscaped = mysqli_real_escape_string($db, $lastTerm);
    $conditions[] = "(LOWER(subclass) LIKE LOWER('%$lastTermEscaped%') OR LOWER(`desc`) LIKE LOWER('%$lastTermEscaped%') OR LOWER(price) LIKE LOWER('%$lastTermEscaped%') OR LOWER(uom) LIKE LOWER('%$lastTermEscaped%') OR LOWER(stock) LIKE LOWER('%$lastTermEscaped%'))";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sorting by subclass
if ($sort === 'az') {
    $sql .= " ORDER BY subclass ASC";
} elseif ($sort === 'za') {
    $sql .= " ORDER BY subclass DESC";
}

// Execute the query for table
$result = mysqli_query($db, $sql);
if (!$result) {
    die('Error executing the query: ' . mysqli_error($db));
}

// Fetch suggestions for search
$suggestions_sql = "SELECT DISTINCT subclass, `desc`, price, uom, stock FROM product";
if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $priorTerms = array_slice($searchTerms, 0, -1);
    $suggestionConditions = [];
    foreach ($priorTerms as $term) {
        if (!empty($term)) {
            $termEscaped = mysqli_real_escape_string($db, $term);
            $suggestionConditions[] = "(LOWER(subclass) LIKE LOWER('%$termEscaped%') OR LOWER(`desc`) LIKE LOWER('%$termEscaped%') OR LOWER(price) LIKE LOWER('%$termEscaped%') OR LOWER(uom) LIKE LOWER('%$termEscaped%') OR LOWER(stock) LIKE LOWER('%$termEscaped%'))";
        }
    }
    if (!empty($suggestionConditions)) {
        $suggestions_sql .= " WHERE " . implode(" AND ", $suggestionConditions);
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
        if (!empty($row['desc']) && $row['desc'] !== $row['subclass']) $suggestions[] = $row['desc'];
        if (!empty($row['price'])) $suggestions[] = (string)$row['price'];
        if (!empty($row['uom']) && $row['uom'] !== $row['subclass'] && $row['uom'] !== $row['desc']) $suggestions[] = $row['uom'];
        if (!empty($row['stock'])) $suggestions[] = (string)$row['stock'];
    }
    $suggestions = array_unique($suggestions);
    usort($suggestions, 'strnatcasecmp');
}
$suggestions_json = json_encode(array_values($suggestions), JSON_HEX_QUOT | JSON_HEX_TAG);

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Product Management</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        .suggestions-container {
            position: absolute;
            z-index: 20;
            width: 100%;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            top: 100%;
            left: 0;
        }
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            color: #1f2937;
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

<div class="bg-white content-wrapper flex flex-col items-start justify-start min-h-screen p-4 lg:ml-[250px]">
    <h1 class="text-2xl font-bold mb-4">Products</h1>

    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4">
        <div class="relative inline-block">
            <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                Sort by Subclass
                <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                </svg>
            </button>
            <div id="dropdownMenu" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm top-full mt-1">
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

        <label for="table-search" class="sr-only">Search</label>
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <form id="searchForm" method="GET" class="flex space-x-2 items-center">
                <div class="relative w-full">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-full focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." autocomplete="off" />
                    <div id="suggestions" class="suggestions-container hidden"></div>
                </div>
                <button type="button" id="addProductBtn" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</button>
            </form>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 mt-2">
                <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                    <tr>
                        <th scope="col" class="px-7 py-2" style="width: 50px;">No</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">Subclass</th>
                        <th scope="col" class="px-7 py-2" style="width: 250px;">Description</th>
                        <th scope="col" class="px-7 py-2" style="width: 100px;">Price</th>
                        <th scope="col" class="px-7 py-2" style="width: 80px;">UOM</th>
                        <th scope="col" class="px-7 py-2" style="width: 80px;">Stock</th>
                        <th scope="col" class="px-7 py-2" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $counter = 1;
                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)) : ?>
                            <tr class="bg-white text-black text-center border-b">
                                <th scope="row" class="px-7 py-4 font-medium whitespace-nowrap"><?php echo number_format($counter++, 0, '.', ','); ?></th>
                                <td><?php echo htmlspecialchars($row['subclass']); ?></td>
                                <td><?php echo htmlspecialchars($row['desc']); ?></td>
                                <td>₱<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($row['uom']); ?></td>
                                <td><?php echo htmlspecialchars($row['stock']); ?></td>
                                <td>
                                    <button type="button" class="editProductBtn font-medium text-yellow-500 hover:underline mr-3" data-id="<?php echo $row['product_id']; ?>">Edit</button>
                                    <a href="?delete_id=<?php echo $row['product_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this product and its stock logs?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center py-4 text-gray-500">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div id="addProductModal">
        <div id="modalContent" class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative"></div>
    </div>
</div>

<script>
    const suggestions = <?php echo $suggestions_json; ?>;
    let debounceTimeout;

    console.log('Suggestions array:', suggestions);

    function updateSort(sortValue) {
        console.log('Sort value:', sortValue);
        const url = new URL(window.location.href);
        const currentSearch = url.searchParams.get('search');
        url.searchParams.set('sort', sortValue);
        if (currentSearch) url.searchParams.set('search', currentSearch);
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
                const parser = new DOMParser();
                const doc = parser.parseFromString(data, 'text/html');
                const modalDiv = doc.querySelector('.rounded-lg');
                if (modalDiv) {
                    modalContent.innerHTML = modalDiv.innerHTML;
                    const form = modalContent.querySelector('form');
                    if (form) {
                        form.action = 'product.php';
                        const productIdInput = form.querySelector('input[name="product_id"]');
                        if (productIdInput && !isEdit) productIdInput.value = '';
                        form.addEventListener('submit', (e) => {
                            e.preventDefault();
                            const formData = new FormData(form);
                            fetch('product.php', {
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
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                                errorDiv.textContent = 'Failed to process product: ' + error.message;
                                modalContent.prepend(errorDiv);
                            });
                        });
                    }
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
            console.log('Dropdown toggled:', menu.classList.contains('hidden') ? 'Hidden' : 'Visible');
        });

        // Load product_add.php for add button
        addProductBtn.addEventListener('click', () => {
            loadModalContent('product_add.php?modal=true');
        });

        // Load product_view.php for edit buttons
        editProductButtons.forEach(button => {
            button.addEventListener('click', () => {
                const productId = button.getAttribute('data-id');
                loadModalContent(`product_view.php?id=${productId}&modal=true`, true, productId);
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

        // Search suggestions with debouncing
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim();
                console.log('Search query:', query);
                suggestionsContainer.innerHTML = '';
                
                if (query.length < 1) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }

                const terms = query.split(',').map(term => term.trim()).filter(term => term !== '');
                if (terms.length === 0) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }
                const lastTerm = terms[terms.length - 1].toLowerCase();
                console.log('Last term:', lastTerm);

                const filteredSuggestions = suggestions
                    .filter(item => item.toLowerCase().includes(lastTerm))
                    .slice(0, 10);

                console.log('Filtered suggestions:', filteredSuggestions);

                if (filteredSuggestions.length > 0) {
                    filteredSuggestions.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = item;
                        div.addEventListener('click', () => {
                            terms[terms.length - 1] = item;
                            searchInput.value = terms.join(', ');
                            suggestionsContainer.classList.add('hidden');
                            searchInput.form.submit();
                        });
                        suggestionsContainer.appendChild(div);
                    });
                    suggestionsContainer.classList.remove('hidden');
                } else {
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    div.textContent = 'No suggestions found';
                    suggestionsContainer.appendChild(div);
                    suggestionsContainer.classList.remove('hidden');
                }
            }, 300);
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