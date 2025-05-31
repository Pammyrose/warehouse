<?php
include('login_session.php');
include 'connect.php'; // Ensure this file sets $db

// Initialize variables
$product_id = $subclass = $desc = $price = $uom = $stock = $supplier_id = '';

// Handle POST form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $product_id = $_POST['product_id'];
    $subclass = $_POST['subclass'];
    $desc = $_POST['desc'];
    $price = $_POST['price'];
    $uom = $_POST['uom'];
    $stock = $_POST['stock'];
    $supplier_id = $_POST['supplier_id'];

    if (!empty($product_id)) {
        // Update existing product
        $stmt = $db->prepare("UPDATE product SET subclass=?, `desc`=?, price=?, uom=?, stock=?, supplier_id=? WHERE product_id=?");
        $stmt->bind_param("ssdsdii", $subclass, $desc, $price, $uom, $stock, $supplier_id, $product_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new product
        $stmt = $db->prepare("INSERT INTO product (subclass, `desc`, price, uom, stock, supplier_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdsdi", $subclass, $desc, $price, $uom, $stock, $supplier_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: supplier_product.php"); // redirect to product listing page
    exit();
}

// If editing, load product data
if (isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $result = $db->query("SELECT * FROM product WHERE product_id = $edit_id");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $product_id = $row['product_id'];
        $subclass = $row['subclass'];
        $desc = $row['desc'];
        $price = $row['price'];
        $uom = $row['uom'];
        $stock = $row['stock'];
        $supplier_id = $row['supplier_id'];
    }
}

// Load all suppliers for the dropdown
$suppliers = [];
$supplier_result = $db->query("SELECT supplier_id, name FROM supplier ORDER BY name");
while ($row = $supplier_result->fetch_assoc()) {
    $suppliers[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RTS - Product</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css"/>
  <script src="https://unpkg.com/flowbite@1.6.5/dist/flowbite.min.js"></script>
</head>
<body class="bg-gray-100">

<?php include("sidebar.php"); ?>

<div class="fixed inset-0 bg-opacity-40"></div>

<div id="myModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle"
  class="fixed inset-0 ml-70 flex justify-center items-center z-50">

  <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">

    <button id="closeModal" onclick="window.location.href='supplier_product.php'"
      class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 id="modalTitle" class="text-xl font-semibold mb-6"><?php echo $product_id ? 'Update Product' : 'Add Product'; ?></h2>

    <form method="POST" action="">
      <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">

      <!-- Subclass -->
      <div class="mb-4">
        <select name="subclass" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($subclass == '') ? 'selected' : ''; ?>>Select Subclass</option>
          <option value="Bakery Direct Material" <?php echo ($subclass == 'Bakery Direct Material') ? 'selected' : ''; ?>>Bakery Direct Material</option>
          <option value="Other Materials" <?php echo ($subclass == 'Other Materials') ? 'selected' : ''; ?>>Other Materials</option>
          <option value="Rice" <?php echo ($subclass == 'Rice') ? 'selected' : ''; ?>>Rice</option>
          <option value="Beverage - Drinks" <?php echo ($subclass == 'Beverage - Drinks') ? 'selected' : ''; ?>>Beverage - Drinks</option>
          <option value="Vegetable/Fruit" <?php echo ($subclass == 'Vegetable/Fruit') ? 'selected' : ''; ?>>Vegetable/Fruit</option>
          <option value="Pork" <?php echo ($subclass == 'Pork') ? 'selected' : ''; ?>>Pork</option>
          <option value="Chicken" <?php echo ($subclass == 'Chicken') ? 'selected' : ''; ?>>Chicken</option>
          <option value="Beef" <?php echo ($subclass == 'Beef') ? 'selected' : ''; ?>>Beef</option>
          <option value="Egg" <?php echo ($subclass == 'Egg') ? 'selected' : ''; ?>>Egg</option>
        </select>
      </div>

      <!-- Description -->
      <div class="mb-4">
        <input type="text" name="desc" placeholder="Description" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($desc); ?>" required>
      </div>

      <!-- Price -->
      <div class="mb-4">
        <input type="number" step="0.01" name="price" placeholder="Price" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($price); ?>" required>
      </div>

      <!-- Unit of Measure -->
      <div class="mb-4">
        <select name="uom" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($uom == '') ? 'selected' : ''; ?>>Select Unit of Measure</option>
          <option value="svg" <?php echo ($uom == 'svg') ? 'selected' : ''; ?>>svg</option>
          <option value="g" <?php echo ($uom == 'g') ? 'selected' : ''; ?>>g</option>
          <option value="ml" <?php echo ($uom == 'ml') ? 'selected' : ''; ?>>ml</option>
          <option value="pcs" <?php echo ($uom == 'pcs') ? 'selected' : ''; ?>>pcs</option>
        </select>
      </div>

      <!-- Stock -->
      <div class="mb-4">
        <input type="number" name="stock" placeholder="Stock" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($stock); ?>" required>
      </div>

      <!-- Supplier Dropdown -->
      <div class="mb-4">
        <select name="supplier_id" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($supplier_id == '') ? 'selected' : ''; ?>>Select Supplier</option>
          <?php foreach ($suppliers as $supplier): ?>
            <option value="<?php echo $supplier['supplier_id']; ?>" <?php echo ($supplier_id == $supplier['supplier_id']) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($supplier['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Buttons -->
      <div class="flex justify-end space-x-3">
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
          <?php echo $product_id ? 'Update' : 'Save'; ?>
        </button>
        <button type="button" onclick="window.location.href='supplier_product.php'" class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
