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
<?php $forecast=$summary['forecast']??[];$totals=$forecast['totals']??[]; ?>
<div class="card border-0 shadow-sm mt-4"><div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center"><div><h2 class="fs-5 mb-0">Forecast <?= esc($forecast['year']??date('Y')) ?></h2><small class="text-muted">Real anterior, real actual y meta asignada</small></div><form method="get" class="d-flex gap-2"><input class="form-control form-control-sm" type="number" name="anio" min="2000" max="2100" value="<?= esc($forecast['year']??date('Y')) ?>"><button class="btn btn-sm btn-outline-primary">Ver</button></form></div><div class="card-body"><div class="row g-3 mb-3"><div class="col-md-3"><div class="p-3 bg-light rounded">Real anterior<br><strong>$<?= number_format($totals['previous']??0,2) ?></strong></div></div><div class="col-md-3"><div class="p-3 bg-light rounded">Real actual<br><strong>$<?= number_format($totals['actual']??0,2) ?></strong></div></div><div class="col-md-3"><div class="p-3 bg-light rounded">Meta<br><strong>$<?= number_format($totals['goal']??0,2) ?></strong></div></div><div class="col-md-3"><div class="p-3 bg-light rounded">Cumplimiento<br><strong><?= number_format($totals['attainment']??0,1) ?>%</strong></div></div></div><div id="forecastChart" data-forecast='<?= esc(json_encode($forecast,JSON_UNESCAPED_UNICODE),'attr') ?>'></div></div></div>
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
    const forecastTarget=document.getElementById('forecastChart');if(forecastTarget){const f=JSON.parse(forecastTarget.dataset.forecast||'{}');new ApexCharts(forecastTarget,{chart:{type:'line',height:360,toolbar:{show:false}},series:[{name:'Real '+(Number(f.year)-1),data:f.previous||[]},{name:'Real '+f.year,data:f.actual||[]},{name:'Meta '+f.year,data:f.goal||[]}],xaxis:{categories:f.labels||[]},stroke:{width:[3,3,3],curve:'smooth'},colors:['#9ca3af','#3454d1','#16a34a'],yaxis:{labels:{formatter:v=>'$'+Number(v).toLocaleString('es-MX')}},tooltip:{y:{formatter:v=>Number(v).toLocaleString('es-MX',{style:'currency',currency:'MXN'})}}}).render();}
})();
</script>
<?= $this->endSection() ?>
