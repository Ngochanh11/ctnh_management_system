<?php
require_once '../includes/config.php';
requireLogin();
checkPermission('reports', 'view') or die('Không có quyền');

$hasDateFilter = isset($_GET['from_date']) || isset($_GET['to_date']);
$fromDate = $_GET['from_date'] ?? date('Y-m-01');
$toDate = $_GET['to_date'] ?? date('Y-m-d');
$customerId = $_GET['customer_id'] ?? '';
$reportRangeNotice = '';

if (!$hasDateFilter) {
    $latestDocumentResult = krudApiList(API_TABLE_DOCUMENTS, [], ['collection_date' => 'DESC'], 1, 1);
    $latestCollectionDate = $latestDocumentResult['data'][0]['collection_date'] ?? '';
    if (($latestDocumentResult['success'] ?? false) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $latestCollectionDate)) {
        if (substr($latestCollectionDate, 0, 7) < date('Y-m')) {
            $latestMonth = strtotime($latestCollectionDate);
            if ($latestMonth !== false) {
                $fromDate = date('Y-m-01', $latestMonth);
                $toDate = date('Y-m-t', $latestMonth);
                $reportRangeNotice = 'Đang hiển thị tháng gần nhất có dữ liệu. Bạn có thể chọn khoảng thời gian khác ở bộ lọc.';
            }
        }
    }
}

$customerResult = krudApiList(API_TABLE_CUSTOMERS, [], ['full_name' => 'ASC'], 1, 1000);
$customers = array_map('krudCustomerRecord', $customerResult['data'] ?? []);
$customersById = [];
foreach ($customers as $customer) {
    $customersById[$customer['id']] = $customer;
}

$documentFilters = [
    ['field' => 'collection_date', 'operator' => '>=', 'value' => $fromDate],
    ['field' => 'collection_date', 'operator' => '<=', 'value' => $toDate],
];
if ($customerId) {
    $documentFilters[] = ['field' => 'customer_id', 'operator' => '=', 'value' => (int) $customerId];
}
$documentResult = krudApiList(API_TABLE_DOCUMENTS, $documentFilters, [], 1, 5000);
$groups = [];
$dailyGroups = [];
$monthlyGroups = [];
foreach (($documentResult['data'] ?? []) as $document) {
    $groupId = $document['customer_id'] ?? 0;
    $collectionDate = $document['collection_date'] ?? '';
    if ($collectionDate !== '') {
        $collectionMonth = substr($collectionDate, 0, 7);
        if (!isset($dailyGroups[$collectionDate])) {
            $dailyGroups[$collectionDate] = ['total_docs' => 0, 'total_weight' => 0];
        }
        $dailyGroups[$collectionDate]['total_docs']++;
        $dailyGroups[$collectionDate]['total_weight'] += (float) ($document['total_weight'] ?? 0);
        if (!isset($monthlyGroups[$collectionMonth])) {
            $monthlyGroups[$collectionMonth] = ['total_docs' => 0, 'total_weight' => 0];
        }
        $monthlyGroups[$collectionMonth]['total_docs']++;
        $monthlyGroups[$collectionMonth]['total_weight'] += (float) ($document['total_weight'] ?? 0);
    }
    if (!isset($groups[$groupId])) {
        $groups[$groupId] = [
            'company_name' => $customersById[$groupId]['company_name'] ?? '',
            'total_docs' => 0,
            'total_weight' => 0,
        ];
    }
    $groups[$groupId]['total_docs']++;
    $groups[$groupId]['total_weight'] += (float) ($document['total_weight'] ?? 0);
}
$reportData = array_values($groups);
usort($reportData, static fn($left, $right) => $right['total_weight'] <=> $left['total_weight']);
$dailyReportData = [];
foreach ($dailyGroups as $collectionDate => $totals) {
    $dailyReportData[] = ['collection_date' => $collectionDate] + $totals;
}
usort($dailyReportData, static fn($left, $right) => $right['collection_date'] <=> $left['collection_date']);
$monthlyReportData = [];
foreach ($monthlyGroups as $collectionMonth => $totals) {
    $monthlyReportData[] = ['collection_month' => $collectionMonth] + $totals;
}
usort($monthlyReportData, static fn($left, $right) => $right['collection_month'] <=> $left['collection_month']);

$totalDocs = array_sum(array_column($reportData, 'total_docs'));
$totalWeight = array_sum(array_column($reportData, 'total_weight'));
$dailyChartData = array_reverse(array_slice($dailyReportData, 0, 14));
$monthlyChartData = array_reverse(array_slice($monthlyReportData, 0, 6));
$maxDailyWeight = max(array_column($dailyChartData, 'total_weight') ?: [0]);
$maxMonthlyWeight = max(array_column($monthlyChartData, 'total_weight') ?: [0]);
$maxCustomerWeight = max(array_column($reportData, 'total_weight') ?: [0]);
$totalReportedCustomers = count($reportData);

