<?php
require_once 'config.php';
requireAuth();

// Get date range (default to last 30 days)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime(''));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Get sales summary
$summary_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(amount) as total_sales,
        AVG(amount) as average_sale
    FROM transactions
    WHERE status = 'success'
    AND DATE(transaction_time) BETWEEN ? AND ?
");
$summary_stmt->bind_param("ss", $date_from, $date_to);
$summary_stmt->execute();
$summary_result = $summary_stmt->get_result();
$summary = $summary_result->fetch_assoc();
$summary_stmt->close();

// Get sales by game
$games_stmt = $conn->prepare("
    SELECT 
        g.name as game_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN games g ON t.game_id = g.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY g.name
    ORDER BY total_sales DESC
");
$games_stmt->bind_param("ss", $date_from, $date_to);
$games_stmt->execute();
$games_result = $games_stmt->get_result();
$sales_by_game = $games_result->fetch_all(MYSQLI_ASSOC);
$games_stmt->close();

// Get sales by payment method
$pm_stmt = $conn->prepare("
    SELECT 
        pm.name as payment_method_name,
        COUNT(t.id) as transaction_count,
        SUM(t.amount) as total_sales
    FROM transactions t
    JOIN payment_methods pm ON t.payment_method_id = pm.id
    WHERE t.status = 'success'
    AND DATE(t.transaction_time) BETWEEN ? AND ?
    GROUP BY pm.name
    ORDER BY total_sales DESC
");
$pm_stmt->bind_param("ss", $date_from, $date_to);
$pm_stmt->execute();
$pm_result = $pm_stmt->get_result();
$sales_by_payment_method = $pm_result->fetch_all(MYSQLI_ASSOC);
$pm_stmt->close();


// Sales data for chart (daily sales for selected range)
$chartLabels = [];
$chartData = [];
$daily_sales_stmt = $conn->prepare("
    SELECT DATE(transaction_time) as sale_date, SUM(amount) as daily_revenue 
    FROM transactions 
    WHERE status = 'success' AND DATE(transaction_time) BETWEEN ? AND ?
    GROUP BY sale_date 
    ORDER BY sale_date ASC
");
$daily_sales_stmt->bind_param("ss", $date_from, $date_to);
$daily_sales_stmt->execute();
$daily_sales_result = $daily_sales_stmt->get_result();

$daily_sales = [];
while($row = $daily_sales_result->fetch_assoc()) {
    $daily_sales[$row['sale_date']] = $row['daily_revenue'];
}
$daily_sales_stmt->close();

// Populate chart data for all days in the range
$current_date = strtotime($date_from);
$end_date = strtotime($date_to);

while ($current_date <= $end_date) {
    $date_str = date('Y-m-d', $current_date);
    $chartLabels[] = date('d M', $current_date);
    $chartData[] = isset($daily_sales[$date_str]) ? round($daily_sales[$date_str] / 1000000, 2) : 0; // Convert to millions
    $current_date = strtotime('+1 day', $current_date);
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<style>
        :root {
            --primary: #4e73df;
            --primary-dark: #3a5bc7;
            --secondary: #858796;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --primary-color: #6c5ce7;
            --dark-color: #2d3436;
            --light-color: #f7f7f7;
            --sidebar-width: 250px;
            --transition: all 0.3s ease;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fc;
            margin: 0;
            display: flex;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100%;
            z-index: 100;
        }

        img {
            width: 105px;
            height: 65px;
            margin-top: 20px;
            margin-bottom: -10px;
            margin-left: 65px;
        }


        .logo {
            padding: 20px;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-align: center;
            border-bottom: 1px solid #eee;
        }

        .sidebar nav ul {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar nav ul li {
            margin: 5px 0;
        }

        .sidebar nav ul li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--dark-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .sidebar nav ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar nav ul li.active a,
        .sidebar nav ul li a:hover {
            background-color: var(--light-color);
            color: var(--primary-color);
            border-left: 4px solid var(--primary-color);
        }

        .main-content {
            margin-left: 250px;
            flex-grow: 1;
            padding: 20px;
            width: calc(100% - 250px);
        }
        /* Topbar Styles */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 25px;
            background: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            border-radius: 10px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .topbar h2 {
            font-weight: 600;
            color: #1a237e;
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-container input {
            width: 100%;
            padding: 10px 15px 10px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-container input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(92, 107, 192, 0.2);
        }

        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
        }

        .topbar-icons {
            display: flex;
            gap: 20px;
        }

        .topbar-icons i {
            font-size: 20px;
            color: #5c6bc0;
            cursor: pointer;
            transition: color 0.3s;
        }

        .topbar-icons i:hover {
            color: #1a237e;
        }

        .card {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .card-header {
            font-size: 1.2em;
            font-weight: bold;
            margin-bottom: 15px;
            color: #555;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th, .table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .table th {
            background-color: #f2f2f2;
            font-weight: bold;
            color: #333;
        }
        .table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .table tr:hover {
            background-color: #f1f1f1;
        }
        .badge {
            display: inline-block;
            padding: .35em .65em;
            font-size: .75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: .375rem;
        }
        .badge-success { background-color: #28a745; color: white; }
        .badge-danger { background-color: #dc3545; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-info { background-color: #17a2b8; color: white; }

        /* Filter and Search styles */
        .filter-section {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: #555;
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .filter-group button {
            padding: 10px 15px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .filter-group button:hover {
            background-color: #2e59d9;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #e9f0f9;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .summary-card .title {
            font-size: 1em;
            color: #666;
            margin-bottom: 10px;
        }
        .summary-card .value {
            font-size: 1.8em;
            font-weight: bold;
            color: #4e73df;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <img src="RVS_LOGO.png" alt="RVStore Logo">
            <nav>
                <ul>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="transaction_history.php"><i class="fas fa-history"></i> Transaction History</a></li>
                    <li><a href="manage_product.php"><i class="fas fa-boxes"></i> Manage Product</a></li>
                    <li class="active"><a href="sales_report.php"><i class="fas fa-chart-bar"></i> Sales Report</a></li>
                    <li><a href="accounts.php"><i class="fas fa-users"></i> Accounts</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>

    <div class="main-content">
            <!-- Topbar -->
            <div class="topbar">
                <h2>Sales Report</h2>
                <form method="GET" class="search-container">
                    <input type="text" name="search" placeholder="Search product..." value="<?= isset($_GET['search']) ? $_GET['search'] : ''; ?>">
                    <i class="fas fa-search"></i>
                </form>
                <div class="topbar-icons">
                    <i class="fas fa-cog"></i>
                    <i class="fas fa-bell"></i>
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>

        <div class="card">
            <div class="card-header">
                <h3>Sales Overview</h3>
            </div>
            <form method="GET" action="" class="filter-section">
                <div class="filter-group">
                    <label for="date_from">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="filter-group">
                    <label for="date_to">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="filter-group">
                    <button type="submit" class="btn">Apply Filter</button>
                </div>
                <div class="filter-group">
                    <button type="button" class="btn" onclick="window.location.href='sales_report.php'">Reset Dates</button>
                </div>
            </form>

            <div class="summary-cards">
                <div class="summary-card">
                    <div class="title">Total Transactions</div>
                    <div class="value"><?php echo number_format($summary['total_transactions']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Total Sales</div>
                    <div class="value"><?php echo formatCurrency($summary['total_sales']); ?></div>
                </div>
                <div class="summary-card">
                    <div class="title">Average Sale</div>
                    <div class="value"><?php echo formatCurrency($summary['average_sale']); ?></div>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Daily Sales Chart</h3>
                </div>
                <canvas id="salesChart" style="max-height: 400px;"></canvas>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Sales By Game</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Game Name</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_by_game)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No sales by game found for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_by_game as $sale) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['game_name']); ?></td>
                                        <td><?php echo $sale['transaction_count']; ?></td>
                                        <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-top: 20px;">
                <div class="card-header">
                    <h3>Sales By Payment Method</h3>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Payment Method</th>
                                <th>Transactions</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sales_by_payment_method)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No sales by payment method found for this period.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($sales_by_payment_method as $sale) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sale['payment_method_name']); ?></td>
                                        <td><?php echo $sale['transaction_count']; ?></td>
                                        <td><?php echo formatCurrency($sale['total_sales']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        var ctx = document.getElementById('salesChart').getContext('2d');
        var salesChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chartLabels); ?>,
                datasets: [{
                    label: 'Sales (in Millions IDR)',
                    data: <?php echo json_encode($chartData); ?>,
                    backgroundColor: 'rgba(78, 115, 223, 0.5)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'IDR ' + value + 'M';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true // Show legend for the bar chart
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'IDR ' + (context.raw * 1000000).toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>