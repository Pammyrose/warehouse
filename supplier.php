<?php

include "connect.php";

// Check database connection
if (!$db || $db->connect_error) {
    die("Database connection failed: " . $db->connect_error);
}

// Handle form submission from supplier_add.php or supplier_view.php modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name'], $_POST['classification'])) {
    $name = mysqli_real_escape_string($db, $_POST['name']);
    $classification = mysqli_real_escape_string($db, $_POST['classification']);
    $supplier_id = !empty($_POST['supplier_id']) ? mysqli_real_escape_string($db, $_POST['supplier_id']) : '';

    if (!empty($supplier_id)) {
        // Update
        $stmt = $db->prepare("UPDATE supplier SET name=?, classification=? WHERE supplier_id=?");
        $stmt->bind_param("ssi", $name, $classification, $supplier_id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO supplier (name, classification) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $classification);
    }

    if ($stmt->execute()) {
        $_SESSION['update_message'] = !empty($supplier_id) ? "Supplier updated successfully." : "Supplier added successfully.";
        $stmt->close();
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['update_message'] = "Failed to " . (!empty($supplier_id) ? "update" : "add") . " supplier: " . $stmt->error;
        $stmt->close();
    }
}

// Handle deletion of a supplier
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    
    // Start a transaction
    $db->begin_transaction();
    try {
        // Fetch all product IDs associated with the supplier
        $product_query = "SELECT product_id FROM product WHERE supplier_id = '$delete_id'";
        $product_result = mysqli_query($db, $product_query);
        if (!$product_result) {
            throw new Exception("Failed to fetch products: " . mysqli_error($db));
        }

        // Delete stock_log records for each product
        while ($product_row = mysqli_fetch_assoc($product_result)) {
            $product_id = $product_row['product_id'];
            $delete_stock_log_query = "DELETE FROM stock_log WHERE product_id = '$product_id'";
            if (!mysqli_query($db, $delete_stock_log_query)) {
                throw new Exception("Failed to delete stock log records for product ID $product_id: " . mysqli_error($db));
            }
        }

        // Delete all products for the supplier
        $delete_products_query = "DELETE FROM product WHERE supplier_id = '$delete_id'";
        if (!mysqli_query($db, $delete_products_query)) {
            throw new Exception("Failed to delete products: " . mysqli_error($db));
        }

        // Delete the supplier
        $delete_supplier_query = "DELETE FROM supplier WHERE supplier_id = '$delete_id'";
        if (!mysqli_query($db, $delete_supplier_query)) {
            throw new Exception("Failed to delete supplier: " . mysqli_error($db));
        }

        // Commit the transaction
        $db->commit();
        $_SESSION['update_message'] = "Supplier and related products deleted successfully.";
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

// Base SQL for fetching suppliers (for table display)
$sql = "SELECT supplier_id, name, classification FROM supplier";
$conditions = [];

if (!empty($search)) {
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $lastTerm = end($searchTerms);
    $lastTermEscaped = mysqli_real_escape_string($db, $lastTerm);
    $conditions[] = "(LOWER(name) LIKE LOWER('%$lastTermEscaped%') OR LOWER(classification) LIKE LOWER('%$lastTermEscaped%'))";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sorting
if ($sort === 'az') {
    $sql .= " ORDER BY name ASC";
} elseif ($sort === 'za') {
    $sql .= " ORDER BY name DESC";
}

// Execute the query for table
$result = mysqli_query($db, $sql);
if (!$result) {
    die('Error executing the query: ' . mysqli_error($db));
}

// Fetch suggestions for search (adapted from provided code)
$suggestions_sql = "SELECT DISTINCT name, classification FROM supplier";
if (!empty($search)) {
    // Apply earlier terms (all except the last) to filter suggestions
    $searchTerms = array_filter(array_map('trim', explode(',', $search)));
    $priorTerms = array_slice($searchTerms, 0, -1); // Exclude last term
    $suggestionConditions = [];
    foreach ($priorTerms as $term) {
        if (!empty($term)) {
            $termEscaped = mysqli_real_escape_string($db, $term);
            $suggestionConditions[] = "(LOWER(name) LIKE LOWER('%$termEscaped%') OR LOWER(classification) LIKE LOWER('%$termEscaped%'))";
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
        if (!empty($row['name'])) $suggestions[] = $row['name'];
        if (!empty($row['classification']) && $row['classification'] !== $row['name']) $suggestions[] = $row['classification'];
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
    <title>Supplier List</title>
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
        #addSupplierModal {
            position: fixed;
            inset: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
            justify-content: center;
            align-items: center;
        }
        #addSupplierModal.show {
            display: flex;
        }
        .glow-text {
            position: relative;
            color: #ffffff;
            text-shadow: 
                0 0 5px #3b82f6,
                0 0 10px #3b82f6,
                0 0 15px #60a5fa;
            transition: text-shadow 0.3s ease-in-out, transform 0.3s ease-in-out;
        }
        .glow-text:hover {
            text-shadow: 
                0 0 8px #3b82f6,
                0 0 15px #3b82f6,
                0 0 20px #60a5fa;
        }
    </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex flex-col items-start justify-start min-h-screen p-4 lg:ml-[250px]">
    <h1 class="text-2xl font-bold mb-4">Suppliers</h1>

    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4">
        <div class="relative inline-block">
            <button-squared-button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                Sort by Name
                <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/></svg>
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

        <label for="table-search" class="sr-only">Search</label>
        <div class="relative w-full sm:w-80">
            <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <form method="GET" class="flex space-x-2 items-center">
                <div class="relative w-full">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-full focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." autocomplete="off" />
                    <div id="suggestions" class="suggestions-container hidden"></div>
                </div>
                <button type="button" id="addSupplierBtn" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</button>
            </form>
        </div>

        <div class="w-full">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 mt-2">
                <thead class="bg-gray-900 text-xs text-white uppercase text-center">
                    <tr>
                        <th scope="col" class="px-7 py-2" style="width: 50px;">No</th>
                        <th scope="col" class="px-7 py-2" style="width: 300px;">Name</th>
                        <th scope="col" class="px-7 py-2" style="width: 300px;">Classification</th>
                        <th scope="col" class="px-7 sm:px-7 py-2 sm:py-3" style="width: 150px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    if (mysqli_num_rows($result) > 0):
                        while ($row = mysqli_fetch_assoc($result)) : ?>
                            <tr class="bg-white text-black text-center border-b">
                                <th scope="row" class="px-7 py-4 font-medium whitespace-nowrap"><?php echo number_format($counter++, 0, '.', ','); ?></th>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['classification']); ?></td>
                                <td>
                                    <a href="supplier_product.php?id=<?php echo $row['supplier_id']; ?>" class="font-medium text-blue-500 hover:underline mr-3">View</a>
                                    <button type="button" class="editSupplierBtn font-medium text-yellow-500 hover:underline mr-3" data-id="<?php echo $row['supplier_id']; ?>">Edit</button>
                                    <a href="?delete_id=<?php echo $row['supplier_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this supplier?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Supplier Modal (for both Add and Edit) -->
    <div id="addSupplierModal">
        <div id="modalContent" class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative"></div>
    </div>
</div>

<script>
    const suggestions = <?php echo $suggestions_json; ?>;
    let debounceTimeout;

    // Debug suggestions array
    console.log('Suggestions array:', suggestions);

    function updateSort(sortValue) {
        const url = new URL(window.location.href);
        const currentSearch = url.searchParams.get('search');
        url.searchParams.set('sort', sortValue);
        if (currentSearch) url.searchParams.set('search', currentSearch);
        window.location.href = url.toString();
    }

    function loadModalContent(url, isEdit = false, supplierId = '') {
        const addSupplierModal = document.getElementById('addSupplierModal');
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
                        form.action = 'supplier.php';
                        const supplierIdInput = form.querySelector('input[name="supplier_id"]');
                        if (supplierIdInput && !isEdit) supplierIdInput.value = '';
                        form.addEventListener('submit', (e) => {
                            e.preventDefault();
                            const formData = new FormData(form);
                            fetch('supplier.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => {
                                if (!response.ok) throw new Error('Failed to process supplier');
                                return response.text();
                            })
                            .then(() => {
                                addSupplierModal.classList.remove('show');
                                location.reload();
                            })
                            .catch(error => {
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4';
                                errorDiv.textContent = 'Failed to process supplier: ' + error.message;
                                modalContent.prepend(errorDiv);
                            });
                        });
                    }
                    const closeBtn = modalContent.querySelector('button[aria-label="Close modal"]');
                    const cancelBtn = modalContent.querySelector('button[type="button"]');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => {
                            addSupplierModal.classList.remove('show');
                        });
                    }
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', () => {
                            addSupplierModal.classList.remove('show');
                        });
                    }
                    addSupplierModal.classList.add('show');
                } else {
                    throw new Error('Modal content not found');
                }
            })
            .catch(error => {
                console.error('Error loading modal content:', error);
                modalContent.innerHTML = '<p class="text-red-700">Failed to load form: ' + error.message + '</p>';
                addSupplierModal.classList.add('show');
            });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('dropdownToggle');
        const menu = document.getElementById('dropdownMenu');
        const searchInput = document.getElementById('table-search');
        const suggestionsContainer = document.getElementById('suggestions');
        const addSupplierBtn = document.getElementById('addSupplierBtn');
        const addSupplierModal = document.getElementById('addSupplierModal');
        const editSupplierButtons = document.querySelectorAll('.editSupplierBtn');

        // Dropdown toggle for sorting
        toggle.addEventListener('click', () => {
            menu.classList.toggle('hidden');
        });

        // Load supplier_add.php for add button
        addSupplierBtn.addEventListener('click', () => {
            loadModalContent('supplier_add.php?modal=true');
        });

        // Load supplier_view.php for edit buttons
        editSupplierButtons.forEach(button => {
            button.addEventListener('click', () => {
                const supplierId = button.getAttribute('data-id');
                loadModalContent(`supplier_view.php?id=${supplierId}&modal=true`, true, supplierId);
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
            if (!document.getElementById('modalContent').contains(event.target) && !addSupplierBtn.contains(event.target) && !Array.from(editSupplierButtons).some(btn => btn.contains(event.target)) && addSupplierModal.classList.contains('show')) {
                addSupplierModal.classList.remove('show');
            }
        });

        // Search suggestions with debouncing
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                const query = this.value.trim().toLowerCase();
                console.log('Search query:', query); // Debug query
                suggestionsContainer.innerHTML = '';
                
                if (query.length < 1) {
                    suggestionsContainer.classList.add('hidden');
                    return;
                }

                // Filter suggestions based on the last term
                const searchTerms = query.split(',').map(term => term.trim()).filter(term => term);
                const lastTerm = searchTerms[searchTerms.length - 1] || '';
                console.log('Last term:', lastTerm); // Debug last term
                const filteredSuggestions = suggestions
                    .filter(item => item.toLowerCase().includes(lastTerm))
                    .slice(0, 10);

                console.log('Filtered suggestions:', filteredSuggestions); // Debug filtered suggestions

                if (filteredSuggestions.length > 0) {
                    filteredSuggestions.forEach(item => {
                        const div = document.createElement('div');
                        div.className = 'suggestion-item';
                        div.textContent = item;
                        div.addEventListener('click', () => {
                            // Append the selected suggestion to the prior terms
                            const priorTerms = searchTerms.slice(0, -1);
                            searchInput.value = [...priorTerms, item].join(', ');
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
            }, 300); // 300ms debounce
        });

        // Close modal on Esc key
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && addSupplierModal.classList.contains('show')) {
                addSupplierModal.classList.remove('show');
            }
        });
    });
</script>

</body>
</html>