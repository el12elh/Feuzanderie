<?php
/**
 * Dashboard partial.
 *
 * Needs from the parent page: $pdo (PDO) and Chart.js v3+ already loaded.
 * Business rules live in the CONFIG block; everything below reads from it.
 */

/* ------------------------------------------------------------------
 * CONFIG
 * ------------------------------------------------------------------ */
$TARGET            = 10000;            // "Road to Ibiza 🇪🇸" goal, in €
$TOPUP_INCOME      = [1, 2, 3, 4];     // Cash, SumUp, Stripe, Transfer
$TOPUP_REFUND      = 5;                // wallet_topup type used for refunds
$INTERNAL_CUSTOMER = 1;                // "Tournée de l'AMIKALE" account
$MEMBER_AFTER_ID   = 3;                // customers with ID > 3 are real members
$EXCLUDED_PRODUCTS = [7, 9];           // left out of the top-members ranking
$SEASON_START_MONTH = 8;                // seasons run August to July
$STAGES            = ['Packing', 'At the Gate', 'Boarding', 'In the Air'];

// Manual corrections to net sales, keyed 'YYYY-MM'.
// (Replaces the old hardcoded "+1400 on January 2026" in the middle of the code.)
$SALES_ADJUSTMENTS = ['2026-01' => 1400];

/* ------------------------------------------------------------------
 * HELPERS
 * ------------------------------------------------------------------ */
