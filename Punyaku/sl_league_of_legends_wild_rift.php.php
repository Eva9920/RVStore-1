<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%League of Legends: Wild Rift%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get Wild Cores sales data
$sales_summary = [];
$stmt = $conn->prepare("
    SELECT 
        COUNT(t.id) AS total_transactions,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        ROUND(SUM(t.profit) / SUM(t.final_amount) * 100, 2) AS profit_margin,
        SUM(tp.in_game_currency) AS total_cores_sold,
        SUM(CASE WHEN tp.package_type = 'special' THEN t.final_amount ELSE 0 END) AS bundle_sales,
        SUM(CASE WHEN tp.package_name LIKE '%Event%' THEN 1 ELSE 0 END) AS event_passes
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get top Wild Core packages
$core_packages = [];
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
    WHERE t.game_id = ? AND t.status = 'success' AND tp.package_type = 'regular'
    GROUP BY tp.package_name, tp.in_game_currency, tp.profit_margin
    ORDER BY total_sales DESC
    LIMIT 5
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$core_packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get popular skin bundles
$skin_bundles = [];
$stmt = $conn->prepare("
    SELECT 
        tp.package_name,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales,
        AVG(t.final_amount) AS avg_sale
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success' AND tp.package_type = 'special'
    GROUP BY tp.package_name
    ORDER BY total_sales DESC
    LIMIT 5
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$skin_bundles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly Wild Core sales
$monthly_sales = [];
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(t.transaction_time, '%Y-%m') AS month,
        SUM(tp.in_game_currency) AS cores_sold,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY DATE_FORMAT(t.transaction_time, '%Y-%m')
    ORDER BY month
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$monthly_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$month_labels = [];
$cores_data = [];
$sales_data = [];
foreach ($monthly_sales as $month) {
    $month_labels[] = $month['month'];
    $cores_data[] = $month['cores_sold'];
    $sales_data[] = $month['total_sales'];
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
        <p>Wild Cores and skin bundles performance analysis</p>
    </div>
    
    <div class="summary-container">
        <div class="summary-card">
            <h3>Total Wild Cores Sold</h3>
            <div class="value"><?php echo number_format($sales_summary['total_cores_sold'] ?? 0); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Sales</h3>
            <div class="value">Rp<?php echo number_format($sales_summary['total_sales'] ?? 0, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card">
            <h3>Skin Bundle Sales</h3>
            <div class="value">Rp<?php echo number_format($sales_summary['bundle_sales'] ?? 0, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card">
            <h3>Event Passes</h3>
            <div class="value"><?php echo number_format($sales_summary['event_passes'] ?? 0); ?></div>
        </div>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Monthly Wild Cores Sales</h3>
        <canvas id="monthlyChart"></canvas>
    </div>
    
    <div class="table-container">
        <h3 class="chart-title">Top Wild Core Packages</h3>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Wild Cores</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Profit</th>
                    <th>Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($core_packages as $package): ?>
                <tr class="wildrift-highlight">
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
    
    <div class="table-container">
        <h3 class="chart-title">Popular Skin Bundles</h3>
        <table>
            <thead>
                <tr>
                    <th>Bundle Name</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Avg. Sale</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($skin_bundles as $bundle): ?>
                <tr class="skin-bundle">
                    <td><?php echo htmlspecialchars($bundle['package_name']); ?></td>
                    <td><?php echo number_format($bundle['transaction_count']); ?></td>
                    <td>Rp<?php echo number_format($bundle['total_sales'], 0, ',', '.'); ?></td>
                    <td>Rp<?php echo number_format($bundle['avg_sale'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Wild Cores vs Total Sales</h3>
        <canvas id="coresVsSalesChart"></canvas>
    </div>
    
    <script>
        // Monthly Wild Cores Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($month_labels); ?>,
                datasets: [{
                    label: 'Wild Cores Sold',
                    data: <?php echo json_encode($cores_data); ?>,
                    borderColor: '#9c27b0',
                    backgroundColor: 'rgba(156, 39, 176, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false
                    }
                }
            }
        });
        
        // Wild Cores vs Sales Chart
        const coresVsSalesCtx = document.getElementById('coresVsSalesChart').getContext('2d');
        const coresVsSalesChart = new Chart(coresVsSalesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($month_labels); ?>,
                datasets: [
                    {
                        label: 'Wild Cores Sold',
                        data: <?php echo json_encode($cores_data); ?>,
                        backgroundColor: 'rgba(156, 39, 176, 0.7)',
                        borderColor: 'rgba(156, 39, 176, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Sales (Rp)',
                        data: <?php echo json_encode($sales_data); ?>,
                        backgroundColor: 'rgba(255, 152, 0, 0.7)',
                        borderColor: 'rgba(255, 152, 0, 1)',
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
    </script>
</body>
</html>