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

// Get total number of stock_start entries from the latest date
$sql_stocks = "
  SELECT SUM(stock_start) as total_stocks
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_stocks = $db->query($sql_stocks);
$row_stocks = $result_stocks->fetch_assoc();
$total_stocks = $row_stocks['total_stocks'];


// Get total number of stockin entries from the latest date
$sql_stockin = "
  SELECT SUM(total) as total_stockin
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_stockin = $db->query($sql_stockin);
$row_stockin = $result_stockin->fetch_assoc();
$total_stockin = $row_stockin['total_stockin'];

// Get total number of stockout entries from the latest date
$sql_stockout = "
  SELECT SUM(out_stock) as total_stockout
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_stockout = $db->query($sql_stockout);
$row_stockout = $result_stockout->fetch_assoc();
$total_stockout = $row_stockout['total_stockout'];

// Get total number of pyesta_outstock entries from the latest date
$sql_pyesta_stockout = "
  SELECT SUM(pyesta_outstock) as total_pyesta_stockout
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_pyesta_stockout = $db->query($sql_pyesta_stockout);
$row_pyesta_stockout = $result_pyesta_stockout->fetch_assoc();
$total_pyesta_stockout = $row_pyesta_stockout['total_pyesta_stockout'];

// Get total number of save5_outstock entries from the latest date
$sql_save5_stockout = "
  SELECT SUM(save5_outstock) as total_save5_stockout
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_save5_stockout = $db->query($sql_save5_stockout);
$row_save5_stockout = $result_save5_stockout->fetch_assoc();
$total_save5_stockout = $row_save5_stockout['total_save5_stockout'];

// Get total number of stockout entries (out_stock + pyesta_outstock + save5_outstock) from the latest date
$sql_total_stockout = "
  SELECT SUM(COALESCE(out_stock, 0) + COALESCE(pyesta_outstock, 0) + COALESCE(save5_outstock, 0)) as total_stockout
  FROM stock_log
  WHERE DATE(date) = (
    SELECT DATE(MAX(date)) FROM stock_log
  )
";
$result_stockout = $db->query($sql_total_stockout);
$row_stockout = $result_stockout->fetch_assoc();
$total_total_stockout = $row_stockout['total_stockout'] ?: 0;
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
<script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2" defer></script>


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

<div class="bg-white content-wrapper flex flex-col items-center justify-start min-h-screen p-2 md:lg:ml-[300px] lg:ml-[250px]">

    <h1 class="text-3xl font-bold text-left underline shadow-lg ">Dashboard</h1>

<div class="w-full max-w-screen-xl px-4 py-8">

    <!-- Grid for 6 Total Boxes -->
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-6 gap-6 mb-10">

          <!-- Total Users -->
          <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Users</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_users; ?></p>
      </div>

            <!-- Total Products -->
            <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Products</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_products; ?></p>
      </div>
      
      <!-- Total Suppliers -->
      <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Suppliers</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_suppliers; ?></p>
      </div>

      <!-- Placeholder Box 4 -->
      <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Stocks</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_stocks; ?></p>
      </div>

      <!-- Placeholder Box 5 -->
      <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Stock In</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_stockin; ?></p>
      </div>

      <!-- Placeholder Box 6 -->
      <div class="bg-gray-100 rounded-lg shadow-md p-6 text-center">
        <h2 class="text-gray-600 font-semibold mb-2">Stock Out</h2>
        <p class="text-5xl font-bold text-gray-800"><?php echo $total_total_stockout; ?></p>
      </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 px-4 py-6">
  <!-- Pie Chart Card (1/3 width on large screens) -->
  <div class="bg-gray-800 rounded-lg shadow-md p-6 w-full lg:w-1/3">
    <h5 class="text-xl font-bold text-white mb-13">Pie Chart</h5>
    <canvas id="myPieChart" class="w-full max-w-md mx-auto"></canvas>
  </div>

  <div class="bg-gray-800 text-white rounded-lg shadow-md p-6 w-full lg:w-2/3" 
     x-data="chartData()" x-init="init()">
  <h3 class="text-white text-xl mb-4">Stock In / Stock Out Chart (Live)</h3>

  <div class="mb-4">
    <label for="startDate" class="mr-2">Start:</label>
    <input type="datetime-local" id="startDate" x-model="startDate" @change="onDateChange"
           class="bg-gray-700 text-white p-1 rounded" />

    <label for="endDate" class="ml-4 mr-2">End:</label>
    <input type="datetime-local" id="endDate" x-model="endDate" @change="onDateChange"
           class="bg-gray-700 text-white p-1 rounded" />
  </div>

  <canvas id="chart" width="600" height="300" class="w-full"></canvas>
