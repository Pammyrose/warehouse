<?php
include('login_session.php');
include 'connect.php'; // Make sure this file sets $conn

$supplier_id = $name = $classification = '';

// Check if we are handling a POST request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get values from the form
    $supplier_id = $_POST['supplier_id'];
    $name = $_POST['name'];
    $classification = $_POST['classification'];

    if (!empty($supplier_id)) {
        // Update existing supplier record
        $stmt = $db->prepare("UPDATE supplier SET name=?, classification=? WHERE supplier_id=?");
        $stmt->bind_param("ssi", $name, $classification, $supplier_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new supplier record
        $stmt = $db->prepare("INSERT INTO supplier (name, classification) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $classification);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: supplier.php"); // Redirect after save
    exit();
}

// If editing, load supplier data
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
  <title>RTS - Supplier</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css"/>
  <script src="https://unpkg.com/flowbite@1.6.5/dist/flowbite.min.js"></script>
</head>
<body class="bg-gray-100">

<?php include("sidebar.php"); ?>

  <!-- Backdrop Overlay (for dimming the background) -->
  <div class="fixed inset-0 bg-opacity-40"></div>

  <!-- Modal container (Centering with flexbox) -->
  <div id="myModal" role="dialog" aria-modal="true" aria-labelledby="modalTitle"
    class="fixed inset-0 ml-70 flex justify-center items-center z-50">

    <!-- Modal content -->
    <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white">

      <button id="closeModal" onclick="window.location.href='supplier_list.php'"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

      <h2 id="modalTitle" class="text-xl font-semibold mb-6"><?php echo $supplier_id ? 'Update Supplier' : 'Add Supplier'; ?></h2>

      <form method="POST" action="">
        <input type="hidden" name="supplier_id" value="<?php echo htmlspecialchars($supplier_id); ?>">

        <!-- Name and Classification -->
        <div class="mb-4">
          <input type="text" name="name" placeholder="Supplier Name" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>

        <div class="mb-4">
          <select name="classification" class="w-full border p-2 rounded" required>
  <option value="" disabled <?php echo ($classification == '') ? 'selected' : ''; ?>>Select Classification</option>
  
  <!-- Direct Materials Options -->
  <option value="Direct Materials - Bakery" <?php echo ($classification == 'Direct Materials - Bakery') ? 'selected' : ''; ?>>Direct Materials - Bakery</option>
  <option value="Direct Materials - Beverage" <?php echo ($classification == 'Direct Materials - Beverage') ? 'selected' : ''; ?>>Direct Materials - Beverage</option>
  <option value="Direct Materials - Kitchen" <?php echo ($classification == 'Direct Materials - Kitchen') ? 'selected' : ''; ?>>Direct Materials - Kitchen</option>
  
  <!-- Supplies & Packaging Options -->
  <option value="Supplies & Packaging - Bakery" <?php echo ($classification == 'Supplies & Packaging - Bakery') ? 'selected' : ''; ?>>Supplies & Packaging - Bakery</option>
  <option value="Supplies & Packaging - Beverage" <?php echo ($classification == 'Supplies & Packaging - Beverage') ? 'selected' : ''; ?>>Supplies & Packaging - Beverage</option>
  <option value="Supplies & Packaging - Kitchen" <?php echo ($classification == 'Supplies & Packaging - Kitchen') ? 'selected' : ''; ?>>Supplies & Packaging - Kitchen</option>
  
  <!-- Other Options -->
  <option value="Cleaning Materials" <?php echo ($classification == 'Cleaning Materials') ? 'selected' : ''; ?>>Cleaning Materials</option>
  <option value="Office Supplies" <?php echo ($classification == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
</select>

        </div>

        <!-- Submit and Cancel Buttons -->
        <div class="flex justify-end space-x-3">
          <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700 transition">
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