$chartWidth = 820;
$chartHeight = 250;
$chartLeft = 56;
$chartRight = 790;
$chartTop = 24;
$chartBottom = 190;
$dailyPoints = [];
if (count($dailyChartData) === 1) {
    $dailyPoints[] = [
        'x' => ($chartLeft + $chartRight) / 2,
        'y' => $maxDailyWeight > 0 ? $chartBottom - (($dailyChartData[0]['total_weight'] / $maxDailyWeight) * ($chartBottom - $chartTop)) : $chartBottom,
    ];
} else {
    foreach ($dailyChartData as $index => $row) {
        $x = $chartLeft + ($index * ($chartRight - $chartLeft) / max(count($dailyChartData) - 1, 1));
        $y = $maxDailyWeight > 0
            ? $chartBottom - (($row['total_weight'] / $maxDailyWeight) * ($chartBottom - $chartTop))
            : $chartBottom;
        $dailyPoints[] = ['x' => $x, 'y' => $y];
    }
}
$dailyPolyline = implode(' ', array_map(static fn($point) => number_format($point['x'], 1, '.', '') . ',' . number_format($point['y'], 1, '.', ''), $dailyPoints));
$dailyAreaPath = $dailyPoints
    ? 'M ' . number_format($dailyPoints[0]['x'], 1, '.', '') . ' ' . $chartBottom
        . ' L ' . implode(' L ', array_map(static fn($point) => number_format($point['x'], 1, '.', '') . ' ' . number_format($point['y'], 1, '.', ''), $dailyPoints))
        . ' L ' . number_format(end($dailyPoints)['x'], 1, '.', '') . ' ' . $chartBottom . ' Z'
    : '';

include '../includes/header.php';
?>