</div>

</div>



    </div>
  </div>
</div>

<script>
const ctx = document.getElementById('myPieChart').getContext('2d');
const myPieChart = new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Stock In','GTR Outstock', 'Pyesta Outstock', 'Save5 Outstock'],
        datasets: [{
            label: 'Totals',
            data: [
              <?php echo $total_stockin; ?>,
                <?php echo $total_stockout; ?>,
                <?php echo $total_pyesta_stockout; ?>,
                <?php echo $total_save5_stockout; ?>,

            
            ],
            backgroundColor: [

                '#ED64A6', // Stock in
                ' #BDC3C7 ', //GTR
                '#36A2EB', // Pyesta
                '#9966FF', // Stock In
            
            ],
            borderColor: [

                '#ED64A6', // Stock in
                ' #BDC3C7 ', //GTR
                '#2A7AE2', // Pyesta
                '#7A4FCC', // Stock In
        
            ],
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

  function chartData() {
  return {
    data: null,
    chartInstance: null,
    startDate: '',
    endDate: '',
    intervalId: null,

    fetchData(startDate, endDate) {
      const url = `chart_data.php?start=${encodeURIComponent(startDate)}&end=${encodeURIComponent(endDate)}`;
      return fetch(url)
        .then(res => res.json())
        .then(data => {
          return {
            stock_in: data.labels.map((date, i) => ({
              date: date,
              qty: data.stock_in[i],
            })),
            stock_out: data.labels.map((date, i) => ({
              date: date,
              qty: data.stock_out[i],
            })),
          };
        });
    },

    prepareChartData(rawData) {
      let allDatesSet = new Set();
      rawData.stock_in.forEach(i => allDatesSet.add(i.date));
      rawData.stock_out.forEach(i => allDatesSet.add(i.date));
      let allDates = Array.from(allDatesSet).sort();

      let stockInData = allDates.map(date => {
        let found = rawData.stock_in.find(i => i.date === date);
        return found ? found.qty : 0;
      });

      let stockOutData = allDates.map(date => {
        let found = rawData.stock_out.find(i => i.date === date);
        return found ? found.qty : 0;
      });

      return { labels: allDates, stock_in: stockInData, stock_out: stockOutData };
    },

    init() {
      const now = new Date();
      const earlier = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000)); // 7 days ago
      this.startDate = earlier.toISOString().slice(0, 16);
      this.endDate = now.toISOString().slice(0, 16);

      this.loadChart(this.startDate, this.endDate);
      this.intervalId = setInterval(() => {
        this.loadChart(this.startDate, this.endDate);
      }, 60000);
    },

    onDateChange() {
      if (this.startDate && this.endDate) {
        this.loadChart(this.startDate, this.endDate);
      }
    },

    loadChart(startDate, endDate) {
      this.fetchData(startDate, endDate)
        .then(rawData => {
          this.data = this.prepareChartData(rawData);
          this.renderChart();
        })
        .catch(err => {
          console.error('Error fetching data:', err);
        });
    },

    renderChart() {
      if (this.chartInstance) {
        this.chartInstance.destroy();
      }

      const ctx = document.getElementById('chart').getContext('2d');
      this.chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: this.data.labels,
          datasets: [
            {
              label: 'Stock In',
              backgroundColor: 'rgba(102, 126, 234, 0.25)',
              borderColor: 'rgba(102, 126, 234, 1)',
              pointBackgroundColor: 'rgba(102, 126, 234, 1)',
              data: this.data.stock_in,
              fill: true,
            },
            {
              label: 'Stock Out',
              backgroundColor: 'rgba(237, 100, 166, 0.25)',
              borderColor: 'rgba(237, 100, 166, 1)',
              pointBackgroundColor: 'rgba(237, 100, 166, 1)',
              data: this.data.stock_out,
              fill: true,
            }
          ]
        },
        options: {
          responsive: true,
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true
              }
            }]
          }
        }
      });
    }
  }
}
</script>

</body>
</html>
