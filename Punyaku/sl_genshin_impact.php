<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%Genshin Impact%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get Genesis Crystals sales data
$sales_summary = [];
$stmt = $conn->prepare("
    SELECT 
        COUNT(t.id) AS total_transactions,
        SUM(t.final_amount) AS total_sales,
        SUM(t.profit) AS total_profit,
        ROUND(SUM(t.profit) / SUM(t.final_amount) * 100, 2) AS profit_margin,
        SUM(CASE WHEN tp.package_type = 'special' THEN 1 ELSE 0 END) AS bundle_count,
        SUM(CASE WHEN tp.package_type = 'special' THEN t.final_amount ELSE 0 END) AS bundle_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_summary = $stmt->get_result()->fetch_assoc();

// Get Welkin Moon vs Genesis Crystals comparison
$sales_comparison = [];
$stmt = $conn->prepare("
    SELECT 
        tp.package_type,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY tp.package_type
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$sales_comparison = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$package_types = [];
$sales_data = [];
foreach ($sales_comparison as $item) {
    $package_types[] = ucfirst(str_replace('_', ' ', $item['package_type']));
    $sales_data[] = $item['total_sales'];
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
        <p>Genesis Crystals and Welkin Moon analysis</p>
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
            <h3>Bundle Sales</h3>
            <div class="value">Rp<?php echo number_format($sales_summary['bundle_sales'] ?? 0, 0, ',', '.'); ?></div>
            <small><?php echo number_format($sales_summary['bundle_count'] ?? 0); ?> bundles sold</small>
        </div>
        <div class="summary-card">
            <h3>Profit Margin</h3>
            <div class="value <?php echo ($sales_summary['profit_margin'] >= 0) ? 'profit' : 'loss'; ?>">
                <?php echo number_format($sales_summary['profit_margin'] ?? 0, 2); ?>%
            </div>
        </div>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Sales by Package Type</h3>
        <canvas id="packageTypeChart"></canvas>
    </div>
    
    <script>
        // Package Type Chart
        const packageTypeCtx = document.getElementById('packageTypeChart').getContext('2d');
        const packageTypeChart = new Chart(packageTypeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($package_types); ?>,
                datasets: [{
                    data: <?php echo json_encode($sales_data); ?>,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0'
                    ]
                }]
            }
        });
    </script>
</body>
</html>
