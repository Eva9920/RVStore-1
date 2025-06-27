<?php
require_once 'config.php';
requireAuth();

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = null;
if ($game_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM games WHERE id = ? AND name LIKE '%Honor of Kings%'");
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();
}

if (!$game) {
    header("Location: manage_product.php");
    exit();
}

// Get Tokens sales data
$token_sales = [];
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
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$token_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get hero/skin sales
$item_sales = [];
$stmt = $conn->prepare("
    SELECT 
        tp.package_name,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    JOIN topup_packages tp ON t.package_id = tp.id
    WHERE t.game_id = ? AND t.status = 'success' AND tp.package_type = 'special'
    GROUP BY tp.package_name
    ORDER BY total_sales DESC
    LIMIT 5
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$item_sales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get monthly growth
$monthly_growth = [];
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(t.transaction_time, '%Y-%m') AS month,
        COUNT(t.id) AS transaction_count,
        SUM(t.final_amount) AS total_sales
    FROM transactions t
    WHERE t.game_id = ? AND t.status = 'success'
    GROUP BY DATE_FORMAT(t.transaction_time, '%Y-%m')
    ORDER BY month
");
$stmt->bind_param("i", $game_id);
$stmt->execute();
$monthly_growth = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Prepare growth data
$growth_labels = [];
$growth_data = [];
foreach ($monthly_growth as $month) {
    $growth_labels[] = $month['month'];
    $growth_data[] = $month['total_sales'];
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
        <p>Tokens and hero/skin bundle performance</p>
    </div>
    
    <div class="chart-container">
        <h3 class="chart-title">Monthly Sales Growth</h3>
        <canvas id="growthChart"></canvas>
    </div>
    
    <div class="table-container">
        <h3 class="chart-title">Top Token Packages</h3>
        <table>
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Tokens</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                    <th>Profit Margin</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($token_sales as $package): ?>
                <tr class="hok-highlight">
                    <td><?php echo htmlspecialchars($package['package_name']); ?></td>
                    <td><?php echo htmlspecialchars($package['in_game_currency']); ?></td>
                    <td><?php echo number_format($package['transaction_count']); ?></td>
                    <td>Rp<?php echo number_format($package['total_sales'], 0, ',', '.'); ?></td>
                    <td><?php echo number_format($package['profit_margin'], 2); ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="table-container">
        <h3 class="chart-title">Popular Hero/Skin Bundles</h3>
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Transactions</th>
                    <th>Total Sales</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($item_sales as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['package_name']); ?></td>
                    <td><?php echo number_format($item['transaction_count']); ?></td>
                    <td>Rp<?php echo number_format($item['total_sales'], 0, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        // Growth Chart
        const growthCtx = document.getElementById('growthChart').getContext('2d');
        const growthChart = new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($growth_labels); ?>,
                datasets: [{
                    label: 'Monthly Sales',
                    data: <?php echo json_encode($growth_data); ?>,
                    borderColor: '#FF9800',
                    backgroundColor: 'rgba(255, 152, 0, 0.1)',
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
    </script>
</body>
</html>
