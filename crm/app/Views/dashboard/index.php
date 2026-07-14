<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<style>
.dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; }
.metric-card { min-height: 118px; }
.metric-icon { width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
#dashboardSummaryChart { min-height: 320px; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $cards = $summary['cards'] ?? []; ?>
<div class="dashboard-grid mb-4">
    <?php foreach ($cards as $card): ?>
        <div class="card metric-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center justify-content-between gap-3">
                <div>
                    <div class="fs-12 text-uppercase text-muted fw-semibold mb-2"><?= esc($card['label']) ?></div>
                    <div class="fs-3 fw-bold text-dark"><?= number_format((int) $card['value']) ?></div>
                </div>
                <span class="metric-icon rounded bg-light text-primary"><i class="<?= esc($card['icon'], 'attr') ?> fs-4"></i></span>
            </div>
        </div>
    <?php endforeach ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
        <h2 class="fs-5 mb-0">Resumen comercial</h2>
    </div>
    <div class="card-body">
        <div id="dashboardSummaryChart" data-labels='<?= esc(json_encode($summary['chart']['labels'] ?? [], JSON_UNESCAPED_UNICODE), 'attr') ?>' data-series='<?= esc(json_encode($summary['chart']['series'] ?? []), 'attr') ?>'></div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= base_url('assets/vendors/js/apexcharts.min.js') ?>"></script>
<script>
(function () {
    const target = document.getElementById('dashboardSummaryChart');
    if (!target || typeof ApexCharts === 'undefined') {
        return;
    }

    const labels = JSON.parse(target.dataset.labels || '[]');
    const series = JSON.parse(target.dataset.series || '[]');
    const chart = new ApexCharts(target, {
        chart: { type: 'bar', height: 320, toolbar: { show: false } },
        series: [{ name: 'Total', data: series }],
        xaxis: { categories: labels, labels: { trim: true } },
        dataLabels: { enabled: false },
        colors: ['#3454d1'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '42%' } },
        grid: { strokeDashArray: 4 },
        tooltip: { y: { formatter: function (value) { return Number(value).toLocaleString('es-MX'); } } }
    });
    chart.render();
})();
</script>
<?= $this->endSection() ?>