<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%Clash of Clans%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get Gems sales summary
$sales_summary = [];
$stmt = $conn->prepare("
    SELECT 
        COUNT(t.id) AS total_transactions,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        ROUND(SUM(t.profit) / SUM(t.final_amount) * 100, 2) AS profit_margin,
        SUM(tp.in_game_currency) AS total_gems_sold,
        AVG(tp.in_game_currency) AS avg_gems_per_transaction
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get Gold Pass vs Regular Gems comparison
$sales_comparison = [];
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN tp.package_name LIKE '%Gold Pass%' THEN 'Gold Pass'
            ELSE 'Regular Gems'
        END AS package_type,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales,
        SUM(tp.in_game_currency) AS total_gems
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY package_type
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_comparison = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get top gem packages
$top_packages = [];
$stmt = $conn->prepare("
    SELECT 
        tp.package_name,
        tp.in_game_currency,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        tp.profit_margin
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY tp.package_name, tp.in_game_currency, tp.profit_margin
    ORDER BY total_sales DESC
    LIMIT 5
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$top_packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$package_types = [];
$sales_data = [];
$gems_data = [];
foreach ($sales_comparison as $item) {
    $package_types[] = $item['package_type'];
    $sales_data[] = $item['total_sales'];
    $gems_data[] = $item['total_gems'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($game['name']); ?> - Sales Report</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            color: #6441a5;
        }
        
        .summary-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .summary-card h3 {
            margin-top: 0;
            color: #666;
            font-size: 16px;
        }
        
        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .summary-card .profit {
            color: #00a854;
        }
        
        .summary-card .loss {
            color: #d32f2f;
        }
        
        .chart-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-title {
            text-align: center;
            margin-bottom: 20px;
            color: #6441a5;
            font-weight: 600;
        }
        
        .table-container {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #6441a5;
            color: white;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .profit-cell {
            color: #00a854;
            font-weight: bold;
        }
        
        .loss-cell {
            color: #d32f2f;
            font-weight: bold;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #6441a5;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            transition: background-color 0.3s;
        }
        
        .back-btn:hover {
            background-color: #4e2d8a;
        }
        
        .package-highlight {
            background-color: #f0e6ff;
        }
    </style>
</head>
<body>
    <a href="manage_product.php" class="back-btn">Back to Products</a>
    
    <div class="header">
        <h1><?php echo htmlspecialchars($game['name']); ?> Sales Report</h1>
        <p>Gems packages and Gold Pass performance analysis</p>
    </div>
    
    <div class="summary-container">
        <div class="summary-card">
            <h3>Total Gems Sold</h3>
            <div class="value"><?php echo number_format($sales_summary['total_gems_sold'] ?? 0); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Sales</h3>
            <div class="value">Rp<?php echo number_format($sales_summary['total_sales'] ?? 0, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card">
            <h3>Avg. Gems/Transaction</h3>
            <div class="value"><?php echo number_format($sales_summary['avg_gems_per_transaction'] ?? 0); ?></div>
        </div>
        <div class="summary-card">
            <h3>Profit Margin</h3>
            <div class="value <?php echo ($sales_summary['profit_margin'] >= 0) ? 'profit' : 'loss'; ?>">
                <?php echo number_format($sales_summary['profit_margin'] ?? 0, 2); ?>%
            </div>
        </div>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Gold Pass vs Regular Gems</h3>
        <canvas id="salesComparisonChart"></canvas>
    </div>
    
    <div class="table-container">
        <h3 class="chart-title">Top Gem Packages</h3>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Gems</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Profit</th>
                    <th>Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_packages as $package): ?>
                <tr class="<?php echo (strpos($package['package_name'], 'Gold Pass') !== false) ? 'gold-pass' : 'coc-highlight'; ?>">
                    <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                    <td><?php echo number_format($package['in_game_currency']); ?></td>
                    <td><?php echo number_format($package['transaction_count']); ?></td>
                    <td>Rp<?php echo number_format($package['total_sales'], 0, ',', '.'); ?></td>
                    <td class="<?php echo ($package['total_profit'] >= 0) ? 'profit-cell' : 'loss-cell'; ?>">
                        Rp<?php echo number_format($package['total_profit'], 0, ',', '.'); ?>
                    </td>
                    <td><?php echo number_format($package['profit_margin'], 2); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Gems Distribution</h3>
        <canvas id="gemsChart"></canvas>
    </div>
    
    <script>
        // Sales Comparison Chart
        const salesCompCtx = document.getElementById('salesComparisonChart').getContext('2d');
        const salesCompChart = new Chart(salesCompCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($package_types); ?>,
                datasets: [
                    {
                        label: 'Total Sales (Rp)',
                        data: <?php echo json_encode($sales_data); ?>,
                        backgroundColor: 'rgba(255, 193, 7, 0.7)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Gems',
                        data: <?php echo json_encode($gems_data); ?>,
                        backgroundColor: 'rgba(76, 175, 80, 0.7)',
                        borderColor: 'rgba(76, 175, 80, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Gems Distribution Chart
        const gemsCtx = document.getElementById('gemsChart').getContext('2d');
        const gemsChart = new Chart(gemsCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($top_packages, 'package_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($top_packages, 'in_game_currency')); ?>,
                    backgroundColor: [
                        '#FFC107', '#FF9800', '#FF5722', '#795548', '#607D8B'
                    ]
                }]
            },
            options: {
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.raw.toLocaleString() + ' gems';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>