<?php
include('login_session.php');
include "connect.php";

// Handle the deletion of a product
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    $delete_query = "DELETE FROM product WHERE product_id = '$delete_id'";
    
    if (mysqli_query($db, $delete_query)) {
        $_SESSION['update_message'] = "Record deleted successfully.";

        // Preserve other query params except delete_id
        $params = $_GET;
        unset($params['delete_id']);
        $queryString = http_build_query($params);

        header("Location: " . $_SERVER['PHP_SELF'] . '?' . $queryString);
        exit;
    } else {
        $_SESSION['update_message'] = "Failed to delete record.";
    }
}

// Get and validate supplier_id
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($supplier_id <= 0) {
    echo "Invalid supplier ID.";
    exit;
}

// Fetch supplier name
$supplier_result = mysqli_query($db, "SELECT name FROM supplier WHERE supplier_id = $supplier_id");
$supplier_data = mysqli_fetch_assoc($supplier_result);
$supplier_name = $supplier_data['name'] ?? 'Unknown Supplier';

// Handle search and sort
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? '';
$searchEscaped = mysqli_real_escape_string($db, $search);

// Build the query
$sql = "SELECT * FROM product WHERE supplier_id = $supplier_id";
if (!empty($search)) {
    $sql .= " AND `desc` LIKE '%$searchEscaped%' OR subclass LIKE '%$searchEscaped%' OR price LIKE '%$searchEscaped%' OR uom LIKE '%$searchEscaped%' OR stock LIKE '%$searchEscaped%'";
}
if ($sort === 'category') {
    $sql .= " ORDER BY subclass ASC";
} elseif ($sort === 'price') {
    $sql .= " ORDER BY price ASC";
}
$result = mysqli_query($db, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Supplier Products</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script>
        function updateSort(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', value);
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
    </script>

</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex flex-col items-start justify-start min-h-screen p-2 lg:ml-[250px]">
    <div class="w-full mt-5">
        <h1 class="text-2xl font-bold mb-4">Products from <?php echo htmlspecialchars($supplier_name); ?></h1>


        <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between">
            <!-- Sort dropdown -->
            <div class="relative inline-block">
            <a href="supplier.php" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">
            <svg class="w-4 h-4 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"  fill="none" viewBox="0 0 24 24">
  <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 19-7-7 7-7"/>
</svg>

</a>
           
                <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                    Sort By
                    <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                    </svg>
                </button>
                <div id="dropdownMenu" class="absolute z-10 hidden w-32 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                    <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggle">
                        <li>
                            <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                <input id="sort-category" type="radio" value="category" name="sort-radio" <?php if ($sort === 'category') echo 'checked'; ?> onclick="updateSort('category')" class="w-4 h-4">
                                <label for="sort-category" class="ms-2 text-sm font-medium">Subclass</label>
                            </div>
                        </li>
                        <li>
                            <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                                <input id="sort-price" type="radio" value="price" name="sort-radio" <?php if ($sort === 'price') echo 'checked'; ?> onclick="updateSort('price')" class="w-4 h-4">
                                <label for="sort-price" class="ms-2 text-sm font-medium">Price</label>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <label for="table-search" class="sr-only">Search</label>
        <div class="relative justify-end">
            <div class="absolute inset-y-0 left-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-500" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path>
                </svg>
            </div>
            <form id="searchForm" method="GET" action="" class="flex space-x-2 items-center">
            <input type="hidden" name="id" value="<?= $supplier_id ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." />
                <a href="supplier_product_add.php?supplier_id=<?php echo $supplier_id; ?>" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</a>
            </form>
        </div>
        </div>

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
                                <td><?php echo htmlspecialchars($row['stock'], 2); ?></td>
                                <td>
                                    <a href="supplier_product_view.php?id=<?php echo $row['product_id']; ?>" class="font-medium text-yellow-500 hover:underline mr-3">Edit</a>
                                    <a href="?id=<?php echo $supplier_id; ?>&delete_id=<?php echo $row['product_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
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
</div>
<script>
function updateSort(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', value);

    // Ensure supplier id stays in the URL
    if (!url.searchParams.get('id')) {
        url.searchParams.set('id', <?= $supplier_id ?>);
    }

    // Preserve the search term if it exists
    const searchInput = document.getElementById('table-search');
    if (searchInput && searchInput.value) {
        url.searchParams.set('search', searchInput.value);
    }

    window.location.href = url.toString();
}


</script>
</body>
</html>
