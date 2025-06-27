<?php
require_once 'config.php';
requireAuth();

// Get game ID from URL
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch game details from database
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ?");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get sales data for this game
$sales_summary = [];
$stmt = $conn->prepare("
    SELECT 
        COUNT(t.id) AS total_transactions,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        ROUND(SUM(t.profit) / SUM(t.final_amount) * 100, 2) AS profit_margin
    FROM transactions t
    WHERE t.game_id = ? AND t.status = 'success'
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get top packages for this game
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

// Get monthly sales data for charts
$monthly_sales = [];
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(t.transaction_time, '%Y-%m') AS month,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit
    FROM transactions t
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY DATE_FORMAT(t.transaction_time, '%Y-%m')
    ORDER BY month
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$monthly_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare data for charts
$chart_labels = [];
$chart_sales = [];
$chart_profit = [];
$chart_margin = [];

foreach ($monthly_sales as $month) {
    $chart_labels[] = $month['month'];
    $chart_sales[] = $month['total_sales'];
    $chart_profit[] = $month['total_profit'];
    $chart_margin[] = ($month['total_sales'] > 0) ? round(($month['total_profit'] / $month['total_sales']) * 100, 2) : 0;
}

// Get recent transactions
$recent_transactions = [];
$stmt = $conn->prepare("
    SELECT 
        t.transaction_code,
        tp.package_name,
        t.final_amount,
        t.profit,
        t.transaction_time
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    ORDER BY t.transaction_time DESC
    LIMIT 5
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$recent_transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <p>Comprehensive sales and profit analysis</p>
    </div>
    
    <div class="summary-container">
        <div class="summary-card">
            <h3>Total Transactions</h3>
            <div class="value"><?php echo number_format($sales_summary['total_transactions'] ?? 0); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Sales</h3>
            <div class="value">Rp<?php echo number_format($sales_summary['total_sales'] ?? 0, 0, ',', '.'); ?></div>
        </div>
        <div class="summary-card">
            <h3>Total Profit</h3>
            <div class="value <?php echo ($sales_summary['total_profit'] >= 0) ? 'profit' : 'loss'; ?>">
                Rp<?php echo number_format($sales_summary['total_profit'] ?? 0, 0, ',', '.'); ?>
            </div>
        </div>
        <div class="summary-card">
            <h3>Profit Margin</h3>
            <div class="value <?php echo ($sales_summary['profit_margin'] >= 0) ? 'profit' : 'loss'; ?>">
                <?php echo number_format($sales_summary['profit_margin'] ?? 0, 2); ?>%
            </div>
        </div>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Monthly Sales Performance</h3>
        <canvas id="salesChart"></canvas>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Monthly Profit Margin</h3>
        <canvas id="marginChart"></canvas>
    </div>
    
    <div class="table-container">
        <h3 class="chart-title">Top Performing Packages</h3>
        <table>
            <thead>
                <tr>
                    <th>Package Name</th>
                    <th>In-Game Currency</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Total Profit</th>
                    <th>Profit Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_packages as $package): ?>
                <tr>
                    <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                    <td><?php echo htmlspecialchars($package['in_game_currency']); ?></td>
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
        <h3 class="chart-title">Recent Transactions</h3>
        <table>
            <thead>
                <tr>
                    <th>Transaction Code</th>
                    <th>Package</th>
                    <th>Amount</th>
                    <th>Profit</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_transactions as $transaction): ?>
                <tr>
                    <td><?php echo htmlspecialchars($transaction['transaction_code']); ?></td>
                    <td><?php echo htmlspecialchars($transaction['package_name']); ?></td>
                    <td>Rp<?php echo number_format($transaction['final_amount'], 0, ',', '.'); ?></td>
                    <td class="<?php echo ($transaction['profit'] >= 0) ? 'profit-cell' : 'loss-cell'; ?>">
                        Rp<?php echo number_format($transaction['profit'], 0, ',', '.'); ?>
                    </td>
                    <td><?php echo date('d M Y H:i', strtotime($transaction['transaction_time'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [
                    {
                        label: 'Total Sales',
                        data: <?php echo json_encode($chart_sales); ?>,
                        backgroundColor: 'rgba(100, 65, 165, 0.7)',
                        borderColor: 'rgba(100, 65, 165, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Total Profit',
                        data: <?php echo json_encode($chart_profit); ?>,
                        backgroundColor: 'rgba(0, 200, 83, 0.7)',
                        borderColor: 'rgba(0, 200, 83, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Amount (Rp)'
                        }
                    }
                }
            }
        });
        
        // Margin Chart
        const marginCtx = document.getElementById('marginChart').getContext('2d');
        const marginChart = new Chart(marginCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Profit Margin (%)',
                    data: <?php echo json_encode($chart_margin); ?>,
                    backgroundColor: 'rgba(255, 159, 64, 0.2)',
                    borderColor: 'rgba(255, 159, 64, 1)',
                    borderWidth: 2,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: false,
                        title: {
                            display: true,
                            text: 'Margin (%)'
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>