<style>
    .reports-page { --report-ink: #173042; --report-muted: #718395; --report-line: #e8eef3; --report-teal: #168b78; padding-bottom: 1rem; }
    .reports-page .report-heading { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-height: 176px; margin-bottom: 1.35rem; padding: 2rem 2.2rem; overflow: hidden; border: 0; border-radius: 20px; background: radial-gradient(ellipse at 78% 18%, rgba(103, 229, 191, .28), transparent 35%), linear-gradient(115deg, #123b48 0%, #126557 57%, #187d69 100%); box-shadow: 0 16px 34px rgba(16, 77, 75, .2); }
    .reports-page .report-heading::after { position: absolute; top: -125px; right: 9%; width: 310px; height: 310px; border: 1px solid rgba(224, 255, 245, .22); border-radius: 50%; box-shadow: 0 0 0 28px rgba(224, 255, 245, .06), 0 0 0 58px rgba(224, 255, 245, .045), 0 0 0 91px rgba(224, 255, 245, .025); content: ""; pointer-events: none; }
    .reports-page .report-heading-copy, .reports-page .period-chip { z-index: 1; }
    .reports-page .report-kicker { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: .65rem; color: #9cebd1; font-size: .7rem; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
    .reports-page .report-heading h1 { margin: 0 0 .45rem; color: #fff; font-size: clamp(1.65rem, 2.4vw, 2.2rem); font-weight: 800; letter-spacing: -.04em; }
    .reports-page .report-heading p { margin: 0; color: rgba(239, 255, 250, .76); }
    .reports-page .period-chip { display: inline-flex; align-items: center; gap: .6rem; padding: .8rem 1rem; border: 1px solid rgba(232, 255, 248, .25); border-radius: 12px; background: rgba(255, 255, 255, .13); color: #fff; font-size: .85rem; font-weight: 650; white-space: nowrap; box-shadow: 0 5px 14px rgba(10, 47, 51, .1); backdrop-filter: blur(12px); }
    .reports-page .report-card { overflow: hidden; border: 1px solid var(--report-line); border-radius: 16px; background: #fff; box-shadow: 0 7px 24px rgba(24, 53, 72, .045); transition: box-shadow .2s ease, transform .2s ease; }
    .reports-page .report-card:hover { box-shadow: 0 12px 28px rgba(24, 53, 72, .075); }
    .reports-page .report-card-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--report-line); }
    .reports-page .report-card-header .period-chip { border-color: #dcebe7; background: #f1faf7; color: #247765; box-shadow: none; backdrop-filter: none; }
    .reports-page .section-title { display: flex; align-items: center; gap: .7rem; margin: 0; color: var(--report-ink); font-size: 1rem; font-weight: 700; }
    .reports-page .section-icon { display: inline-grid; width: 36px; height: 36px; place-items: center; border: 1px solid #dff0eb; border-radius: 11px; background: linear-gradient(145deg, #f0faf7, #e6f5f0); color: var(--report-teal); }
    .reports-page .report-filter { margin-bottom: 1.25rem; }
    .reports-page .report-filter .report-card-header { background: linear-gradient(90deg, #fff, #fbfdfd); }
    .reports-page .report-filter .card-body { padding: 1.25rem; }
    .reports-page .form-label { margin-bottom: .45rem; color: #536777; font-size: .82rem; font-weight: 650; }
    .reports-page .form-control, .reports-page .form-select { min-height: 44px; border-color: #dce5ec; border-radius: 9px; }
    .reports-page .form-control:focus, .reports-page .form-select:focus { border-color: #75bbae; box-shadow: 0 0 0 .2rem rgba(22, 139, 120, .12); }
    .reports-page .filter-actions { display: flex; align-items: end; gap: .5rem; }
    .reports-page .filter-actions .btn { min-height: 44px; border-radius: 9px; font-weight: 650; }
    .reports-page .summary-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.35rem; }
    .reports-page .summary-card { position: relative; display: flex; align-items: center; gap: 1rem; min-height: 132px; padding: 1.2rem; overflow: hidden; border: 1px solid var(--report-line); border-radius: 16px; background: linear-gradient(145deg, #fff 48%, #fbfcfe); box-shadow: 0 7px 24px rgba(24, 53, 72, .045); transition: transform .2s ease, box-shadow .2s ease; }
    .reports-page .summary-card::before { position: absolute; top: 0; right: 0; left: 0; height: 3px; background: var(--summary-color); content: ""; }
    .reports-page .summary-card:hover { transform: translateY(-3px); box-shadow: 0 13px 26px rgba(24, 53, 72, .09); }
    .reports-page .summary-card::after { position: absolute; right: -24px; bottom: -38px; width: 110px; height: 110px; border-radius: 50%; background: var(--summary-tint); content: ""; }
    .reports-page .summary-icon { z-index: 1; display: grid; flex: 0 0 48px; width: 48px; height: 48px; place-items: center; border-radius: 14px; background: var(--summary-tint); color: var(--summary-color); font-size: 1.15rem; }
    .reports-page .summary-copy { z-index: 1; min-width: 0; }
    .reports-page .summary-label { margin-bottom: .35rem; color: var(--report-muted); font-size: .82rem; font-weight: 600; }
    .reports-page .summary-value { color: var(--report-ink); font-size: clamp(1.35rem, 2.2vw, 1.8rem); font-weight: 750; line-height: 1.15; }
    .reports-page .summary-foot { margin-top: .45rem; color: #97a6b1; font-size: .7rem; }
    .reports-page .report-tables { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem; }
    .reports-page .mini-chart { display: grid; gap: .7rem; margin: .35rem 1.1rem .6rem; padding: 1rem; border: 1px solid #edf2f4; border-radius: 12px; background: linear-gradient(145deg, #fbfdfd, #f7fafb); }
    .reports-page .chart-row { display: grid; grid-template-columns: 58px minmax(30px, 1fr) auto; align-items: center; gap: .65rem; }
    .reports-page .chart-label { overflow: hidden; color: #788b9a; font-size: .72rem; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
    .reports-page .chart-track { height: 8px; overflow: hidden; border-radius: 99px; background: #eaf0f2; }
    .reports-page .chart-bar { height: 100%; min-width: 3px; border-radius: inherit; background: linear-gradient(90deg, #32ae91, #168b78); box-shadow: 0 2px 6px rgba(22, 139, 120, .18); }
    .reports-page .chart-value { color: #385366; font-size: .74rem; font-weight: 750; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .reports-page .chart-caption { display: flex; align-items: center; gap: .4rem; margin: 0 1.15rem .15rem; color: var(--report-muted); font-size: .73rem; }
    .reports-page .chart-caption i { color: var(--report-teal); }
    .reports-page .analytics-grid { display: grid; grid-template-columns: minmax(0, 1.8fr) minmax(280px, 1fr); gap: 1.25rem; margin-bottom: 1.35rem; }
    .reports-page .trend-card { min-width: 0; }
    .reports-page .trend-chart { padding: .65rem 1.2rem .35rem; }
    .reports-page .trend-chart svg { display: block; width: 100%; height: auto; overflow: visible; }
    .reports-page .trend-chart .grid-line { stroke: #edf2f4; stroke-width: 1; }
    .reports-page .trend-chart .axis-label { fill: #8b9ba7; font-family: inherit; font-size: 11px; }
    .reports-page .trend-chart .axis-value { fill: #8b9ba7; font-family: inherit; font-size: 10px; }
    .reports-page .trend-chart .trend-area { fill: url(#trendFill); }
    .reports-page .trend-chart .trend-line { fill: none; stroke: #168b78; stroke-linecap: round; stroke-linejoin: round; stroke-width: 3; }
    .reports-page .trend-chart .trend-dot { fill: #fff; stroke: #168b78; stroke-width: 3; }
    .reports-page .trend-legend { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .2rem 1.45rem 1rem; color: var(--report-muted); font-size: .78rem; }
    .reports-page .trend-total { color: #16394a; font-size: 1.18rem; font-weight: 800; letter-spacing: -.02em; }
    .reports-page .trend-total small { margin-left: .25rem; color: #82939f; font-size: .72rem; font-weight: 650; letter-spacing: 0; }
    .reports-page .legend-label { display: inline-flex; align-items: center; gap: .45rem; }
    .reports-page .legend-dot { width: 8px; height: 8px; border-radius: 50%; background: #168b78; box-shadow: 0 0 0 4px #e6f5f0; }
    .reports-page .quick-ranges { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: .9rem; }
    .reports-page .quick-range { padding: .36rem .68rem; border: 1px solid #e2eaf0; border-radius: 999px; background: #fff; color: #657b8b; font-size: .74rem; font-weight: 650; transition: all .15s ease; }
    .reports-page .quick-range:hover { border-color: #a9d8cb; background: #eff9f6; color: #147965; }
    .reports-page .range-notice { display: flex; align-items: center; gap: .6rem; margin: -.25rem 0 1.1rem; padding: .8rem 1rem; border: 1px solid #cfe9df; border-radius: 12px; background: linear-gradient(90deg, #effaf5, #f7fcfa); color: #397366; font-size: .82rem; }
    .reports-page .range-notice i { color: #168b78; }
    .reports-page .customer-weight { min-width: 110px; }
    .reports-page .customer-weight-value { margin-bottom: .35rem; color: #334b5b; font-size: .78rem; font-weight: 700; text-align: right; }
    .reports-page .customer-weight-track { height: 5px; overflow: hidden; border-radius: 99px; background: #edf2f4; }
    .reports-page .customer-weight-bar { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #6bd0b1, #168b78); }
    .reports-page .top-customers { align-self: stretch; }
    .reports-page .top-customer-list { display: grid; gap: .25rem; padding: .55rem 1.15rem .85rem; }
    .reports-page .top-customer { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: .7rem; padding: .8rem .35rem; border-bottom: 1px solid #eef2f5; }
    .reports-page .top-customer:last-child { border-bottom: 0; }
    .reports-page .top-customer-rank { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid #e8eef2; border-radius: 11px; background: #f8fafb; color: #647c8b; font-size: .78rem; font-weight: 800; }
    .reports-page .top-customer:first-child .top-customer-rank { border-color: #f3dfac; background: #fff7df; color: #a87617; }
    .reports-page .top-customer:nth-child(2) .top-customer-rank { border-color: #e4e8ed; background: #f4f6f8; color: #607384; }
    .reports-page .top-customer:nth-child(3) .top-customer-rank { border-color: #f1d8c6; background: #fff1e8; color: #ae7045; }
    .reports-page .top-customer-name { overflow: hidden; color: #294455; font-size: .8rem; font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .reports-page .top-customer-docs { margin-top: .2rem; color: #91a0ab; font-size: .7rem; }
    .reports-page .top-customer-weight { color: #1d6659; font-size: .76rem; font-weight: 750; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .reports-page .top-customers-foot { margin: 0 1.15rem 1rem; padding: .65rem .75rem; border-radius: 10px; background: #f2f8f7; color: #58766f; font-size: .72rem; }
    .reports-page .table-wrap { padding: .25rem 1.1rem .7rem; }
    .reports-page .table { margin: 0; --bs-table-hover-bg: #f5faf9; }
    .reports-page .table thead th { padding: .8rem .7rem; border-bottom: 1px solid #e5edf2; background: #f7f9fb; color: #748596; font-size: .68rem; font-weight: 750; letter-spacing: .055em; text-transform: uppercase; white-space: nowrap; }
    .reports-page .table tbody td, .reports-page .table tfoot td { padding: .85rem .7rem; border-color: #edf1f4; color: #334b5b; vertical-align: middle; }
    .reports-page .table tbody tr:last-child td { border-bottom: 0; }
    .reports-page .table tfoot td { border-top: 1px solid #e3ebf0; background: #f7f9fb; color: var(--report-ink); }
    .reports-page .numeric-cell { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
    .reports-page .customer-name { color: var(--report-ink); font-weight: 650; }
    .reports-page .rank-pill { display: inline-grid; width: 28px; height: 28px; place-items: center; border-radius: 9px; background: #f0f4f7; color: #607587; font-size: .78rem; font-weight: 700; }
    .reports-page .rank-pill.top-rank { background: #e6f5f0; color: #168b78; }
    .reports-page .empty-report { padding: 2rem 1rem; color: var(--report-muted); text-align: center; }
    .reports-page .empty-report i { display: block; margin-bottom: .7rem; color: #b8c7d2; font-size: 1.7rem; }
    .reports-page .export-btn { border-radius: 9px; font-weight: 650; white-space: nowrap; }
    @media (max-width: 1100px) { .reports-page .summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 1000px) { .reports-page .analytics-grid { grid-template-columns: 1fr; } }
    @media (max-width: 900px) { .reports-page .report-tables { grid-template-columns: 1fr; } }
    @media (max-width: 575px) { .reports-page .report-heading { align-items: flex-start; flex-direction: column; min-height: 0; padding: 1.35rem; } .reports-page .period-chip { white-space: normal; } .reports-page .summary-grid { grid-template-columns: 1fr; gap: .75rem; } .reports-page .summary-card { min-height: 96px; } .reports-page .report-card-header { align-items: flex-start; flex-direction: column; } .reports-page .export-btn { width: 100%; } .reports-page .filter-actions { align-items: stretch; } .reports-page .filter-actions .btn { flex: 1; } .reports-page .mini-chart { margin-right: .75rem; margin-left: .75rem; padding: .8rem; } .reports-page .trend-chart { padding: .4rem .5rem; } .reports-page .trend-legend { align-items: flex-start; flex-direction: column; padding-right: 1rem; padding-left: 1rem; } .reports-page .analytics-grid { gap: .85rem; } }
</style>

<div class="reports-page">
    <div class="report-heading">
        <div class="report-heading-copy">
            <div class="report-kicker"><i class="fas fa-sparkles"></i>Phân tích hoạt động</div>
            <h1>Báo cáo thống kê</h1>
            <p>Theo dõi khối lượng thu gom và hiệu quả theo khách hàng.</p>
        </div>
        <div class="period-chip"><i class="far fa-calendar-days"></i><span><?php echo formatDate($fromDate); ?> – <?php echo formatDate($toDate); ?></span></div>
    </div>

    <?php if ($reportRangeNotice !== ''): ?>
    <div class="range-notice"><i class="fas fa-circle-info"></i><?php echo sanitize($reportRangeNotice); ?></div>
    <?php endif; ?>

    <section class="report-card report-filter" aria-label="Bộ lọc báo cáo">
        <div class="report-card-header">
            <h2 class="section-title"><span class="section-icon"><i class="fas fa-sliders"></i></span>Điều kiện báo cáo</h2>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="from-date">Từ ngày</label>
                    <input id="from-date" type="date" name="from_date" class="form-control" value="<?php echo sanitize($fromDate); ?>">
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="to-date">Đến ngày</label>
                    <input id="to-date" type="date" name="to_date" class="form-control" value="<?php echo sanitize($toDate); ?>">
                </div>
                <div class="col-lg-3">
                    <label class="form-label" for="customer-id">Khách hàng</label>
                    <select id="customer-id" name="customer_id" class="form-select">
                        <option value="">Tất cả khách hàng</option>
                        <?php foreach ($customers as $c): ?>
                        <option value="<?php echo (int) $c['id']; ?>" <?php echo $customerId == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($c['company_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-lg-3 filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter me-2"></i>Lọc báo cáo</button>
                    <a href="<?php echo BASE_URL; ?>/pages/reports.php" class="btn btn-light border" title="Xóa bộ lọc" aria-label="Xóa bộ lọc"><i class="fas fa-rotate-left"></i></a>
                </div>
            </form>
            <div class="quick-ranges" aria-label="Khoảng thời gian nhanh">
                <button type="button" class="quick-range" data-range="7">7 ngày</button>
                <button type="button" class="quick-range" data-range="30">30 ngày</button>
                <button type="button" class="quick-range" data-range="month">Tháng này</button>
                <button type="button" class="quick-range" data-range="last-month">Tháng trước</button>
            </div>
        </div>
    </section>

    <section class="summary-grid" aria-label="Tổng quan báo cáo">
        <div class="summary-card" style="--summary-tint:#eaf1ff;--summary-color:#4268ce">
            <div class="summary-icon"><i class="fas fa-file-lines"></i></div>
            <div class="summary-copy">
                <div class="summary-label">Tổng số chứng từ</div>
                <div class="summary-value"><?php echo formatNumber($totalDocs); ?></div>
                <div class="summary-foot">Trong khoảng thời gian đã chọn</div>
            </div>
        </div>
        <div class="summary-card" style="--summary-tint:#e8f7f1;--summary-color:#168b78">
            <div class="summary-icon"><i class="fas fa-weight-hanging"></i></div>
            <div class="summary-copy">
                <div class="summary-label">Tổng khối lượng</div>
                <div class="summary-value"><?php echo formatNumber($totalWeight, 2); ?><small class="ms-1 fs-6 fw-semibold">kg</small></div>
                <div class="summary-foot">Khối lượng ghi nhận trên chứng từ</div>
            </div>
        </div>
        <div class="summary-card" style="--summary-tint:#f0ebff;--summary-color:#7454c8">
            <div class="summary-icon"><i class="fas fa-building"></i></div>
            <div class="summary-copy">
                <div class="summary-label">Khách hàng phát sinh</div>
                <div class="summary-value"><?php echo formatNumber($totalReportedCustomers); ?></div>
                <div class="summary-foot">Có phát sinh thu gom trong kỳ</div>
            </div>
        </div>
        <div class="summary-card" style="--summary-tint:#fff3e6;--summary-color:#d68a2e">
            <div class="summary-icon"><i class="fas fa-scale-balanced"></i></div>
            <div class="summary-copy">
                <div class="summary-label">Trung bình mỗi chứng từ</div>
                <div class="summary-value"><?php echo formatNumber($totalWeight / max($totalDocs, 1), 2); ?><small class="ms-1 fs-6 fw-semibold">kg</small></div>
                <div class="summary-foot">Tổng khối lượng chia cho số chứng từ</div>
            </div>
        </div>
    </section>

    <section class="analytics-grid" aria-label="Phân tích hiệu suất">
    <article class="report-card trend-card">
        <div class="report-card-header">
            <div>
                <h2 class="section-title"><span class="section-icon"><i class="fas fa-chart-line"></i></span>Xu hướng khối lượng thu gom</h2>
                <div class="text-muted small mt-1">14 ngày có dữ liệu gần nhất trong phạm vi báo cáo</div>
            </div>
            <div class="text-end"><div class="trend-total"><?php echo formatNumber($totalWeight, 2); ?><small>kg tổng kỳ</small></div><div class="text-muted small">Tổng khối lượng</div></div>
        </div>
        <?php if (empty($dailyChartData)): ?>
        <div class="empty-report"><i class="fas fa-chart-line"></i>Chưa có dữ liệu để vẽ biểu đồ trong khoảng ngày này.</div>
        <?php else: ?>
        <div class="trend-chart">
            <svg viewBox="0 0 <?php echo $chartWidth; ?> <?php echo $chartHeight; ?>" role="img" aria-label="Biểu đồ đường khối lượng thu gom theo ngày">
                <defs>
                    <linearGradient id="trendFill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="#36b795" stop-opacity=".25"></stop>
                        <stop offset="100%" stop-color="#36b795" stop-opacity=".015"></stop>
                    </linearGradient>
                </defs>
                <?php for ($gridIndex = 0; $gridIndex < 4; $gridIndex++):
                    $gridY = $chartTop + ($gridIndex * ($chartBottom - $chartTop) / 3);
                    $gridWeight = $maxDailyWeight * (1 - ($gridIndex / 3));
                ?>
                <line class="grid-line" x1="<?php echo $chartLeft; ?>" y1="<?php echo $gridY; ?>" x2="<?php echo $chartRight; ?>" y2="<?php echo $gridY; ?>"></line>
                <text class="axis-value" x="48" y="<?php echo $gridY - 5; ?>" text-anchor="end"><?php echo sanitize(formatNumber($gridWeight)); ?></text>
                <?php endfor; ?>
                <path class="trend-area" d="<?php echo $dailyAreaPath; ?>"></path>
                <polyline class="trend-line" points="<?php echo $dailyPolyline; ?>"></polyline>
                <?php foreach ($dailyPoints as $index => $point): ?>
                <circle class="trend-dot" cx="<?php echo number_format($point['x'], 1, '.', ''); ?>" cy="<?php echo number_format($point['y'], 1, '.', ''); ?>" r="5">
                    <title><?php echo sanitize(formatDate($dailyChartData[$index]['collection_date']) . ': ' . formatNumber($dailyChartData[$index]['total_weight'], 2) . ' kg'); ?></title>
                </circle>
                <?php endforeach; ?>
                <?php foreach ($dailyChartData as $index => $row):
                    $labelX = count($dailyChartData) === 1 ? ($chartLeft + $chartRight) / 2 : $chartLeft + ($index * ($chartRight - $chartLeft) / (count($dailyChartData) - 1));
                ?>
                <?php if ($index === 0 || $index === count($dailyChartData) - 1 || $index % max((int) ceil(count($dailyChartData) / 6), 1) === 0): ?>
                <text class="axis-label" x="<?php echo number_format($labelX, 1, '.', ''); ?>" y="222" text-anchor="middle"><?php echo sanitize(date('d/m', strtotime($row['collection_date']))); ?></text>
                <?php endif; ?>
                <?php endforeach; ?>
            </svg>
        </div>
        <div class="trend-legend">
            <span class="legend-label"><span class="legend-dot"></span>Khối lượng thu gom mỗi ngày</span>
            <span><?php echo count($dailyChartData) === 1 ? '1 ngày có dữ liệu' : formatDate($dailyChartData[0]['collection_date']) . ' — ' . formatDate(end($dailyChartData)['collection_date']); ?></span>
        </div>
        <?php endif; ?>
    </article>

    <aside class="report-card top-customers">
        <div class="report-card-header">
            <div>
                <h2 class="section-title"><span class="section-icon"><i class="fas fa-trophy"></i></span>Khách hàng nổi bật</h2>
                <div class="text-muted small mt-1">Xếp theo khối lượng thu gom</div>
            </div>
            <span class="badge rounded-pill text-bg-light"><?php echo formatNumber(min(5, count($reportData))); ?> / <?php echo formatNumber($totalReportedCustomers); ?></span>
        </div>
        <?php if (empty($reportData)): ?>
        <div class="empty-report"><i class="fas fa-users"></i>Chưa có khách hàng phát sinh.</div>
        <?php else: ?>
        <div class="top-customer-list">
            <?php foreach (array_slice($reportData, 0, 5) as $index => $customer): ?>
            <div class="top-customer">
                <span class="top-customer-rank"><?php echo str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
                <div>
                    <div class="top-customer-name" title="<?php echo sanitize($customer['company_name']); ?>"><?php echo sanitize($customer['company_name']); ?></div>
                    <div class="top-customer-docs"><?php echo formatNumber($customer['total_docs']); ?> chứng từ</div>
                </div>
                <span class="top-customer-weight"><?php echo formatNumber($customer['total_weight'], 2); ?> kg</span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="top-customers-foot"><i class="fas fa-circle-info me-1"></i>Số liệu được xếp hạng theo khối lượng trong kỳ lọc.</div>
        <?php endif; ?>
    </aside>
    </section>

    <section class="report-tables" aria-label="Thống kê theo thời gian">
        <article class="report-card">
            <div class="report-card-header">
                <h2 class="section-title"><span class="section-icon"><i class="fas fa-calendar-day"></i></span>Theo ngày</h2>
                <span class="text-muted small"><?php echo formatNumber(count($dailyReportData)); ?> ngày có dữ liệu</span>
            </div>
            <?php if (!empty($dailyChartData)): ?>
            <div class="mini-chart" role="img" aria-label="Biểu đồ khối lượng thu gom theo ngày">
                <?php foreach ($dailyChartData as $row):
                    $barWidth = $maxDailyWeight > 0 ? max(3, ($row['total_weight'] / $maxDailyWeight) * 100) : 0;
                ?>
                <div class="chart-row">
                    <span class="chart-label"><?php echo sanitize(date('d/m', strtotime($row['collection_date']))); ?></span>
                    <div class="chart-track"><div class="chart-bar" style="width:<?php echo number_format($barWidth, 2, '.', ''); ?>%"></div></div>
                    <span class="chart-value"><?php echo formatNumber($row['total_weight'], 2); ?> kg</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-caption"><i class="fas fa-circle"></i>Khối lượng thu gom · 14 ngày có dữ liệu gần nhất</div>
            <?php endif; ?>
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Ngày thu gom</th><th class="text-end">Số CT</th><th class="text-end">Khối lượng (kg)</th></tr></thead>
                        <tbody>
                            <?php if (empty($dailyReportData)): ?>
                            <tr><td colspan="3"><div class="empty-report"><i class="far fa-calendar-xmark"></i>Không có dữ liệu trong khoảng ngày đã chọn.</div></td></tr>
                            <?php else: foreach ($dailyReportData as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo formatDate($row['collection_date']); ?></td>
                                <td class="numeric-cell"><?php echo formatNumber($row['total_docs']); ?></td>
                                <td class="numeric-cell fw-semibold"><?php echo formatNumber($row['total_weight'], 2); ?> kg</td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>

        <article class="report-card">
            <div class="report-card-header">
                <h2 class="section-title"><span class="section-icon"><i class="fas fa-calendar-alt"></i></span>Theo tháng</h2>
                <span class="text-muted small"><?php echo formatNumber(count($monthlyReportData)); ?> tháng có dữ liệu</span>
            </div>
            <?php if (!empty($monthlyChartData)): ?>
            <div class="mini-chart" role="img" aria-label="Biểu đồ khối lượng thu gom theo tháng">
                <?php foreach ($monthlyChartData as $row):
                    $barWidth = $maxMonthlyWeight > 0 ? max(3, ($row['total_weight'] / $maxMonthlyWeight) * 100) : 0;
                ?>
                <div class="chart-row">
                    <span class="chart-label"><?php echo sanitize(date('m/y', strtotime($row['collection_month'] . '-01'))); ?></span>
                    <div class="chart-track"><div class="chart-bar" style="width:<?php echo number_format($barWidth, 2, '.', ''); ?>%"></div></div>
                    <span class="chart-value"><?php echo formatNumber($row['total_weight'], 2); ?> kg</span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="chart-caption"><i class="fas fa-circle"></i>Khối lượng thu gom · 6 tháng gần nhất</div>
            <?php endif; ?>
            <div class="table-wrap">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Tháng</th><th class="text-end">Số CT</th><th class="text-end">Khối lượng (kg)</th></tr></thead>
                        <tbody>
                            <?php if (empty($monthlyReportData)): ?>
                            <tr><td colspan="3"><div class="empty-report"><i class="far fa-calendar-xmark"></i>Không có dữ liệu trong khoảng ngày đã chọn.</div></td></tr>
                            <?php else: foreach ($monthlyReportData as $row): ?>
                            <tr>
                                <td class="fw-semibold"><?php echo sanitize(date('m/Y', strtotime($row['collection_month'] . '-01'))); ?></td>
                                <td class="numeric-cell"><?php echo formatNumber($row['total_docs']); ?></td>
                                <td class="numeric-cell fw-semibold"><?php echo formatNumber($row['total_weight'], 2); ?> kg</td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>

    <section class="report-card">
        <div class="report-card-header">
            <div>
                <h2 class="section-title"><span class="section-icon"><i class="fas fa-building"></i></span>Chi tiết theo khách hàng</h2>
                <div class="text-muted small mt-1"><?php echo formatNumber(count($reportData)); ?> khách hàng có chứng từ trong kỳ</div>
            </div>
            <button onclick="exportTableToCsv('reportTable', 'bao-cao-khach-hang.csv')" class="btn btn-success btn-sm export-btn">
                <i class="fas fa-file-export me-2"></i>Xuất Excel
            </button>
        </div>
        <div class="table-wrap">
            <div class="table-responsive">
                <table class="table table-hover" id="reportTable">
                    <thead><tr><th>STT</th><th>Khách hàng</th><th class="text-end">Số chứng từ</th><th class="text-end">Khối lượng (kg)</th><th class="text-end">Trung bình (kg/CT)</th></tr></thead>
                    <tbody>
                        <?php if (empty($reportData)): ?>
                        <tr><td colspan="5"><div class="empty-report"><i class="fas fa-chart-simple"></i>Chưa có chứng từ phù hợp với bộ lọc.</div></td></tr>
                        <?php else: foreach ($reportData as $idx => $row): ?>
                        <tr>
                            <td><span class="rank-pill <?php echo $idx < 3 ? 'top-rank' : ''; ?>"><?php echo $idx + 1; ?></span></td>
                            <td class="customer-name"><?php echo sanitize($row['company_name']); ?></td>
                            <td class="numeric-cell"><?php echo formatNumber($row['total_docs']); ?></td>
                            <td class="numeric-cell">
                                <div class="customer-weight">
                                    <div class="customer-weight-value"><?php echo formatNumber($row['total_weight'], 2); ?> kg</div>
                                    <div class="customer-weight-track"><div class="customer-weight-bar" style="width:<?php echo $maxCustomerWeight > 0 ? number_format(($row['total_weight'] / $maxCustomerWeight) * 100, 2, '.', '') : '0'; ?>%"></div></div>
                                </div>
                            </td>
                            <td class="numeric-cell"><?php echo formatNumber($row['total_weight'] / max($row['total_docs'], 1), 2); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                    <?php if (!empty($reportData)): ?>
                    <tfoot><tr class="fw-bold"><td colspan="2">TỔNG CỘNG</td><td class="numeric-cell"><?php echo formatNumber($totalDocs); ?></td><td class="numeric-cell"><?php echo formatNumber($totalWeight, 2); ?></td><td class="numeric-cell">—</td></tr></tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
(() => {
    const fromInput = document.getElementById('from-date');
    const toInput = document.getElementById('to-date');
    if (!fromInput || !toInput) return;

    const formatInputDate = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    document.querySelectorAll('.quick-range').forEach(button => {
        button.addEventListener('click', () => {
            const today = new Date();
            const range = button.dataset.range;
            let start = new Date(today);
            const end = new Date(today);

            if (range === '7' || range === '30') {
                start.setDate(start.getDate() - (Number(range) - 1));
            } else if (range === 'month') {
                start = new Date(today.getFullYear(), today.getMonth(), 1);
            } else if (range === 'last-month') {
                start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                end.setDate(0);
            }

            fromInput.value = formatInputDate(start);
            toInput.value = formatInputDate(end);
            fromInput.form.requestSubmit();
        });
    });
})();
</script>

<?php include '../includes/footer.php'; ?>
