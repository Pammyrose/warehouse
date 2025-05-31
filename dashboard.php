<?php
include('login_session.php');
include 'connect.php';

// Get total number of suppliers
$sql_supplier = "SELECT COUNT(*) as total_suppliers FROM supplier";
$result_supplier = $db->query($sql_supplier);
$row_supplier = $result_supplier->fetch_assoc();
$total_suppliers = $row_supplier['total_suppliers'];

// Get total number of users
$sql_users = "SELECT COUNT(*) as total_users FROM user";
$result_users = $db->query($sql_users);
$row_users = $result_users->fetch_assoc();
$total_users = $row_users['total_users'];

// Get total number of products
$sql_products = "SELECT COUNT(*) as total_products FROM product";
$result_products = $db->query($sql_products);
$row_products = $result_products->fetch_assoc();
$total_products = $row_products['total_products'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RTS</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.0/font/bootstrap-icons.css" />
<script src="https://unpkg.com/flowbite@1.6.5/dist/flowbite.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<style>
@media screen and (max-width: 700px) {
  .box {
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
@media screen and (max-width: 400px) {
  .box {
    display: flex;
    align-items: center;
    justify-content: center;
  }
}
</style>
</head>
<body>

<?php include("sidebar.php"); ?>

<div class="bg-white content-wrapper flex items-start justify-center min-h-screen p-2 md:lg:ml-[300px] lg:ml-[250px]">
  <div class="box px-4 py-16 mx-auto sm:max-w-xl md:max-w-full lg:max-w-screen-xl md:px-24 lg:px-8 lg:py-20">
    <div class="grid gap-8 row-gap-5 md:grid-cols-2">

      <!-- Total Suppliers Box -->
      <div class="p-6 bg-gray-100 rounded-lg shadow-md flex items-center justify-center w-80 h-60">
        <div class="flex flex-col items-center">
          <h2 class="text-gray-600 font-semibold mb-6 self-start">Total of Suppliers</h2>
          <p class="text-8xl font-bold text-gray-800 text-center"><?php echo $total_suppliers; ?></p>
        </div>
      </div>

      <!-- Pie Chart Card -->
      <div class="max-w-sm w-full bg-white rounded-lg shadow-sm dark:bg-gray-800 p-4 md:p-6">
        <div class="flex justify-between items-start w-full">
          <div class="flex-col items-center">
            <div class="flex items-center mb-1">
              <h5 class="text-xl font-bold leading-none text-gray-900 dark:text-white me-1">Pie Chart</h5>
              <svg data-popover-target="chart-info" data-popover-placement="bottom" class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white cursor-pointer ms-1" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 20">
                <path d="M10 .5a9.5 9.5 0 1 0 9.5 9.5A9.51 9.51 0 0 0 10 .5Zm0 16a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm1-5.034V12a1 1 0 0 1-2 0v-1.418a1 1 0 0 1 1.038-.999 1.436 1.436 0 0 0 1.488-1.441 1.501 1.501 0 1 0-3-.116.986.986 0 0 1-1.037.961 1 1 0 0 1-.96-1.037A3.5 3.5 0 1 1 11 11.466Z"/>
              </svg>
              <div data-popover id="chart-info" role="tooltip" class="absolute z-10 invisible inline-block text-sm text-gray-500 transition-opacity duration-300 bg-white border border-gray-200 rounded-lg shadow-xs opacity-0 w-72 dark:bg-gray-800 dark:border-gray-600 dark:text-gray-400">
                <div class="p-3 space-y-2">
                 <h3 class="font-semibold text-gray-900 dark:text-white">Calculation</h3>
                 <p>Each data point in the inventory report reflects a cumulative total, meaning it includes all previous quantities along with any new additions for that period. This running total approach offers a comprehensive view of inventory growth and movement over time. It enables clear tracking of stock accumulation, helps identify trends such as overstocking or depletion, and supports better forecasting and supply planning. By continuously summing data, it ensures that decision-makers can assess the overall performance and health of inventory with each passing period.</p> </div>
                <div data-popper-arrow></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Pie Chart Canvas -->
        <canvas id="myPieChart" width="400" height="400"></canvas>
      </div>

    </div>
  </div>
</div>

<script>
  const ctx = document.getElementById('myPieChart').getContext('2d');
  const myPieChart = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Users', 'Products'],
      datasets: [{
        label: 'Totals',
        data: [<?php echo $total_users; ?>, <?php echo $total_products; ?>],
        backgroundColor: ['#FF6384','#36A2EB'],
        borderColor: ['#E03A50','#2A7AE2'],
        borderWidth: 1
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: {
          position: 'bottom',
        },
        tooltip: {
          enabled: true,
        }
      }
    }
  });
</script>

</body>
</html>
