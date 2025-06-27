<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%Call of Duty Mobile%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get CP package sales
$cp_packages = [];
$stmt = $conn->prepare("
    SELECT 
        tp.package_name,
        tp.in_game_currency,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY tp.package_name, tp.in_game_currency
    ORDER BY total_sales DESC
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$cp_packages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    
    <div class="table-container">
        <h3 class="chart-title">CP Package Sales</h3>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>CP Amount</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cp_packages as $package): ?>
                <tr>
                    <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                    <td><?php echo htmlspecialchars($package['in_game_currency']); ?></td>
                    <td><?php echo number_format($package['transaction_count']); ?></td>
                    <td>Rp<?php echo number_format($package['total_sales'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>