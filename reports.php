<?php
// Backend part: Prepare data as JSON inside the same PHP file

// DB connection (adjust credentials)
$db = new mysqli('localhost', 'root', '', 'warehouse');

if ($db->connect_error) {
    $json_data = json_encode(['error' => 'DB connection failed']);
} else {
    function getSumQty($db, $table, $start_date, $end_date) {
        $stmt = $db->prepare("SELECT DATE(date) as day, SUM(qty) as qty FROM $table WHERE DATE(date) BETWEEN ? AND ? GROUP BY DATE(date)");
        $stmt->bind_param('ss', $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[$row['day']] = (int)$row['qty'];
        }
        return $data;
    }

    // Declare fillZeroData once here
    function fillZeroData($period, $data) {
        $filled = [];
        foreach ($period as $day) {
            $filled[] = $data[$day] ?? 0;
        }
        return $filled;
    }

    $today = date('Y-m-d');
    $periods = [7, 30, 90];
    $dataForPeriods = [];

    foreach ($periods as $days) {
        $start = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
        $end = $today;

        $stockIn = getSumQty($db, 'stock_in', $start, $end);
        $stockOut = getSumQty($db, 'stock_out', $start, $end);

        $periodDates = [];
        for ($i = 0; $i < $days; $i++) {
            $periodDates[] = date('Y-m-d', strtotime("-$i days"));
        }
        $periodDates = array_reverse($periodDates);

        // Use the single declared function here
        $dataForPeriods[$days] = [
            'labels' => $periodDates,
            'stock_in' => fillZeroData($periodDates, $stockIn),
            'stock_out' => fillZeroData($periodDates, $stockOut),
        ];
    }

    $json_data = json_encode([
        'dates' => $dataForPeriods,
    ]);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Stock In/Out Chart</title>
<script src="https://cdn.jsdelivr.net/gh/alpinejs/alpine@v2.x.x/dist/alpine.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.3/Chart.min.js"></script>
<style>
  body { background: white; color: #a0aec0; font-family: sans-serif; }
  .container {
  max-width: 1000px;
  margin: 70px 350px;
  background: #2d3748;
  padding: 20px;
  border-radius: 8px;
}

  h3 { color: white; margin-bottom: 10px; }
  label, select { font-size: 1rem; }
  select { background: #4a5568; color: #a0aec0; border: none; padding: 5px 10px; border-radius: 4px; }
</style>
</head>
<body class="min-h-screen bg-white text-gray-300 flex items-center justify-center">

<?php include("sidebar.php"); ?>
<div class="container bg-white" x-data="chartData()" x-init="init()">
  <h3>Stock In / Stock Out Chart</h3>
  
  <label for="dateRange" style="color: white; margin-right: 10px;">Select Period:</label>
  <select id="dateRange" x-on:change="onPeriodChange($event.target.value)">
    <option value="7" selected>Last 7 Days</option>
    <option value="30">Last 30 Days</option>
    <option value="90">Last 90 Days</option>
  </select>
  
  <canvas id="chart" width="600" height="300" style="margin-top: 20px;"></canvas>
</div>

<script>
// Inject PHP JSON data into JS variable
const backendData = <?php echo $json_data; ?>;

function chartData() {
  return {
    data: null,
    chartInstance: null,
    currentPeriod: '7',

    fetchData(period = '7') {
      const periodData = backendData.dates[period];
      if (!periodData) {
        return Promise.reject('No data for period ' + period);
      }
      return Promise.resolve({
        stock_in: periodData.labels.map((date, i) => ({
          date: date,
          qty: periodData.stock_in[i],
        })),
        stock_out: periodData.labels.map((date, i) => ({
          date: date,
          qty: periodData.stock_out[i],
        })),
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
      this.loadChart(this.currentPeriod);
    },

    loadChart(period) {
      this.fetchData(period)
        .then(rawData => {
          this.data = this.prepareChartData(rawData);
          this.renderChart();
        })
        .catch(err => {
          console.error('Error fetching data:', err);
        });
    },

    onPeriodChange(period) {
      this.currentPeriod = period;
      this.loadChart(period);
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
          scales: {
            yAxes: [{
              ticks: {
                beginAtZero: true,
                min: 0,
                max: 200,
                stepSize: 10,
                callback: function(value) {
                  return value;
                }
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
