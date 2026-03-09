<?php
    include 'security.php';

    $stmt_0= $pdo->prepare("
        SELECT
            rtt.NAME AS METHOD,
            SUM(wt.AMOUNT) AS REVENUE
        FROM wallet_topup wt
        JOIN ref_topup_type rtt 
        ON wt.ID_TOPUP_TYPE = rtt.ID_TOPUP_TYPE
        WHERE 
            wt.ID_TOPUP_TYPE IN (2,3,6) AND
            YEAR(wt.CREATED_AT) = YEAR(CURDATE())
        GROUP BY rtt.NAME
        ORDER BY wt.ID_TOPUP_TYPE
    ");
    $stmt_0->execute();
    $kpi_0 = $stmt_0->fetchAll();
    $currentYear = date('Y');

    $stmt_1= $pdo->prepare("
        WITH sales AS (
            SELECT 
                YEAR(created_at)  AS year_tr,
                MONTH(created_at) AS month_tr,
                COUNT(DISTINCT CASE WHEN id_customer > 3 THEN id_customer END) AS cnt_customer,
                SUM(total) AS total_sales
            FROM transactions
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            AND id_customer != 1 AND id_product NOT IN (7,9)
            GROUP BY YEAR(created_at), MONTH(created_at)
        ),
        refunds AS (
            SELECT 
                YEAR(created_at)  AS year_tr,
                MONTH(created_at) AS month_tr,
                SUM(amount) AS refund
            FROM wallet_topup
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            AND id_topup_type = '5'
            GROUP BY YEAR(created_at), MONTH(created_at)
        )
        SELECT
            s.YEAR_TR,
            s.MONTH_TR,
            s.CNT_CUSTOMER,
            s.total_sales - IFNULL(r.refund, 0) AS NET_SALES
        FROM sales s
        LEFT JOIN refunds r
            ON s.year_tr = r.year_tr
        AND s.month_tr = r.month_tr
        ORDER BY s.YEAR_TR, s.MONTH_TR
    ");

    $stmt_1->execute();
    $kpi_1 = $stmt_1->fetchAll();

    $currentYear = date('Y');
    $previousYear = $currentYear - 1;

    // Labels = short month names (Jan, Feb, Mar...)
    $labels = [];
    for ($m = 1; $m <= 12; $m++) {
        // mktime(heure, minute, seconde, mois, jour)
        $labels[] = date("M", mktime(0, 0, 0, $m, 1));
    }

    // Initialise arrays with 0 (important if some months are missing)
    $sales_cy = array_fill(0, 12, 0);
    $cnt_customer_cy = array_fill(0, 12, 0);
    $sales_py = array_fill(0, 12, 0);
    $cnt_customer_py = array_fill(0, 12, 0);

    foreach ($kpi_1 as $row) {
        $monthIndex = (int)$row['MONTH_TR'] - 1;

        if ($row['YEAR_TR'] == $currentYear) {
            $sales_cy[$monthIndex] = (int)$row['NET_SALES'];
            $cnt_customer_cy[$monthIndex] = $row['CNT_CUSTOMER'];
        }

        if ($row['YEAR_TR'] == $previousYear) {
            $sales_py[$monthIndex] = (int)$row['NET_SALES'];
            $cnt_customer_py[$monthIndex] = $row['CNT_CUSTOMER'];
        }
    }

    // Add 700 to January 2026 (current year)
    if ($currentYear == 2026) {
        $sales_cy[0] += 700;   // January index = 0
    }

    $stmt_2= $pdo->prepare("
        SELECT
            rtt.NAME AS METHOD,
            DATE_FORMAT(wt.CREATED_AT, '%a %e %b') AS DATE_TUP,
            SUM(wt.AMOUNT) AS REVENUE
        FROM wallet_topup wt
        JOIN ref_topup_type rtt 
            ON wt.ID_TOPUP_TYPE = rtt.ID_TOPUP_TYPE
        INNER JOIN (
            SELECT DISTINCT DATE(CREATED_AT) AS active_date
            FROM wallet_topup
            WHERE ID_TOPUP_TYPE IN (2,3,6)
            ORDER BY active_date DESC
            LIMIT 7
        ) AS last_seven ON DATE(wt.CREATED_AT) = last_seven.active_date
        WHERE wt.ID_TOPUP_TYPE IN (2,3,6)
        GROUP BY DATE(wt.CREATED_AT), rtt.NAME
        ORDER BY DATE(wt.CREATED_AT), wt.ID_TOPUP_TYPE
    ");

    $stmt_2->execute();
    $kpi_2 = $stmt_2->fetchAll();

    // 1. Process results into a structured matrix: [Method][Date] = Amount
    $matrix = [];
    $methods = [];
    $labels_2 = [];

    foreach ($kpi_2 as $row) {
        $date = $row['DATE_TUP'];
        $method = $row['METHOD'];
        
        if (!in_array($date, $labels_2)) $labels_2[] = $date;
        if (!in_array($method, $methods)) $methods[] = $method;
        
        $matrix[$method][$date] = (float)$row['REVENUE'];
    }

    // 2. Prepare datasets for Chart
    $datasets = [];
    $colors = [
        'Cash' => 'rgb(33, 59, 112)',
        'SumUp' => 'rgb(254, 230, 54)',
        'Bank Transfer' => 'rgb(255, 255, 255)'
    ];

    foreach ($methods as $method) {
        $data = [];
        foreach ($labels_2 as $date) {
            // If data is missing for a specific date/method combo, default to 0
            $data[] = $matrix[$method][$date] ?? 0;
        }

        $datasets[] = [
            'label' => $method,
            'data' => $data,
            'backgroundColor' => $colors[$method] ?? 'rgb(200, 200, 200)' // Fallback color
        ];
    }


    // GET best customers
    $stmt_3= $pdo->prepare("
    SELECT 
        CONCAT(ROW_NUMBER() OVER (ORDER BY (sub.TOTAL_ORDER_VALUE - COALESCE(ref.TOTAL_REFUND, 0)) DESC), ' - ', sub.CUSTOMER) AS CUSTOMER,
        (sub.TOTAL_ORDER_VALUE - COALESCE(ref.TOTAL_REFUND, 0)) AS NET_VALUE
    FROM (
        SELECT 
            c.ID_CUSTOMER,
            CONCAT(c.FIRST_NAME, ' ', c.LAST_NAME) AS CUSTOMER,
            SUM(p.PRICE * tr.QUANTITY) AS TOTAL_ORDER_VALUE
        FROM transactions tr
        JOIN customers c ON tr.ID_CUSTOMER = c.ID_CUSTOMER
        LEFT JOIN ref_product p ON tr.ID_PRODUCT = p.ID_PRODUCT
        WHERE tr.CREATED_AT >= DATE_FORMAT(NOW(), '%Y-01-01')
        AND c.ID_CUSTOMER > 3 
        AND tr.ID_PRODUCT NOT IN (7,9)
        GROUP BY c.ID_CUSTOMER, c.FIRST_NAME, c.LAST_NAME
    ) sub
    LEFT JOIN (
        SELECT 
            ID_CUSTOMER,
            SUM(amount) AS TOTAL_REFUND
        FROM wallet_topup
        WHERE CREATED_AT >= DATE_FORMAT(NOW(), '%Y-01-01')
        AND id_topup_type = 5
        GROUP BY ID_CUSTOMER
    ) ref ON sub.ID_CUSTOMER = ref.ID_CUSTOMER
    ORDER BY NET_VALUE DESC
    LIMIT 20
    ");

    $stmt_3->execute();
    $kpi_3 = $stmt_3->fetchAll();

    // GET Amikale Note
    $stmt_4= $pdo->prepare("
        SELECT 
            YEAR(CREATED_AT) YEAR_TR,
            MONTH(CREATED_AT) MONTH_TR,
            -SUM(TOTAL) TOTAL_LOSS
        FROM transactions
        WHERE YEAR(CREATED_AT) = YEAR(now()) AND ID_CUSTOMER = 1
        GROUP BY YEAR(CREATED_AT), MONTH(CREATED_AT)
        ORDER BY YEAR(CREATED_AT), MONTH(CREATED_AT)
    ");

    $stmt_4->execute();
    $kpi_4 = $stmt_4->fetchAll();

    // Initialise arrays with 0 (important if some months are missing)
    $loss_cy = array_fill(0, 12, 0);

    foreach ($kpi_4 as $row) {
        $monthIndex = (int)$row['MONTH_TR'] - 1;
        $loss_cy[$monthIndex] = (int)$row['TOTAL_LOSS'];
    }
?>

<article id="dashboard">
    <h2 class="major">Dashboard</h2>
    <div style="display: flex; justify-content: center; align-items: center; margin: 20px 0;">
        <div style="width: 300px; height: 300px;">
            <canvas id="topupDonut"></canvas>
        </div>
    </div>
    <hr />
    <canvas id="revenueChart" height="300"></canvas>
    <hr />
    <canvas id="lossChart" height="300"></canvas>
    <hr />
    <canvas id="salesChart" height="300"></canvas>
    <hr />
    <canvas id="CustChart" height="300"></canvas>
    <hr />
    <canvas id="topCustomersChart" height="300"></canvas>
    
<script>
    document.addEventListener('DOMContentLoaded', () => {

    const ctx = document.getElementById('topupDonut').getContext('2d');
    const centerTextPlugin = {
        id: 'centerText',
        beforeDraw(chart) {
            const { ctx, chartArea } = chart;
            const dataset = chart.data.datasets[0];

            if (!dataset || !dataset.data.length) return;

            const total = dataset.data.reduce((a, b) => a + b, 0);

            ctx.save();
            ctx.font = 'bold 18px sans-serif';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';

            const x = (chartArea.left + chartArea.right) / 2;
            const y = (chartArea.top + chartArea.bottom) / 2;

            ctx.fillText(`€${total.toLocaleString()}`, x, y);
            ctx.restore();
        }
    };
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($kpi_0, 'METHOD')) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($r) => (float)$r['REVENUE'], $kpi_0)) ?>,
                backgroundColor: [
                    'rgb(33, 59, 112)',
                    'rgb(254, 230, 54)',
                    'rgb(255, 255, 255)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Top-up Revenue by Payment Method – CY'
                },
                tooltip: {
                    callbacks: {
                        label: ctx => {
                            const dataset = ctx.chart.data.datasets[0];
                            const total = dataset.data.reduce((a, b) => a + b, 0);
                            const value = ctx.parsed;
                            const percentage = ((value / total) * 100).toFixed(0);
                            return `${value.toLocaleString('en-US', {minimumFractionDigits: 0, maximumFractionDigits: 0})}€ (${percentage}%)`;
                        }
                    }
                },
                legend: {
                    position: 'top'
                }
            },
            cutout: '60%' // controls donut thickness
        },
        plugins: [centerTextPlugin]
    });
    
    const ctx1 = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Sales <?= date("Y") ?>',
                    data: <?= json_encode($sales_cy) ?>,
                    borderColor: 'rgb(33, 59, 112)',
                    backgroundColor: 'rgba(33, 59, 112, 0.25)', // ✅ background
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4
                },
                {
                    label: 'Sales <?= date("Y")-1 ?>',
                    data: <?= json_encode($sales_py) ?>,
                    borderColor: 'rgb(254, 230, 54)',
                    backgroundColor: 'rgba(254, 230, 54, 0.25)', // ✅ background
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Monthly Sales – CY vs PY'
                },
                legend: {
                    labels: {
                        usePointStyle: true, // ✅ line instead of rectangle
                        pointStyle: 'line'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + '€'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v + '€'
                    }
                }
            }
        }
    });
        
    const ctx2 = document.getElementById('CustChart').getContext('2d');
        new Chart(ctx2, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Members <?= date("Y") ?>',
                    data: <?= json_encode($cnt_customer_cy) ?>,
                    borderColor: 'rgb(33, 59, 112)',
                    backgroundColor: 'rgba(33, 59, 112, 0.25)', // ✅ background
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4
                },
                {
                    label: 'Members <?= date("Y")-1 ?>',
                    data: <?= json_encode($cnt_customer_py) ?>,
                    borderColor: 'rgb(254, 230, 54)',
                    backgroundColor: 'rgba(254, 230, 54, 0.25)', // ✅ background
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Active Members – CY vs PY'
                },
                legend: {
                    labels: {
                        usePointStyle: true, // ✅ line instead of rectangle
                        pointStyle: 'line'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    const ctx3 = document.getElementById('revenueChart').getContext('2d');

    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labels_2) ?>,
            datasets: <?= json_encode($datasets) ?>
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Top-up Revenue by Payment Method – Last 7 Days'
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + '€'
                    }
                }
            },
            scales: {
                x: { stacked: true,
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45
                    }
                    },
                y: { 
                    stacked: true,
                    beginAtZero: true,
                    ticks: { callback: v => v + '€' }
                }
            }
        }
    });

    const ctx_4 = document.getElementById('topCustomersChart').getContext('2d');

    new Chart(ctx_4, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($kpi_3, 'CUSTOMER')) ?>,
            datasets: [{
                label: 'Total Order Value',
                data: <?= json_encode(array_map(fn($c) => (float)$c['NET_VALUE'], $kpi_3)) ?>,
                backgroundColor: 'rgb(33, 59, 112)',
            }]
        },
        options: {
            indexAxis: 'y', // horizontal bars
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Top 20 Members – CY'
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.parsed.x + '€'
                    }
                }
            },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: v => v + '€' }
                }
            }
        }
    });

    const ctx4 = document.getElementById('lossChart').getContext('2d');
    new Chart(ctx4, {
        type: 'line',
        data: {
            labels: <?= json_encode($labels) ?>,
            datasets: [
                {
                    label: 'Loss <?= date("Y") ?>',
                    data: <?= json_encode($loss_cy) ?>,
                    borderColor: 'rgb(255, 0, 0)',
                    backgroundColor: 'rgba(255, 0, 0, 0.25)', // ✅ background
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Amikale Rounds – CY'
                },
                legend: {
                    labels: {
                        usePointStyle: true, // ✅ line instead of rectangle
                        pointStyle: 'line'
                    }
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + '€'
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        maxRotation: 90,
                        minRotation: 45
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => v + '€'
                    }
                }
            }
        }
    });
    });
    </script>

</article>