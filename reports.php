<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Stock In/Out Chart</title>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@2.8.2" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4"></script>
  <style>
    body {
      background: white;
      color: #a0aec0;
      font-family: sans-serif;
    }
    .container {
      max-width: 1000px;
      margin: 70px auto;
      background: #2d3748;
      padding: 20px;
      border-radius: 8px;
      margin-right:75px;
    }
    h3 {
      color: white;
      margin-bottom: 10px;
    }
    label, select {
      font-size: 1rem;
      color: white;
    }
    select {
      background: #4a5568;
      color: #a0aec0;
      border: none;
      padding: 5px 10px;
      border-radius: 4px;
    }
  </style>
</head>
<body>
<?php include("sidebar.php"); ?>
<div class="container" x-data="chartData()" x-init="init()">
  <h3>Stock In / Stock Out Chart (Live)</h3>

  <div style="margin-bottom: 10px;">
  <label for="startDate" style="margin-right: 10px;">Start:</label>
  <input type="datetime-local" id="startDate" x-model="startDate" @change="onDateChange" />

  <label for="endDate" style="margin-left: 20px; margin-right: 10px;">End:</label>
  <input type="datetime-local" id="endDate" x-model="endDate" @change="onDateChange" />
</div>


  

  <canvas id="chart" width="800" height="400" style="margin-top: 20px;"></canvas>
</div>

<script>
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
