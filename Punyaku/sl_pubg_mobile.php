<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%PUBG Mobile%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get UC sales summary
$sales_summary = [];
$stmt = $conn->prepare("
    SELECT 
        COUNT(t.id) AS total_transactions,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        ROUND(SUM(t.profit) / SUM(t.final_amount) * 100, 2) AS profit_margin,
        SUM(CASE WHEN tp.package_type = 'weekly_pass' THEN 1 ELSE 0 END) AS pass_count,
        SUM(CASE WHEN tp.package_type = 'weekly_pass' THEN t.final_amount ELSE 0 END) AS pass_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get UC package sales distribution
$uc_distribution = [];
$stmt = $conn->prepare("
    SELECT 
        tp.in_game_currency,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY tp.in_game_currency
    ORDER BY total_sales DESC
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$uc_distribution = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
        <h3 class="chart-title">UC Package Distribution</h3>
        <canvas id="ucChart"></canvas>
    </div>
    
    <script>
        // UC Distribution Pie Chart
        const ucCtx = document.getElementById('ucChart').getContext('2d');
        const ucChart = new Chart(ucCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($uc_distribution, 'in_game_currency')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($uc_distribution, 'total_sales')); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            }
        });
    </script>
</body>
</html>