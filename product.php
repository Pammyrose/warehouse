<?php
include('login_session.php');
include "connect.php";

$subclassFilter = $_GET['subclass'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$sortColumn = $_GET['sort'] ?? '';
$allowedSortColumns = ['price', 'desc', 'subclass', 'stock', 'uom'];

if (!in_array($sortColumn, $allowedSortColumns)) {
    $sortColumn = '';
}

// Build base query
$sql = "SELECT * FROM product WHERE 1=1 ";
$params = [];
$types = "";

// Filter: Subclass
if (!empty($subclassFilter)) {
    $sql .= " AND subclass = ? ";
    $params[] = $subclassFilter;
    $types .= "s";
}

// Search: Across desc, price, uom, stock
if (!empty($searchQuery)) {
    $sql .= " AND (
        `desc` LIKE ? OR
        CAST(price AS CHAR) LIKE ? OR
        uom LIKE ? OR
        CAST(stock AS CHAR) LIKE ? OR
        subclass LIKE ?
    )";
    $likeTerm = "%$searchQuery%";
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $params[] = $likeTerm;
    $types .= "sssss";
}


// Sort
if (!empty($sortColumn)) {
    $sql .= " ORDER BY `$sortColumn` ASC ";
}

// Prepare + bind
$stmt = $db->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die("Query failed: " . $db->error);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
   
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css"
    />
    <script src="https://unpkg.com/flowbite@1.6.5/dist/flowbite.min.js"></script>

</head>
<style>
    .thead ,sr-only{
        background-color: #3498db;
    }

    .auto_num{
        counter-reset: rowNumber;
  counter-increment: rowNumber;
  content: counter(rowNumber) ".";
  padding-right: 0.3em;
  text-align: right;
    }
</style>
<body>

    <?php include("sidebar.php"); ?>

    <div class="ml-70">
<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-2">
    <div class="flex flex-column sm:flex-row flex-wrap space-y-4 sm:space-y-0 items-center justify-between pb-4 mt-10">
       
    <div>
            <button id="dropdownRadioButton" data-dropdown-toggle="dropdownRadio" class="inline-flex items-center text-black bg-white border border-gray-300 focus:outline-none hover:bg-gray-100  font-medium rounded-lg text-sm px-3 py-1.5 dark:border-gray-600 dark:hover:border-gray-600 dark:focus:ring-gray-700" type="button">
            
                Filter
                <svg class="w-2.5 h-2.5 ms-2.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                </svg>
            </button>
            <!-- Dropdown menu -->
            <div id="dropdownRadio" class="z-10 hidden w-48 bg-gray-100 divide-y divide-gray-100 rounded-lg shadow-sm dark:divide-gray-600" data-popper-reference-hidden="" data-popper-escaped="" data-popper-placement="top" style="position: absolute; inset: auto auto 0px 0px; margin: 0px; transform: translate3d(522.5px, 3847.5px, 0px);">
                <ul class="p-3 space-y-1 text-sm text-blck" aria-labelledby="dropdownRadioButton">
                <li>
  <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
    <a href="product.php" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">All</a>
  </div>
</li>

                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Bakery Direct Material" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Bakery Direct Material</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Other Materials" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Other Materials</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Rice" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Rice</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Beverage - Drinks" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Beverage - Drinks</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Vegetable/Fruit" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Vegetable/Fruit</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Pork" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Pork</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Chicken" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Chicken</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Beef" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Beef</a>
                        </div>
                    </li>
                    <li>
                        <div class="flex items-center p-2 rounded-sm hover:bg-gray-80 dark:hover:bg-gray-300">
                        <a href="?subclass=Egg" class="w-full ms-2 text-sm font-medium text-gray-900 rounded-sm">Egg</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
        <label for="table-search" class="sr-only">Search</label>
        <div class="relative">
        <div class="absolute inset-y-0 left-0 rtl:inset-r-0 rtl:right-0 flex items-center ps-3 pointer-events-none">
                <svg class="w-5 h-5 text-gray-500 dark:text-gray-400" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"></path></svg>
            </div>
            <form method="GET" action="product.php" class="flex space-x-2 items-center">
    <!-- Subclass Filter -->


    <!-- Search Box -->
    <input
        type="text"
        name="search"
        value="<?= htmlspecialchars($searchQuery) ?>"
        placeholder="Search for items"
        class="block p-2 ps-10 text-sm text-black border border-gray-300 rounded-lg w-80 focus:ring-blue-500 focus:border-blue-500"
    />



    <button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hidden"></button>
</form>



        </div>
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 mt-2 ">
        <thead class="thead text-xs text-white uppercase text-center">
            <tr>
            <th scope="col" class="px-6 py-3">
                    No
                </th>
                <th scope="col" class="px-6 py-3">
                    Subclass
                </th>
                <th scope="col" class="px-6 py-3">
                    Description
                </th>
                <th scope="col" class="px-6 py-3">
                    Price
                </th>
                <th scope="col" class="px-6 py-3">
                    UOM
                </th>
                <th scope="col" class="px-6 py-3">
                    Stock
                </th>
                <th scope="col" class="px-6 py-3">
                    Action
                </th>
            </tr>
        </thead>
        <tbody>
<?php 
$counter = 1;
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        ?>
        <tr class="bg-white text-black text-center border-b dark:border-gray-700 border-gray-200 ">
            <th scope="row" class="px-6 py-4 font-medium whitespace-nowrap ">
                <?php echo $counter++; ?>
            </th>
            <th scope="row" class="px-6 py-4 font-medium whitespace-nowrap ">
                <?php echo htmlspecialchars($row['subclass']); ?>
            </th>
            <th scope="row" class="px-6 py-4 font-medium whitespace-nowrap ">
                <?php echo htmlspecialchars($row['desc']); ?>
            </th>
            <td class="px-6 py-4">
                ₱<?php echo htmlspecialchars($row['price']); ?>
            </td>
            <td class="px-6 py-4">
                <?php echo htmlspecialchars($row['uom']); ?>
            </td>
            <td class="px-6 py-4">
                <?php echo htmlspecialchars($row['stock']); ?>
            </td>
            <td class="px-6 py-4">
                <a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
            </td>
        </tr>
        <?php
    }
} else {
    ?>
    <tr>
        <td colspan="7" class="text-center py-4 text-gray-600">No records found</td>
    </tr>
    <?php
}
?>
</tbody>

    </table>
    </div>
   
</div>
</div>
</body>
</html>