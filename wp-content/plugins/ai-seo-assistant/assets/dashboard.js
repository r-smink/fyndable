(function() {
  const data = window.AISEODashboardData || { counts: {}, health: [], trends: [], alerts: [] };
  const ctxCounts = document.getElementById('aiseo-counts');
  if (ctxCounts && window.Chart) {
    const labels = Object.keys(data.counts || {});
    const values = Object.values(data.counts || {});
    new Chart(ctxCounts, {
      type: 'bar',
      data: { labels, datasets: [{ label: 'Snapshots', data: values, backgroundColor: ['#5B8DEF', '#4CD4A9', '#FFC15F'], borderRadius: 6 }] },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }

  const ctxHealth = document.getElementById('aiseo-health');
  if (ctxHealth && window.Chart) {
    const statuses = (data.health || []).reduce((acc, row) => { acc[row.status] = (acc[row.status] || 0) + 1; return acc; }, {});
    new Chart(ctxHealth, {
      type: 'doughnut',
      data: { labels: Object.keys(statuses), datasets: [{ data: Object.values(statuses), backgroundColor: ['#4CD4A9', '#FFC15F', '#E76F51'] }] },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }

  const ctxTrend = document.getElementById('aiseo-trend');
  if (ctxTrend && window.Chart) {
    const labels = (data.trends || []).map(t => t.date);
    const values = (data.trends || []).map(t => t.avg);
    new Chart(ctxTrend, {
      type: 'line',
      data: { labels, datasets: [{ label: 'Avg best position', data: values, borderColor: '#5B8DEF', tension: 0.2, fill: false }] },
      options: { plugins: { legend: { display: false } }, scales: { y: { reverse: true } } }
    });
  }
})();
