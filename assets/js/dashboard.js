/* =========================================================
   dashboard.js — POS Dashboard
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {

  // ---------- Sales Chart ----------
  const canvas = document.getElementById('salesChart');
  if (!canvas) return;

  const labels    = JSON.parse(canvas.dataset.labels   || '[]');
  const values    = JSON.parse(canvas.dataset.values   || '[]');
  const currency  = canvas.dataset.currency || 'Rs.';

  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 280);
  gradient.addColorStop(0, 'rgba(13, 110, 253, 0.25)');
  gradient.addColorStop(1, 'rgba(13, 110, 253, 0)');

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: labels,
      datasets: [{
        label: 'Revenue',
        data: values,
        borderColor: '#0d6efd',
        backgroundColor: gradient,
        borderWidth: 2,
        tension: 0.35,
        fill: true,
        pointRadius: 3,
        pointHoverRadius: 6,
        pointBackgroundColor: '#0d6efd',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: '#212529',
          titleColor: '#fff',
          bodyColor: '#fff',
          padding: 10,
          displayColors: false,
          callbacks: {
            label: ctx => `${currency} ${Number(ctx.parsed.y).toFixed(2)}`
          }
        }
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: '#6c757d', font: { size: 11 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: '#eef0f4' },
          ticks: {
            color: '#6c757d',
            font: { size: 11 },
            callback: v => currency + v
          }
        }
      }
    }
  });
});