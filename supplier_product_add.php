<?php
include('login_session.php');
include 'connect.php';

// DEBUG output to check supplier_id source (remove after debugging)
echo '<pre>';
echo "GET supplier_id: "; var_dump($_GET['supplier_id'] ?? null);
echo "POST supplier_id: "; var_dump($_POST['supplier_id'] ?? null);
echo '</pre>';

// Initialize variables
$product_id = $subclass = $desc = $price = $uom = $stock = '';
$supplier_id = 0;

// 1. Get supplier_id from GET first (page load)
if (isset($_GET['supplier_id']) && is_numeric($_GET['supplier_id'])) {
    $supplier_id = (int) $_GET['supplier_id'];
}

// 2. Override supplier_id if POST (form submission)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['supplier_id']) && is_numeric($_POST['supplier_id'])) {
        $supplier_id = (int) $_POST['supplier_id'];
    }
}

// 3. Validate supplier_id
if ($supplier_id <= 0) {
    die("❌ Error: Supplier ID is missing or invalid.");
}

// 4. Check if supplier exists in DB with error handling
$supplier_check = $db->prepare("SELECT 1 FROM supplier WHERE supplier_id = ?");
if (!$supplier_check) {
    die("❌ Prepare failed: " . $db->error);
}
$supplier_check->bind_param("i", $supplier_id);
if (!$supplier_check->execute()) {
    die("❌ Execute failed: " . $supplier_check->error);
}
$supplier_result = $supplier_check->get_result();
if ($supplier_result === false) {
    die("❌ get_result failed: " . $supplier_check->error);
}
if ($supplier_result->num_rows === 0) {
    die("❌ Error: Supplier not found for supplier_id=$supplier_id.");
}
$supplier_check->close();

// 5. Handle form submission (POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize inputs
    $product_id = $_POST['product_id'] ?? '';
    $subclass = $_POST['subclass'] ?? '';
    $desc = $_POST['desc'] ?? '';
    $price = $_POST['price'] ?? 0;
    $uom = $_POST['uom'] ?? '';
    $stock = $_POST['stock'] ?? 0;

    if (!empty($product_id)) {
        // Update product
        $stmt = $db->prepare("UPDATE product SET subclass=?, `desc`=?, price=?, uom=?, stock=?, supplier_id=? WHERE product_id=?");
        if (!$stmt) {
            die("❌ Prepare failed: " . $db->error);
        }
        // Bind params: s=string, d=double, i=int
        $stmt->bind_param("ssdsiii", $subclass, $desc, $price, $uom, $stock, $supplier_id, $product_id);
        if (!$stmt->execute()) {
            die("❌ Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } else {
        // Insert new product
        $stmt = $db->prepare("INSERT INTO product (subclass, `desc`, price, uom, stock, supplier_id) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("❌ Prepare failed: " . $db->error);
        }
        $stmt->bind_param("ssdssi", $subclass, $desc, $price, $uom, $stock, $supplier_id);
        if (!$stmt->execute()) {
            die("❌ Execute failed: " . $stmt->error);
        }
        $stmt->close();
    }

    // Redirect back to supplier product list
    header("Location: supplier_product.php?supplier_id=$supplier_id");
    exit();
}

// 6. Load existing product data if editing (GET id param)
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM product WHERE product_id = ?");
    if (!$stmt) {
        die("❌ Prepare failed: " . $db->error);
    }
    $stmt->bind_param("i", $edit_id);
    if (!$stmt->execute()) {
        die("❌ Execute failed: " . $stmt->error);
    }
    $result = $stmt->get_result();
    if ($result && $result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $product_id = $row['product_id'];
        $subclass = $row['subclass'];
        $desc = $row['desc'];
        $price = $row['price'];
        $uom = $row['uom'];
        $stock = $row['stock'];
        $supplier_id = (int)$row['supplier_id']; // override supplier_id from product
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $product_id ? 'Edit Product' : 'Add Product'; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">

<?php include("sidebar.php"); ?>

<div id="myModal" class="fixed inset-0 ml-70 flex justify-center items-center z-50">
  <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">
    <button onclick="window.location.href='supplier_product.php?supplier_id=<?php echo $supplier_id; ?>'"
      class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 class="text-xl font-semibold mb-6"><?php echo $product_id ? 'Update Product' : 'Add Product'; ?></h2>

    <form method="POST" action="">
      <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product_id); ?>">
      <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">

      <!-- Subclass -->
      <div class="mb-4">
        <select name="subclass" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($subclass == '') ? 'selected' : ''; ?>>Select Subclass</option>
          <?php
          $options = [
            'Bakery Direct Material', 'Other Materials', 'Rice',
            'Beverage - Drinks', 'Vegetable/Fruit', 'Pork', 'Chicken', 'Beef', 'Egg'
          ];
          foreach ($options as $option) {
            echo '<option value="' . htmlspecialchars($option) . '" ' . ($subclass == $option ? 'selected' : '') . '>' . htmlspecialchars($option) . '</option>';
          }
          ?>
        </select>
      </div>

      <div class="mb-4">
        <input type="text" name="desc" placeholder="Description" class="w-full border p-2 rounded"
          value="<?php echo htmlspecialchars($desc); ?>" required>
      </div>

      <div class="mb-4">
        <input type="number" step="0.01" name="price" placeholder="Price" class="w-full border p-2 rounded"
          value="<?php echo htmlspecialchars($price); ?>" required>
      </div>

      <div class="mb-4">
        <select name="uom" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($uom == '') ? 'selected' : ''; ?>>Select Unit of Measure</option>
          <?php
          $uoms = ['svg', 'g', 'ml', 'pcs'];
          foreach ($uoms as $unit) {
              echo '<option value="' . htmlspecialchars($unit) . '" ' . ($uom == $unit ? 'selected' : '') . '>' . htmlspecialchars($unit) . '</option>';
          }
          ?>
        </select>
      </div>

      <div class="mb-4">
        <input type="number" name="stock" placeholder="Stock" class="w-full border p-2 rounded"
          value="<?php echo htmlspecialchars($stock); ?>" required>
      </div>

      <div class="flex justify-end space-x-3">
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
          <?php echo $product_id ? 'Update' : 'Save'; ?>
        </button>
        <button type="button" onclick="window.location.href='supplier_product.php?supplier_id=<?php echo htmlspecialchars($supplier_id); ?>'"
          class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
