<?php
include('login_session.php');
include 'connect.php';

$supplier_id = $name = $classification = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $supplier_id = $_POST['supplier_id'];
    $name = $_POST['name'];
    $classification = $_POST['classification'];

    if (!empty($supplier_id)) {
        // Update
        $stmt = $db->prepare("UPDATE supplier SET name=?, classification=? WHERE supplier_id=?");
        $stmt->bind_param("ssi", $name, $classification, $supplier_id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO supplier (name, classification) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $classification);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: supplier.php");
    exit();
}

// Load data for editing
if (isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $result = $db->query("SELECT * FROM supplier WHERE supplier_id = $edit_id");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $supplier_id = $row['supplier_id'];
        $name = $row['name'];
        $classification = $row['classification'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?php echo $supplier_id ? 'Edit Supplier' : 'Add Supplier'; ?></title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    #myModal {
      position: fixed;
      inset: 0;
      background-color: rgba(0, 0, 0, 0.5); /* Transparent black overlay */
      z-index: 1000;
      display: flex;
      justify-content: center;
      align-items: center;
    }
  </style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div id="myModal">
  <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">
    <button onclick="window.location.href='supplier.php'"
      class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">×</button>

    <h2 class="text-xl font-semibold mb-6"><?php echo $supplier_id ? 'Update Supplier' : 'Add Supplier'; ?></h2>

    <form method="POST" action="">
      <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">

      <div class="mb-4">
        <input type="text" name="name" placeholder="Supplier Name"
          class="w-full border p-2 rounded"
          value="<?php echo htmlspecialchars($name); ?>" required>
      </div>

      <div class="mb-4">
        <select name="classification" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($classification == '') ? 'selected' : ''; ?>>Select Classification</option>
          <option value="Direct Materials - Bakery" <?php echo ($classification == 'Direct Materials - Bakery') ? 'selected' : ''; ?>>Direct Materials - Bakery</option>
          <option value="Direct Materials - Beverage" <?php echo ($classification == 'Direct Materials - Beverage') ? 'selected' : ''; ?>>Direct Materials - Beverage</option>
          <option value="Direct Materials - Kitchen" <?php echo ($classification == 'Direct Materials - Kitchen') ? 'selected' : ''; ?>>Direct Materials - Kitchen</option>
          <option value="Supplies & Packaging - Bakery" <?php echo ($classification == 'Supplies & Packaging - Bakery') ? 'selected' : ''; ?>>Supplies & Packaging - Bakery</option>
          <option value="Supplies & Packaging - Beverage" <?php echo ($classification == 'Supplies & Packaging - Beverage') ? 'selected' : ''; ?>>Supplies & Packaging - Beverage</option>
          <option value="Supplies & Packaging - Kitchen" <?php echo ($classification == 'Supplies & Packaging - Kitchen') ? 'selected' : ''; ?>>Supplies & Packaging - Kitchen</option>
          <option value="Cleaning Materials" <?php echo ($classification == 'Cleaning Materials') ? 'selected' : ''; ?>>Cleaning Materials</option>
          <option value="Office Supplies" <?php echo ($classification == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
        </select>
      </div>

      <div class="flex justify-end space-x-3">
        <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
          <?php echo $supplier_id ? 'Update' : 'Save'; ?>
        </button>
        <button type="button" onclick="window.location.href='supplier.php'"
          class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>