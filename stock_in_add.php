<?php
include('login_session.php');
include 'connect.php'; // Ensure this file sets $db

// Initialize variables
$id = $item = $name = $uom = $loc = $qty = '';

// Handle form POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'] ?? '';
    $item = $_POST['item'];
    $name = $_POST['name'];
    $uom = $_POST['uom'];
    $loc = $_POST['loc'];
    $qty = $_POST['qty'];

    if (!empty($id)) {
        // Update stock
        $stmt = $db->prepare("UPDATE stock_in SET item=?, name=?, uom=?, loc=?, qty=? WHERE id=?");
        $stmt->bind_param("ssssii", $item, $name, $uom, $loc, $qty, $id);
    } else {
        // Insert new stock
        $stmt = $db->prepare("INSERT INTO stock_in (item, name, uom, loc, qty) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $item, $name, $uom, $loc, $qty);
    }

    $stmt->execute();
    $stmt->close();

    header("Location: stock_in.php");
    exit();
}

// Load stock data if editing
if (isset($_GET['id'])) {
    $edit_id = $_GET['id'];
    $result = $db->query("SELECT * FROM stock_in WHERE stockin_id = $edit_id");
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $id = $row['stockin_id'];
        $item = $row['item'];
        $name = $row['name'];
        $uom = $row['uom'];
        $loc = $row['loc'];
        $qty = $row['qty'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Stock Form</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <style>
    #myModal {
      display: flex;
      justify-content: center;
      align-items: center;
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      z-index: 50;
    }
  </style>
</head>
<body class="bg-gray-100">

<?php include("sidebar.php"); ?>

<!-- Modal -->
<div id="myModal" class="fixed inset-0 ml-70 flex justify-center items-center z-50">
  <div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">
    <button id="closeModal" onclick="window.location.href='stock_in.php'"
      class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">&times;</button>

    <h2 class="text-xl font-semibold mb-6"><?php echo $id ? 'Update Stock' : 'Add Stock'; ?></h2>

    <form method="POST" action="">
      <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

      <div class="mb-4">
        <input type="text" name="item" placeholder="Item" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($item); ?>" required>
      </div>

      <div class="mb-4">
        <input type="text" name="name" placeholder="Item Name" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($name); ?>" required>
      </div>

      <div class="mb-4">
        <select name="uom" class="w-full border p-2 rounded" required>
          <option value="" disabled <?php echo ($uom == '') ? 'selected' : ''; ?>>Select Unit of Measure</option>
          <option value="svg" <?php echo ($uom == 'svg') ? 'selected' : ''; ?>>svg</option>
          <option value="g" <?php echo ($uom == 'g') ? 'selected' : ''; ?>>g</option>
          <option value="ml" <?php echo ($uom == 'ml') ? 'selected' : ''; ?>>ml</option>
          <option value="pcs" <?php echo ($uom == 'pcs') ? 'selected' : ''; ?>>pcs</option>
        </select>
      </div>

      <div class="mb-4">
        <input type="text" name="loc" placeholder="Location" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($loc); ?>" required>
      </div>

      <div class="mb-4">
        <input type="number" name="qty" placeholder="Quantity" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($qty); ?>" required>
      </div>

      <div class="flex justify-end space-x-3">
        <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-gray-700 transition">
          <?php echo $id ? 'Update' : 'Save'; ?>
        </button>
        <button type="button" onclick="window.location.href='stock_in.php'"
          class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