$fetch = function (string $sql, array $params = []) use ($pdo): array {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log('[dashboard] ' . $e->getMessage());
        return [];   // one failing query must not take the whole page down
    }
};
$ids = fn(array $a): string => implode(',', array_map('intval', $a));
$h   = fn($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$eur = fn($n): string => number_format((float) $n, 0, ',', "\u{202F}") . "\u{00A0}€";
$cumulate = function (array $values): array {
    $sum = 0;
    return array_map(function ($v) use (&$sum) {
        if ($v === null) return null;
        $sum += $v;
        return round($sum, 2);
    }, $values);
};

$incomeIn   = $ids($TOPUP_INCOME);
$MONTHS     = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$today      = new DateTimeImmutable('today');
$year       = (int) $today->format('Y');
$prevYear   = $year - 1;
$curMonth   = (int) $today->format('n');
$yearStart  = "$year-01-01";
$yearEnd    = ($year + 1) . '-01-01';
$prevStart  = "$prevYear-01-01";

/* ------------------------------------------------------------------
 * DATA
 * ------------------------------------------------------------------ */

// Top-ups by payment method, current year
$methodRows = $fetch("
    SELECT rtt.NAME AS METHOD, SUM(wt.AMOUNT) AS REVENUE
    FROM wallet_topup wt
    JOIN ref_topup_type rtt ON wt.ID_TOPUP_TYPE = rtt.ID_TOPUP_TYPE
    WHERE wt.ID_TOPUP_TYPE IN ($incomeIn)
      AND wt.CREATED_AT >= ? AND wt.CREATED_AT < ?
    GROUP BY rtt.ID_TOPUP_TYPE, rtt.NAME
    ORDER BY rtt.ID_TOPUP_TYPE
", [$yearStart, $yearEnd]);

// Net sales + active customers, previous and current year (full calendar years,
// so the year-over-year comparison is complete)
$salesRows = $fetch("
    WITH sales AS (
        SELECT YEAR(created_at) AS year_tr, MONTH(created_at) AS month_tr,
               COUNT(DISTINCT CASE WHEN id_customer > ? THEN id_customer END) AS cnt_customer,
               SUM(total) AS total_sales
        FROM transactions
        WHERE created_at >= ? AND created_at < ? AND id_customer != ?
        GROUP BY YEAR(created_at), MONTH(created_at)
    ),
    refunds AS (
        SELECT YEAR(created_at) AS year_tr, MONTH(created_at) AS month_tr, SUM(amount) AS refund
        FROM wallet_topup
        WHERE created_at >= ? AND created_at < ? AND id_topup_type = ?
        GROUP BY YEAR(created_at), MONTH(created_at)
    )
    SELECT s.year_tr AS YEAR_TR, s.month_tr AS MONTH_TR, s.cnt_customer AS CNT_CUSTOMER,
           s.total_sales - IFNULL(r.refund, 0) AS NET_SALES
    FROM sales s
    LEFT JOIN refunds r ON s.year_tr = r.year_tr AND s.month_tr = r.month_tr
    ORDER BY s.year_tr, s.month_tr
", [$MEMBER_AFTER_ID, $prevStart, $yearEnd, $INTERNAL_CUSTOMER, $prevStart, $yearEnd, $TOPUP_REFUND]);

// Top-ups over the last 7 days that had activity
$weekRows = $fetch("
    SELECT rtt.NAME AS METHOD, DATE(wt.CREATED_AT) AS DAY_TUP, SUM(wt.AMOUNT) AS REVENUE
    FROM wallet_topup wt
    JOIN ref_topup_type rtt ON wt.ID_TOPUP_TYPE = rtt.ID_TOPUP_TYPE
    JOIN (
        SELECT DISTINCT DATE(CREATED_AT) AS active_date
        FROM wallet_topup
        WHERE ID_TOPUP_TYPE IN ($incomeIn)
        ORDER BY active_date DESC
        LIMIT 7
    ) last_seven ON DATE(wt.CREATED_AT) = last_seven.active_date
    WHERE wt.ID_TOPUP_TYPE IN ($incomeIn)
    GROUP BY DATE(wt.CREATED_AT), rtt.ID_TOPUP_TYPE, rtt.NAME
    ORDER BY DATE(wt.CREATED_AT), rtt.ID_TOPUP_TYPE
");

// Top 20 members by net spend, current year
$excludedClause = $EXCLUDED_PRODUCTS ? 'AND tr.ID_PRODUCT NOT IN (' . $ids($EXCLUDED_PRODUCTS) . ')' : '';
$topRows = $fetch("
    SELECT sub.CUSTOMER, (sub.TOTAL_ORDER_VALUE - COALESCE(ref.TOTAL_REFUND, 0)) AS NET_VALUE
    FROM (
        SELECT c.ID_CUSTOMER,
               CONCAT(LEFT(c.FIRST_NAME, 1), '. ', c.LAST_NAME) AS CUSTOMER,
               SUM(p.PRICE * tr.QUANTITY) AS TOTAL_ORDER_VALUE
        FROM transactions tr
        JOIN customers c ON tr.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN ref_product p ON tr.ID_PRODUCT = p.ID_PRODUCT
        WHERE tr.CREATED_AT >= ? AND tr.CREATED_AT < ?
          AND c.ID_CUSTOMER > ?
          $excludedClause
        GROUP BY c.ID_CUSTOMER, c.FIRST_NAME, c.LAST_NAME
    ) sub
    LEFT JOIN (
        SELECT ID_CUSTOMER, SUM(AMOUNT) AS TOTAL_REFUND
        FROM wallet_topup
        WHERE CREATED_AT >= ? AND CREATED_AT < ? AND ID_TOPUP_TYPE = ?
        GROUP BY ID_CUSTOMER
    ) ref ON sub.ID_CUSTOMER = ref.ID_CUSTOMER
    ORDER BY NET_VALUE DESC
    LIMIT 20
", [$yearStart, $yearEnd, $MEMBER_AFTER_ID, $yearStart, $yearEnd, $TOPUP_REFUND]);

// "Tournée de l'AMIKALE" (internal account), current year
$lossRows = $fetch("
    SELECT MONTH(CREATED_AT) AS MONTH_TR, -SUM(TOTAL) AS TOTAL_LOSS
    FROM transactions
    WHERE CREATED_AT >= ? AND CREATED_AT < ? AND ID_CUSTOMER = ?
    GROUP BY MONTH(CREATED_AT)
", [$yearStart, $yearEnd, $INTERNAL_CUSTOMER]);

// Sales by weekday, last 90 days
$weekdayRows = $fetch("
    SELECT WEEKDAY(created_at) AS DOW, SUM(total) AS SALES
    FROM transactions
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY) AND id_customer != ?
    GROUP BY WEEKDAY(created_at)
", [$INTERNAL_CUSTOMER]);

// Daily data for the week-by-week views (this year and last year, so any ISO week can be picked)
$weeklyFrom = $prevStart;
$weeklyTo   = $today->modify('+1 day')->format('Y-m-d');
$methodList = array_column($fetch("
    SELECT NAME FROM ref_topup_type
    WHERE ID_TOPUP_TYPE IN ($incomeIn)
    ORDER BY ID_TOPUP_TYPE
"), 'NAME');
$dailyTopupRows = $fetch("
    SELECT DATE(wt.CREATED_AT) AS DAY_TUP, rtt.NAME AS METHOD, SUM(wt.AMOUNT) AS REVENUE
    FROM wallet_topup wt
    JOIN ref_topup_type rtt ON wt.ID_TOPUP_TYPE = rtt.ID_TOPUP_TYPE
    WHERE wt.ID_TOPUP_TYPE IN ($incomeIn)
      AND wt.CREATED_AT >= ? AND wt.CREATED_AT < ?
    GROUP BY DATE(wt.CREATED_AT), rtt.ID_TOPUP_TYPE, rtt.NAME
", [$weeklyFrom, $weeklyTo]);
$dailySalesRows = $fetch("
    SELECT DATE(created_at) AS DAY_TR, SUM(total) AS SALES
    FROM transactions
    WHERE created_at >= ? AND created_at < ? AND id_customer != ?
    GROUP BY DATE(created_at)
", [$weeklyFrom, $weeklyTo, $INTERNAL_CUSTOMER]);

// Cash on hand: all income minus all purchases
$cashRow    = $fetch("
    SELECT (SELECT COALESCE(SUM(AMOUNT), 0) FROM wallet_topup WHERE ID_TOPUP_TYPE IN ($incomeIn))
         - (SELECT COALESCE(SUM(AMOUNT), 0) FROM purchases) AS TOTAL_CASH
");
$totalCash = round((float) ($cashRow[0]['TOTAL_CASH'] ?? 0), 2);

// Monthly income and expenses over the whole history (for the season averages)
$historyRows = $fetch("
    SELECT YEAR_TR, MONTH_TR, SUM(INCOME) AS INCOME, SUM(EXPENSES) AS EXPENSES
    FROM (
        SELECT YEAR(CREATED_AT) AS YEAR_TR, MONTH(CREATED_AT) AS MONTH_TR,
               SUM(AMOUNT) AS INCOME, 0 AS EXPENSES
        FROM wallet_topup
        WHERE ID_TOPUP_TYPE IN ($incomeIn)
        GROUP BY YEAR(CREATED_AT), MONTH(CREATED_AT)
        UNION ALL
        SELECT YEAR(CREATED_AT), MONTH(CREATED_AT), 0, SUM(AMOUNT)
        FROM purchases
        GROUP BY YEAR(CREATED_AT), MONTH(CREATED_AT)
    ) AS monthly_history
    GROUP BY YEAR_TR, MONTH_TR
");

// Rolling 12-month cash flow
$cfStart    = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
$cfStartStr = $cfStart->format('Y-m-d');
$cfEndStr   = (new DateTimeImmutable('first day of next month'))->format('Y-m-d');
$cfRows = $fetch("
    SELECT YEAR_TR, MONTH_TR, SUM(INCOME) AS INCOME, SUM(EXPENSES) AS EXPENSES
    FROM (
        SELECT YEAR(CREATED_AT) AS YEAR_TR, MONTH(CREATED_AT) AS MONTH_TR,
               SUM(AMOUNT) AS INCOME, 0 AS EXPENSES
        FROM wallet_topup
        WHERE ID_TOPUP_TYPE IN ($incomeIn) AND CREATED_AT >= ? AND CREATED_AT < ?
        GROUP BY YEAR(CREATED_AT), MONTH(CREATED_AT)
        UNION ALL
        SELECT YEAR(CREATED_AT), MONTH(CREATED_AT), 0, SUM(AMOUNT)
        FROM purchases
        WHERE CREATED_AT >= ? AND CREATED_AT < ?
        GROUP BY YEAR(CREATED_AT), MONTH(CREATED_AT)
    ) AS monthly_cashflow
    GROUP BY YEAR_TR, MONTH_TR
    ORDER BY YEAR_TR, MONTH_TR
", [$cfStartStr, $cfEndStr, $cfStartStr, $cfEndStr]);

/* ------------------------------------------------------------------
 * SHAPE THE DATA FOR THE CHARTS
 * ------------------------------------------------------------------ */

// Monthly sales / members, current vs previous year
$salesCY = $salesPY = array_fill(0, 12, 0.0);
$membersCY = $membersPY = array_fill(0, 12, 0);
foreach ($salesRows as $r) {
    $i = (int) $r['MONTH_TR'] - 1;
    $y = (int) $r['YEAR_TR'];
    if ($y === $year) {
        $salesCY[$i]   = round((float) $r['NET_SALES'], 2);
        $membersCY[$i] = (int) $r['CNT_CUSTOMER'];
    } elseif ($y === $prevYear) {
        $salesPY[$i]   = round((float) $r['NET_SALES'], 2);
        $membersPY[$i] = (int) $r['CNT_CUSTOMER'];
    }
}
foreach ($SALES_ADJUSTMENTS as $key => $amount) {
    [$y, $m] = array_map('intval', explode('-', $key));
    if ($y === $year)     $salesCY[$m - 1] += $amount;
    if ($y === $prevYear) $salesPY[$m - 1] += $amount;
}

// Year-to-date figures (computed before future months are blanked out)
$ytdSales  = array_sum($salesCY);
$lastFull  = $curMonth - 1;                       // last complete month of this year
$ytdDelta  = null;
$ytdRange  = '';
if ($lastFull >= 1 && array_sum(array_slice($salesPY, 0, $lastFull)) > 0) {
    $cy = array_sum(array_slice($salesCY, 0, $lastFull));
    $py = array_sum(array_slice($salesPY, 0, $lastFull));
    $ytdDelta = ($cy / $py - 1) * 100;
    $ytdRange = $lastFull === 1 ? $MONTHS[0] : $MONTHS[0] . '–' . $MONTHS[$lastFull - 1];
}
$membersNow  = $membersCY[$curMonth - 1];
$membersPrev = $curMonth > 1 ? $membersCY[$curMonth - 2] : $membersPY[11];

// Cumulative sales, then blank future months so lines stop at today
$cumSalesCY = $cumSalesPY = [];
$cumSalesPY = $cumulate($salesPY);
$cumSalesCY = $cumulate($salesCY);
for ($i = $curMonth; $i < 12; $i++) {
    $salesCY[$i]     = null;
    $membersCY[$i]   = null;
    $cumSalesCY[$i]  = null;
}

// Internal-account loss
$lossCY = array_fill(0, 12, 0.0);
foreach ($lossRows as $r) {
    $lossCY[(int) $r['MONTH_TR'] - 1] = round((float) $r['TOTAL_LOSS'], 2);
}
for ($i = $curMonth; $i < 12; $i++) $lossCY[$i] = null;

// Cash flow
$cfLabels = $cfIndex = [];
$income = $expenses = $cfNet = $cfCumulative = $cfOpening = array_fill(0, 12, 0.0);
for ($i = 0; $i < 12; $i++) {
    $month = $cfStart->modify("+$i months");
    $cfLabels[] = $month->format('M Y');
    $cfIndex[$month->format('Y-n')] = $i;
}
foreach ($cfRows as $r) {
    $key = $r['YEAR_TR'] . '-' . (int) $r['MONTH_TR'];
    if (!isset($cfIndex[$key])) continue;
    $income[$cfIndex[$key]]   = (float) $r['INCOME'];
    $expenses[$cfIndex[$key]] = (float) $r['EXPENSES'];
}
$running = 0.0;
for ($i = 0; $i < 12; $i++) {
    $cfOpening[$i]    = $running;
    $cfNet[$i]        = $income[$i] - $expenses[$i];
    $running         += $cfNet[$i];
    $cfCumulative[$i] = $running;
}

// Average monthly net per season: net result of the complete months divided by their number.
// The running month is left out, and a season that has just started only counts what has elapsed.
$monthIdx = fn(int $y, int $m): int => $y * 12 + ($m - 1);
$netByIdx = [];
foreach ($historyRows as $r) {
    $netByIdx[$monthIdx((int) $r['YEAR_TR'], (int) $r['MONTH_TR'])] = (float) $r['INCOME'] - (float) $r['EXPENSES'];
}
$seasons = [];
if ($netByIdx) {
    $firstIdx = min(array_keys($netByIdx));
    $lastIdx  = $monthIdx($year, $curMonth) - 1;                 // last complete month
    $offset   = $SEASON_START_MONTH - 1;
    for ($y = intdiv($firstIdx - $offset, 12); $y <= intdiv($lastIdx - $offset, 12); $y++) {
        $seasonFrom = $y * 12 + $offset;
        $from   = max($seasonFrom, $firstIdx);                   // a first season may start late
        $to     = min($seasonFrom + 11, $lastIdx);
        $months = $to - $from + 1;
        if ($months < 1) continue;
        $sum = 0.0;
        for ($i = $from; $i <= $to; $i++) $sum += $netByIdx[$i] ?? 0;
        $seasons[] = [
            'label'  => sprintf('%d/%02d', $y, ($y + 1) % 100),
            'avg'    => round($sum / $months, 2),
            'months' => $months,
            'open'   => $to < $seasonFrom + 11,                  // season still running
        ];
    }
    $seasons = array_slice($seasons, -6);                        // the last six seasons
}
$seasonNow  = $seasons ? $seasons[count($seasons) - 1] : null;
$seasonPrev = count($seasons) > 1 ? $seasons[count($seasons) - 2] : null;
// Pace and projection: average monthly net of the complete months of the current season (August to July)
$seasonPace = ($seasonNow && $seasonNow['open']) ? (float) $seasonNow['avg'] : null;
$remaining  = max($TARGET - $totalCash, 0);
$progress   = $TARGET > 0 ? $totalCash / $TARGET : 0;
$stageIdx   = max(0, min((int) floor($progress * count($STAGES)), count($STAGES) - 1));
if ($remaining <= 0) {
    $etaText = 'Target reached.';
} elseif ($seasonPace === null) {
    $etaText = 'The season has just started and has no complete month yet, so no date can be projected.';
} elseif ($seasonPace > 0) {
    $monthsLeft = (int) ceil($remaining / $seasonPace);
    $etaDate    = $today->modify('first day of this month')->modify("+$monthsLeft months");
    $etaText    = 'At the average pace of season ' . $seasonNow['label'] . ' (' . $eur($seasonPace) . ' a month over '
                . (int) $seasonNow['months'] . ' complete month' . ($seasonNow['months'] > 1 ? 's' : '')
                . '), the target lands around ' . $etaDate->format('F Y') . '.';
} else {
    $etaText = 'The balance did not grow on average this season, so no date can be projected.';
}

// Top-ups, last 7 active days (stacked by method)
$weekLabels = [];
$weekSeries = [];
foreach ($weekRows as $r) {
    $label = (new DateTimeImmutable($r['DAY_TUP']))->format('D j M');
    if (!in_array($label, $weekLabels, true)) $weekLabels[] = $label;
}
foreach ($weekRows as $r) {
    $label = (new DateTimeImmutable($r['DAY_TUP']))->format('D j M');
    $weekSeries[$r['METHOD']] ??= array_fill(0, count($weekLabels), 0.0);
    $weekSeries[$r['METHOD']][array_search($label, $weekLabels, true)] = (float) $r['REVENUE'];
}
$weekDatasets = [];
foreach ($weekSeries as $method => $data) {
    $weekDatasets[] = ['label' => $method, 'data' => $data];
}

// Donut + KPI
$methodLabels = array_column($methodRows, 'METHOD');
$methodValues = array_map(fn($r) => (float) $r['REVENUE'], $methodRows);
$topupsYtd    = array_sum($methodValues);
$topMethod    = '';
if ($topupsYtd > 0) {
    $maxIdx    = array_search(max($methodValues), $methodValues, true);
    $topMethod = $methodLabels[$maxIdx] . ' ' . round($methodValues[$maxIdx] / $topupsYtd * 100) . '%';
}

// Top members
$topLabels = [];
foreach ($topRows as $i => $r) $topLabels[] = ($i + 1) . '. ' . $r['CUSTOMER'];
$topValues = array_map(fn($r) => round((float) $r['NET_VALUE'], 2), $topRows);

// Weekdays (Mon = 0)
$weekdayValues = array_fill(0, 7, 0.0);
foreach ($weekdayRows as $r) $weekdayValues[(int) $r['DOW']] = round((float) $r['SALES'], 2);

// Week-by-week views: { 'YYYY-MM-DD': { method: amount } } and { 'YYYY-MM-DD': amount }
$dailyTopups = [];
foreach ($dailyTopupRows as $r) $dailyTopups[$r['DAY_TUP']][$r['METHOD']] = round((float) $r['REVENUE'], 2);
$dailySales = [];
foreach ($dailySalesRows as $r) $dailySales[$r['DAY_TR']] = round((float) $r['SALES'], 2);

$payload = [
    'year'     => $year,
    'prevYear' => $prevYear,
    'months'   => $MONTHS,
    'gauge'    => [
        'balance' => $totalCash,
        'target'  => $TARGET,
        'stages'  => $STAGES,
        'stage'   => $stageIdx,
    ],
    'cashflow' => [
        'labels'     => $cfLabels,
        'income'     => $income,
        'expenses'   => $expenses,
        'opening'    => $cfOpening,
        'cumulative' => $cfCumulative,
    ],
    'sales'      => ['cy' => $salesCY, 'py' => $salesPY],
    'cumSales'   => ['cy' => $cumSalesCY, 'py' => $cumSalesPY],
    'members'    => ['cy' => $membersCY, 'py' => $membersPY],
    'weekdays'   => $weekdayValues,
    'seasons'    => [
        'labels' => array_column($seasons, 'label'),
        'values' => array_column($seasons, 'avg'),
        'months' => array_column($seasons, 'months'),
        'open'   => array_column($seasons, 'open'),
    ],
    'weekly'     => [
        'today'   => $today->format('Y-m-d'),
        'from'    => $weeklyFrom,
        'methods' => $methodList,
        'topups'  => $dailyTopups,
        'sales'   => $dailySales,
    ],
    'topups7'    => ['labels' => $weekLabels, 'datasets' => $weekDatasets],
    'methods'    => ['labels' => $methodLabels, 'values' => $methodValues],
    'top'        => ['labels' => $topLabels, 'values' => $topValues],
    'loss'       => $lossCY,
];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$topHeight = max(320, count($topValues) * 26 + 60);

// KPI delta pill
$deltaPill = function (?float $d) use ($h): string {
    if ($d === null) return '';
    $cls = $d >= 0 ? 'up' : 'down';
    return '<span class="db-delta ' . $cls . '">' . ($d >= 0 ? '▲' : '▼') . ' ' . $h(abs(round($d))) . '%</span>';
};
?>

<style>
    /* Optional: gives the dashboard more room than the theme's narrow article. Delete if you prefer the theme width. */
    #main article#dashboard { width: 66rem; }

    #dashboard {
        --db-blue: #6c93e8;
        --db-yellow: #fee636;
        --db-green: #2ac986;
        --db-red: #ff5f6d;
        --db-muted: rgba(255, 255, 255, .64);
        --db-line: rgba(255, 255, 255, .14);
        --db-panel: rgba(255, 255, 255, .045);
    }
    #dashboard .db-hero,
    #dashboard .db-kpis,
    #dashboard .db-grid { display: grid; gap: 1.25rem; margin-bottom: 1.25rem; }

    /* Hero: the road to the target */
    #dashboard .db-hero { grid-template-columns: repeat(auto-fit, minmax(min(100%, 18rem), 1fr)); align-items: center; padding: 1.5rem; background: var(--db-panel); border: 1px solid var(--db-line); border-radius: 8px; }
    #dashboard .db-hero h3 { margin: 0 0 .35rem; font-size: 1.1rem; letter-spacing: normal; text-transform: none; }
    #dashboard .db-balance { margin: 0; font-size: 2.6rem; font-weight: 600; line-height: 1.1; font-variant-numeric: tabular-nums; }
    #dashboard .db-hero p { margin: 0 0 .6rem; }
    #dashboard .db-hero .db-muted { color: var(--db-muted); font-size: .9rem; }
    #dashboard .db-gauge { position: relative; height: 15rem; }

    /* KPI strip */
    #dashboard .db-kpis { grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); }
    #dashboard .db-kpi { padding: .9rem 0 0; border-top: 2px solid var(--db-line); }
    #dashboard .db-kpi dt { margin: 0; color: var(--db-muted); font-size: .85rem; }
    #dashboard .db-kpi dd { margin: 0; }
    #dashboard .db-kpi .db-value { margin-top: .2rem; font-size: 1.7rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    #dashboard .db-kpi .db-sub { margin-top: .15rem; color: var(--db-muted); font-size: .8rem; line-height: 1.35; }
    #dashboard .db-delta { font-weight: 600; white-space: nowrap; }
    #dashboard .db-delta.up { color: var(--db-green); }
    #dashboard .db-delta.down { color: var(--db-red); }

    /* Chart panels */
    #dashboard .db-grid { grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr)); }
    #dashboard .db-card { min-width: 0; padding: 1.1rem 1.25rem 1.25rem; background: var(--db-panel); border: 1px solid var(--db-line); border-radius: 8px; }
    #dashboard .db-card.db-wide { grid-column: 1 / -1; }
    #dashboard .db-card h3 { margin: 0; font-size: 1rem; letter-spacing: normal; text-transform: none; }
    #dashboard .db-card p.db-desc { margin: .2rem 0 .9rem; color: var(--db-muted); font-size: .82rem; line-height: 1.4; }
    #dashboard .db-chart { position: relative; height: 17.5rem; }
    /* Week picker */
    #dashboard .db-weeknav { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin-bottom: .6rem; }
    #dashboard .db-weeknav select { flex: 1 1 8.5rem; width: auto; min-width: 0; height: 2.2rem; padding: 0 2.5rem 0 .75rem; font-size: .85rem; background-size: 1rem; background-position: calc(100% - .75rem) center; }
    #dashboard select option { color: #1b1f22; background: #fff; }
    #dashboard .db-btn { height: 2.2rem; line-height: 2.2rem; padding: 0 .85rem; font-size: .85rem; letter-spacing: normal; text-transform: none; cursor: pointer; }
    #dashboard .db-btn:disabled { opacity: .35; cursor: default; }
    #dashboard .db-weeksum { margin: 0 0 .75rem; font-size: .9rem; font-variant-numeric: tabular-nums; }
    #dashboard .db-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
    @media (max-width: 480px) {
        #dashboard .db-card { padding: .9rem .9rem 1rem; }
        #dashboard .db-weeknav { gap: .35rem; }
        #dashboard .db-weeknav .db-btn { padding: 0 .65rem; }
        #dashboard .db-weeknav select { padding-left: .5rem; padding-right: 2rem; background-position: calc(100% - .5rem) center; }
    }
    #dashboard .db-empty { margin: 0; padding: 2rem 0; color: var(--db-muted); text-align: center; }
</style>

<article id="dashboard">
    <h2 class="major">Dashboard</h2>

    <section class="db-hero" aria-labelledby="db-road">
        <div>
            <h3 id="db-road">Road to Ibiza 🇪🇸</h3>
            <p class="db-balance"><?= $eur($totalCash) ?></p>
            <p class="db-muted">
                <?= round($progress * 100) ?>% of the <?= $eur($TARGET) ?> target.
                Stage: <?= $h($STAGES[$stageIdx]) ?>.
            </p>
            <p><?= $h($etaText) ?></p>
        </div>
        <div class="db-gauge">
            <canvas id="gaugeChart" role="img"
                aria-label="Gauge: balance of <?= $h($eur($totalCash)) ?> out of <?= $h($eur($TARGET)) ?>"></canvas>
        </div>
    </section>

    <dl class="db-kpis">
        <div class="db-kpi">
            <dt>Net sales in <?= $year ?></dt>
            <dd class="db-value"><?= $eur($ytdSales) ?></dd>
            <dd class="db-sub">
                <?php if ($ytdDelta !== null): ?>
                    <?= $deltaPill($ytdDelta) ?> vs <?= $h($ytdRange) ?> <?= $prevYear ?>
                <?php else: ?>
                    After refunds
                <?php endif; ?>
            </dd>
        </div>
        <div class="db-kpi">
            <dt>Active customers this month</dt>
            <dd class="db-value"><?= (int) $membersNow ?></dd>
            <dd class="db-sub">Last month: <?= (int) $membersPrev ?></dd>
        </div>
        <div class="db-kpi">
            <dt>Top-ups in <?= $year ?></dt>
            <dd class="db-value"><?= $eur($topupsYtd) ?></dd>
            <dd class="db-sub"><?= $topMethod !== '' ? 'Most used: ' . $h($topMethod) : 'No top-ups yet' ?></dd>
        </div>
        <div class="db-kpi">
            <dt>Average monthly net</dt>
            <?php if ($seasonNow): ?>
                <dd class="db-value"><?= $eur($seasonNow['avg']) ?></dd>
                <dd class="db-sub">
                    Season <?= $h($seasonNow['label']) ?>
                </dd>
            <?php endif; ?>
        </div>
    </dl>

    <div class="db-grid">
        <section class="db-card db-wide">
            <h3>Cash flow, last 12 months</h3>
            <p class="db-desc">Top-ups in, purchases out, and the running balance over the period.</p>
            <div class="db-chart"><canvas id="cashflowChart" role="img" aria-label="Monthly income, expenses and cumulative balance, last 12 months"></canvas></div>
        </section>

        <section class="db-card">
            <h3>Top-ups per week</h3>
            <p class="db-desc">By payment method, Monday to Sunday. Use the arrows or the list to pick a week.</p>
            <div class="db-weeknav" data-weekpicker="topups">
                <button type="button" class="db-btn" data-act="prev" aria-label="Previous week">&#8249;</button>
                <label class="db-sr" for="topupsWeek">Week</label>
                <select id="topupsWeek"></select>
                <button type="button" class="db-btn" data-act="next" aria-label="Next week">&#8250;</button>
                <button type="button" class="db-btn" data-act="today">Today</button>
            </div>
            <p class="db-weeksum" id="topupsSummary" aria-live="polite"></p>
            <div class="db-chart"><canvas id="weeklyTopupsChart" role="img" aria-label="Top-ups by payment method for each day of the selected week"></canvas></div>
        </section>

        <section class="db-card">
            <h3>Sales per weekday</h3>
            <p class="db-desc">Each day of the selected week, next to the week before.</p>
            <div class="db-weeknav" data-weekpicker="sales">
                <button type="button" class="db-btn" data-act="prev" aria-label="Previous week">&#8249;</button>
                <label class="db-sr" for="salesWeek">Week</label>
                <select id="salesWeek"></select>
                <button type="button" class="db-btn" data-act="next" aria-label="Next week">&#8250;</button>
                <button type="button" class="db-btn" data-act="today">Today</button>
            </div>
            <p class="db-weeksum" id="salesSummary" aria-live="polite"></p>
            <div class="db-chart"><canvas id="weeklySalesChart" role="img" aria-label="Sales for each day of the selected week compared with the previous week"></canvas></div>
        </section>

        <section class="db-card">
            <h3>Monthly net sales</h3>
            <p class="db-desc"><?= $year ?> against <?= $prevYear ?>, after refunds.</p>
            <div class="db-chart"><canvas id="salesChart" role="img" aria-label="Monthly net sales, current year versus previous year"></canvas></div>
        </section>

        <section class="db-card">
            <h3>Sales since 1 January</h3>
            <p class="db-desc">Running total of net sales, <?= $year ?> against <?= $prevYear ?>.</p>
            <div class="db-chart"><canvas id="cumSalesChart" role="img" aria-label="Cumulative net sales, current year versus previous year"></canvas></div>
        </section>

        <section class="db-card">
            <h3>Active customers</h3>
            <p class="db-desc">Customers with at least one purchase in the month.</p>
            <div class="db-chart"><canvas id="membersChart" role="img" aria-label="Active customers per month, current year versus previous year"></canvas></div>
        </section>

        </section>

        <section class="db-card">
            <h3>Top-ups by payment method</h3>
            <p class="db-desc">Share of top-ups since 1 January.</p>
            <?php if ($methodValues): ?>
                <div class="db-chart"><canvas id="methodDonut" role="img" aria-label="Top-ups by payment method, current year"></canvas></div>
            <?php else: ?>
                <p class="db-empty">No top-ups this year yet.</p>
            <?php endif; ?>
        </section>

        <section class="db-card db-wide">
            <h3>Top 20 members</h3>
            <p class="db-desc">Net spend since 1 January: purchases minus refunds.</p>
            <?php if ($topValues): ?>
                <div class="db-chart" style="height: <?= (int) $topHeight ?>px"><canvas id="topMembersChart" role="img" aria-label="Top 20 members by net spend this year"></canvas></div>
            <?php else: ?>
                <p class="db-empty">No member purchases this year yet.</p>
            <?php endif; ?>
        </section>

        <section class="db-card db-wide">
            <h3>Tournée de l'Amikale</h3>
            <p class="db-desc">Monthly total booked to the Amikale account, shown as a loss.</p>
            <div class="db-chart"><canvas id="lossChart" role="img" aria-label="Monthly Amikale loss this year"></canvas></div>
        </section>

    </div>

<script>
(() => {
    const D = <?= json_encode($payload, $jsonFlags) ?>;

    const init = () => {
        if (typeof Chart === 'undefined') {
            console.error('[dashboard] Chart.js is not loaded.');
            return;
        }

        /* ---------- shared look ---------- */
        const C = { blue: '#6c93e8', yellow: '#fee636', green: '#2ac986', red: '#ff5f6d', white: '#ffffff', purple: '#e879a9'};
        const METHOD_COLORS = { 'Cash': C.blue, 'SumUp': C.yellow, 'Stripe': C.purple, 'Transfer': C.white, };
        const FALLBACK = 'rgb(200, 200, 200)';
        const fontFamily = getComputedStyle(document.body).fontFamily;

        Chart.defaults.color = 'rgba(255, 255, 255, .72)';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, .12)';
        Chart.defaults.font.family = fontFamily;
        Chart.defaults.maintainAspectRatio = false;
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) Chart.defaults.animation = false;

        const eurFmt = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 });
        const eur = v => eurFmt.format(v);
        const el = id => document.getElementById(id);
        const make = (id, config) => el(id) ? new Chart(el(id), config) : null;
        const tooltipEuro = axis => ({ callbacks: { label: c => `${c.dataset.label ?? c.label}: ${eur(c.parsed[axis])}` } });

        /* ---------- gauge ---------- */
        const needle = {
            id: 'needle',
            afterDatasetsDraw(chart) {
                const { ctx } = chart;
                const { x, y, innerRadius, outerRadius } = chart.getDatasetMeta(0).data[0];
                const ratio = Math.min(Math.max(D.gauge.balance / D.gauge.target, 0), 1);

                ctx.save();
                ctx.translate(x, y);

                ctx.save();
                ctx.rotate(Math.PI + Math.PI * ratio);
                ctx.fillStyle = '#ffffff';
                ctx.beginPath();
                ctx.moveTo(0, -5);
                ctx.lineTo(outerRadius - 20, 0);
                ctx.lineTo(0, 5);
                ctx.fill();
                ctx.beginPath();
                ctx.arc(0, 0, 8, 0, Math.PI * 2);
                ctx.fill();
                ctx.fillStyle = '#1a1a1a';
                ctx.beginPath();
                ctx.arc(0, 0, 3, 0, Math.PI * 2);
                ctx.fill();
                ctx.restore();

                // 0 and target labels under the ends of the arc
                const mid = (innerRadius + outerRadius) / 2;
                ctx.fillStyle = 'rgba(255, 255, 255, .72)';
                ctx.font = `600 12px ${fontFamily}`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText(eur(0), -mid, 14);
                ctx.fillText(eur(D.gauge.target), mid, 14);
                ctx.restore();
            }
        };
        make('gaugeChart', {
            type: 'doughnut',
            data: {
                labels: D.gauge.stages,
                datasets: [{
                    data: D.gauge.stages.map(() => D.gauge.target / D.gauge.stages.length),
                    backgroundColor: ({ chart }) => {
                        const { ctx, chartArea } = chart;
                        if (!chartArea) return '#ff4d6d';
                        const g = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
                        g.addColorStop(0, '#ff4d6d');
                        g.addColorStop(.25, '#ff9e4f');
                        g.addColorStop(.5, '#ffd84d');
                        g.addColorStop(.75, '#8bd450');
                        g.addColorStop(1, '#2ac986');
                        return g;
                    },
                    borderWidth: 0,
                    hoverOffset: 0,
                    circumference: 180,
                    rotation: 270,
                    cutout: '80%'
                }]
            },
            options: {
                layout: { padding: { bottom: 30 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            title: () => D.gauge.stages[D.gauge.stage],
                            label: () => [`Balance: ${eur(D.gauge.balance)}`, `Target: ${eur(D.gauge.target)}`]
                        }
                    }
                }
            },
            plugins: [needle]
        });

        /* ---------- cash flow (floating bars) ---------- */
        const cf = D.cashflow;
        const cfValues = { 'Income': cf.income, 'Expenses': cf.expenses, 'Cumulative net result': cf.cumulative };
        make('cashflowChart', {
            type: 'bar',
            data: {
                labels: cf.labels,
                datasets: [
                    { label: 'Income', backgroundColor: 'rgba(42, 201, 134, .85)',
                      data: cf.income.map((v, i) => [cf.opening[i], cf.opening[i] + v]) },
                    { label: 'Expenses', backgroundColor: 'rgba(255, 95, 109, .85)',
                      data: cf.expenses.map((v, i) => [cf.opening[i] + cf.income[i], cf.cumulative[i]]) },
                    { label: 'Cumulative net result', backgroundColor: 'rgba(108, 147, 232, .85)',
                      data: cf.cumulative.map(v => [0, v]) }
                ]
            },
            options: {
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: c => `${c.dataset.label}: ${eur(cfValues[c.dataset.label][c.dataIndex])}` } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { callback: eur } }
                }
            }
        });

        /* ---------- line charts: one factory ---------- */
        const line = (id, series, { money = true } = {}) => make(id, {
            type: 'line',
            data: {
                labels: D.months,
                datasets: series.map(s => ({
                    label: s.label, data: s.data,
                    borderColor: s.color, backgroundColor: s.color + '33',
                    borderWidth: 3, tension: .35, fill: true, pointRadius: 3, spanGaps: false
                }))
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: series.length > 1, position: 'bottom' },
                    tooltip: { callbacks: { label: c => `${c.dataset.label}: ${money ? eur(c.parsed.y) : c.parsed.y}` } }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { beginAtZero: true, ticks: { precision: 0, callback: v => money ? eur(v) : v } }
                }
            }
        });

        line('salesChart', [
            { label: `Sales ${D.year}`, data: D.sales.cy, color: C.blue },
            { label: `Sales ${D.prevYear}`, data: D.sales.py, color: C.yellow }
        ]);
        line('cumSalesChart', [
            { label: `${D.year}`, data: D.cumSales.cy, color: C.blue },
            { label: `${D.prevYear}`, data: D.cumSales.py, color: C.yellow }
        ]);
        line('membersChart', [
            { label: `Members ${D.year}`, data: D.members.cy, color: C.blue },
            { label: `Members ${D.prevYear}`, data: D.members.py, color: C.yellow }
        ], { money: false });
        line('lossChart', [{ label: `Loss ${D.year}`, data: D.loss, color: C.red }]);

        /* ---------- weekday ---------- */
        const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const peak = Math.max(...D.weekdays);
        make('weekdayChart', {
            type: 'bar',
            data: {
                labels: dayNames,
                datasets: [{
                    label: 'Sales',
                    data: D.weekdays,
                    backgroundColor: D.weekdays.map(v => (v === peak && peak > 0) ? C.yellow : C.blue)
                }]
            },
            options: {
                plugins: { legend: { display: false }, tooltip: tooltipEuro('y') },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: eur } } }
            }
        });

        /* ---------- week-by-week views with a week picker ---------- */
        const W = D.weekly;
        const DAY_MS = 86400000;
        const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        const parseDay = s => { const [y, m, d] = s.split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)); };
        const isoDay = d => d.toISOString().slice(0, 10);
        const addDays = (d, n) => new Date(d.getTime() + n * DAY_MS);
        const mondayOf = d => addDays(d, -((d.getUTCDay() + 6) % 7));
        const isoWeekNumber = monday => {
            const thursday = addDays(monday, 3);                       // ISO: the week belongs to the year of its Thursday
            const jan1 = new Date(Date.UTC(thursday.getUTCFullYear(), 0, 1));
            return Math.ceil(((thursday - jan1) / DAY_MS + 1) / 7);
        };
        const shortDate = d => d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', timeZone: 'UTC' });
        // Compact label for the picker (fits a phone): 26W41, 5/10 - 11/10
        const dayMonth = d => `${d.getUTCDate()}/${d.getUTCMonth() + 1}`;
        const weekLabel = mon => {
            const start = parseDay(mon);
            const code = String(addDays(start, 3).getUTCFullYear()).slice(-2) + 'W' + String(isoWeekNumber(start)).padStart(2, '0');
            return `${code}, ${dayMonth(start)} - ${dayMonth(addDays(start, 6))}`;
        };
        const weekDays = monday => Array.from({ length: 7 }, (_, i) => isoDay(addDays(parseDay(monday), i)));
        const dayLabels = days => days.map((d, i) => `${WEEKDAYS[i]} ${parseDay(d).getUTCDate()}`);
        const sumOf = list => list.reduce((s, v) => s + (v || 0), 0);

        // Mondays from the current week back to the start of the data, newest first
        const weekMondays = [];
        for (let m = mondayOf(parseDay(W.today)), first = mondayOf(parseDay(W.from)); m >= first; m = addDays(m, -7)) {
            weekMondays.push(isoDay(m));
        }

        const weekPicker = (key, onChange) => {
            const root = document.querySelector(`[data-weekpicker="${key}"]`);
            if (!root) return;
            const select = root.querySelector('select');
            const prev = root.querySelector('[data-act="prev"]');
            const next = root.querySelector('[data-act="next"]');
            select.innerHTML = weekMondays.map(mon => `<option value="${mon}">${weekLabel(mon)}</option>`).join('');
            const show = mon => {
                select.value = mon;
                const i = weekMondays.indexOf(mon);
                prev.disabled = i === weekMondays.length - 1;          // list is newest first
                next.disabled = i === 0;
                onChange(mon);
            };
            const step = delta => {
                const target = weekMondays[weekMondays.indexOf(select.value) + delta];
                if (target) show(target);
            };
            prev.addEventListener('click', () => step(1));
            next.addEventListener('click', () => step(-1));
            root.querySelector('[data-act="today"]').addEventListener('click', () => show(weekMondays[0]));
            select.addEventListener('change', () => show(select.value));
            show(weekMondays[0]);
        };

        // Top-ups per payment method, Monday to Sunday
        const weeklyTopups = make('weeklyTopupsChart', {
            type: 'bar',
            data: {
                labels: [],
                datasets: W.methods.map(m => ({ label: m, backgroundColor: METHOD_COLORS[m] ?? FALLBACK, data: [] }))
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: c => `${c.dataset.label}: ${eur(c.parsed.y)}`,
                            footer: items => `Day total: ${eur(sumOf(items.map(i => i.parsed.y)))}`
                        }
                    }
                },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, ticks: { callback: eur } }
                }
            }
        });
        weekPicker('topups', mon => {
            if (!weeklyTopups) return;
            const days = weekDays(mon);
            weeklyTopups.data.labels = dayLabels(days);
            let total = 0;
            const parts = [];
            weeklyTopups.data.datasets.forEach(ds => {
                ds.data = days.map(d => d > W.today ? null : ((W.topups[d] || {})[ds.label] || 0));
                const sum = sumOf(ds.data);
                total += sum;
                if (sum > 0) parts.push(`${ds.label} ${eur(sum)}`);
            });
            weeklyTopups.update();
            el('topupsSummary').textContent = total > 0
                ? `Week total ${eur(total)}: ${parts.join(', ')}.`
                : 'No top-ups recorded this week.';
        });

        // Sales per weekday, selected week against the week before
        const weeklySales = make('weeklySalesChart', {
            type: 'bar',
            data: {
                labels: [],
                datasets: [
                    { label: 'Selected week', backgroundColor: C.blue, data: [] },
                    { label: 'Previous week', backgroundColor: 'rgba(254, 230, 54, .55)', data: [] }
                ]
            },
            options: {
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom' }, tooltip: tooltipEuro('y') },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: eur } } }
            }
        });
        weekPicker('sales', mon => {
            if (!weeklySales) return;
            const monday = parseDay(mon);
            const days = weekDays(mon);
            const prevDays = weekDays(isoDay(addDays(monday, -7)));
            const weekNo = isoWeekNumber(monday), prevNo = isoWeekNumber(addDays(monday, -7));
            const sales = d => d > W.today ? null : (W.sales[d] ?? 0);

            weeklySales.data.labels = dayLabels(days);
            weeklySales.data.datasets[0].label = `Week ${weekNo}`;
            weeklySales.data.datasets[0].data = days.map(sales);
            weeklySales.data.datasets[1].label = `Week ${prevNo}`;
            weeklySales.data.datasets[1].data = prevDays.map(d => W.sales[d] ?? 0);
            weeklySales.update();

            // Compare like with like: for the running week, only the days that have happened
            const elapsed = days.filter(d => d <= W.today).length;
            const cur = sumOf(weeklySales.data.datasets[0].data);
            const before = sumOf(weeklySales.data.datasets[1].data.slice(0, elapsed));
            let text = elapsed === 0 ? 'This week has not started yet.' : `Week ${weekNo}: ${eur(cur)}.`;
            if (elapsed > 0 && before > 0) {
                const pct = Math.round((cur / before - 1) * 100);
                const cls = pct >= 0 ? 'up' : 'down';
                text += ` <span class="db-delta ${cls}">${pct >= 0 ? '▲' : '▼'} ${Math.abs(pct)}%</span> vs ${elapsed === 7 ? '' : 'the same days of '}week ${prevNo}.`;
            }
            el('salesSummary').innerHTML = text;
        });

        /* ---------- recent top-ups (stacked) ---------- */
        make('recentTopupsChart', {
            type: 'bar',
            data: {
                labels: D.topups7.labels,
                datasets: D.topups7.datasets.map(ds => ({
                    ...ds, backgroundColor: METHOD_COLORS[ds.label] ?? FALLBACK
                }))
            },
            options: {
                plugins: { legend: { position: 'bottom' }, tooltip: tooltipEuro('y') },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: { maxRotation: 90, minRotation: 45 } },
                    y: { stacked: true, beginAtZero: true, ticks: { callback: eur } }
                }
            }
        });

        /* ---------- donut ---------- */
        const centerText = {
            id: 'centerText',
            beforeDraw(chart) {
                const { ctx, chartArea: a } = chart;
                const total = chart.data.datasets[0].data.reduce((s, v) => s + v, 0);
                ctx.save();
                ctx.font = `600 18px ${fontFamily}`;
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(eur(total), (a.left + a.right) / 2, (a.top + a.bottom) / 2);
                ctx.restore();
            }
        };
        make('methodDonut', {
            type: 'doughnut',
            data: {
                labels: D.methods.labels,
                datasets: [{
                    data: D.methods.values,
                    backgroundColor: D.methods.labels.map(m => METHOD_COLORS[m] ?? FALLBACK),
                    borderColor: 'rgba(0, 0, 0, .45)',
                    borderWidth: 1
                }]
            },
            options: {
                cutout: '62%',
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: c => {
                                const total = c.dataset.data.reduce((s, v) => s + v, 0);
                                return `${c.label}: ${eur(c.parsed)} (${Math.round(c.parsed / total * 100)}%)`;
                            }
                        }
                    }
                }
            },
            plugins: [centerText]
        });

        /* ---------- average monthly net per season ---------- */
        make('seasonChart', {
            type: 'bar',
            data: {
                labels: D.seasons.labels,
                datasets: [{
                    label: 'Average monthly net',
                    data: D.seasons.values,
                    backgroundColor: D.seasons.values.map((v, i) => (v < 0 ? C.red : C.blue) + (D.seasons.open[i] ? '88' : ''))
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: c => [
                                `${eur(c.parsed.y)} a month`,
                                `${D.seasons.months[c.dataIndex]} complete months${D.seasons.open[c.dataIndex] ? ', season in progress' : ''}`
                            ]
                        }
                    }
                },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { callback: eur } } }
            }
        });

        /* ---------- top members ---------- */
        make('topMembersChart', {
            type: 'bar',
            data: {
                labels: D.top.labels,
                datasets: [{ label: 'Net spend', data: D.top.values, backgroundColor: C.blue }]
            },
            options: {
                indexAxis: 'y',
                plugins: { legend: { display: false }, tooltip: tooltipEuro('x') },
                scales: {
                    x: { beginAtZero: true, ticks: { callback: eur } },
                    y: { grid: { display: false }, ticks: { autoSkip: false } }
                }
            }
        });
    };

    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', init) : init();
})();
</script>
</article>