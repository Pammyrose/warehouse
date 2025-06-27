<?php
include('login_session.php');
include "connect.php";

// Handle the deletion of a product
if (isset($_GET['delete_id'])) {
    $delete_id = mysqli_real_escape_string($db, $_GET['delete_id']);
    $delete_query = "DELETE FROM product WHERE product_id = '$delete_id'";
    
    if (mysqli_query($db, $delete_query)) {
        $_SESSION['update_message'] = "Record deleted successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['update_message'] = "Failed to delete record.";
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
    // Search in subclass and description (desc)
    $conditions[] = "(subclass LIKE '%$searchEscaped%' OR `desc` LIKE '%$searchEscaped%' OR `price` LIKE '%$searchEscaped%' OR `uom` LIKE '%$searchEscaped%' OR `stock` LIKE '%$searchEscaped%')";
}

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

// Sorting by description ascending or descending
if ($sort === 'az') {
    $sql .= " ORDER BY `subclass` ASC";
} elseif ($sort === 'za') {
    $sql .= " ORDER BY `subclass` DESC";
}

// Execute the query and check for errors
$result = mysqli_query($db, $sql);

if (!$result) {
    die('Error executing the query: ' . mysqli_error($db));
}

// Handle adding a new product (you can customize fields here)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['product_id'];
    $subclass = $_POST['subclass'];
    $desc = $_POST['desc'];
    $price = $_POST['price'];
    $uom = $_POST['uom'];
    $stock = $_POST['stock'];

    $sql = "INSERT INTO product (product_id, subclass, `desc`, price, uom, stock) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        die("Prepare failed for insert: (" . $db->errno . ") " . $db->error);
    }

    $stmt->bind_param("sssdis", $product_id, $subclass, $desc, $price, $uom, $stock);

    if ($stmt->execute()) {
        $_SESSION['update_message'] = "Record added successfully.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['update_message'] = "Failed to add product.";
    }

    $stmt->close();
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Product Management</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script>
        function updateSort(sortValue) {
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            const currentSearch = url.searchParams.get('search');
            if (currentSearch) {
                url.searchParams.set('search', currentSearch);
            }
            window.location.href = url.toString();
        }
    </script>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-2 lg:ml-[250px]">
    <div class="flex flex-col sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4 mt-10">
        <div class="relative inline-block">
            <button id="dropdownToggle" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100 font-medium rounded-lg text-sm px-3 py-1.5" type="button">
                Sort by Subclass
                <svg class="w-2.5 h-2.5 ms-2.5" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4" />
                </svg>
            </button>

            <div id="dropdownMenu" class="absolute z-10 hidden w-28 bg-white divide-y divide-gray-100 rounded-lg shadow-sm">
                <ul class="space-y-1 text-sm text-gray-700" aria-labelledby="dropdownToggle">
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                            <input id="filter-radio-az" type="radio" value="az" name="filter-radio" <?php if ($sort === 'az') echo 'checked'; ?> onclick="updateSort('az')" class="w-4 h-4" />
                            <label for="filter-radio-az" class="ms-2 text-sm font-medium">A – Z</label>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-100">
                            <input id="filter-radio-za" type="radio" value="za" name="filter-radio" <?php if ($sort === 'za') echo 'checked'; ?> onclick="updateSort('za')" class="w-4 h-4" />
                            <label for="filter-radio-za" class="ms-2 text-sm font-medium">Z – A</label>
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
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" id="table-search" class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500" placeholder="Search..." />
                <a href="product_add.php" class="inline-flex bg-gray-900 items-center text-white border border-gray-300 focus:outline-none hover:bg-gray-700 font-lg rounded-lg text-md px-3 py-1.5">+</a>
            </form>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-500 mt-2">
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
                                <th scope="row" class="px-7 py-4 font-medium whitespace-nowrap"><?php echo $counter++; ?></th>
                                <td><?php echo htmlspecialchars($row['subclass']); ?></td>
                                <td><?php echo htmlspecialchars($row['desc']); ?></td>
                                <td>₱<?php echo htmlspecialchars(number_format($row['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($row['uom']); ?></td>
                                <td><?php echo htmlspecialchars($row['stock']); ?></td>
                                <td>
                                    <a href="product_view.php?id=<?php echo $row['product_id']; ?>" class="font-medium text-yellow-500 hover:underline mr-3">Edit</a>
                                    <a href="?delete_id=<?php echo $row['product_id']; ?>" class="font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('dropdownToggle').addEventListener('click', function () {
    const menu = document.getElementById('dropdownMenu');
    menu.classList.toggle('hidden');
});

document.addEventListener('click', function (event) {
    const menu = document.getElementById('dropdownMenu');
    const toggle = document.getElementById('dropdownToggle');
    if (!toggle.contains(event.target) && !menu.contains(event.target)) {
        menu.classList.add('hidden');
    }
});
</script>
</body>
</html>
