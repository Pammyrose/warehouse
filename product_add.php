<?php
include 'connect.php';

$subclass = $desc = $price = $uom = $stock = $supplier_id = '';

// Load all suppliers for the dropdown
$suppliers = [];
$supplier_result = $db->query("SELECT supplier_id, name FROM supplier ORDER BY name");
while ($row = $supplier_result->fetch_assoc()) {
    $suppliers[] = $row;
}


?>

<div class="rounded-lg p-6 w-full max-w-md shadow-xl border bg-white relative">
    <button onclick="document.getElementById('addProductModal').classList.remove('show')" class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-2xl font-bold" aria-label="Close modal">×</button>

    <h2 class="text-xl font-semibold mb-6">Add Product</h2>

    <form method="POST" action="product.php">
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

        <div class="mb-4">
            <input type="text" name="desc" placeholder="Description" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($desc); ?>" required>
        </div>

        <div class="mb-4">
            <input type="number" step="0.01" name="price" placeholder="Price" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($price); ?>" required>
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
            <input type="number" name="stock" placeholder="Stock" class="w-full border p-2 rounded" value="<?php echo htmlspecialchars($stock); ?>" required>
        </div>

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

        <div class="flex justify-end space-x-3">
            <button type="submit" class="bg-gray-900 text-white px-5 py-2 rounded hover:bg-gray-700 transition">Save</button>
            <button type="button" onclick="document.getElementById('addProductModal').classList.remove('show')" class="bg-gray-300 text-gray-800 px-5 py-2 rounded hover:bg-gray-400 transition">Cancel</button>
        </div>
    </form>
</div>