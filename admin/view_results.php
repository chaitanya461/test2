<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Handle filters
$quiz_filter = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : null;
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// Handle CSV download
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=quiz_results_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers - detailed table format
    fputcsv($output, array(
        'Result ID',
        'User ID',
        'Username',
        'Quiz ID',
        'Quiz Title',
        'Score (%)',
        'Correct Answers',
        'Total Questions',
        'Percentage',
        'Date Completed',
        'Time Taken (seconds)'
    ));
    
    // Base query for CSV
    $query = "
        SELECT qr.*, u.username, q.title 
        FROM quiz_results qr
        JOIN users u ON qr.user_id = u.user_id
        JOIN quizzes q ON qr.quiz_id = q.quiz_id
    ";
    
    // Add conditions based on filters
    $conditions = [];
    $params = [];
    
    if ($quiz_filter) {
        $conditions[] = "qr.quiz_id = ?";
        $params[] = $quiz_filter;
    }
    
    if ($user_filter) {
        $conditions[] = "qr.user_id = ?";
        $params[] = $user_filter;
    }
    
    if ($date_from) {
        $conditions[] = "qr.completed_at >= ?";
        $params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $conditions[] = "qr.completed_at <= ?";
        $params[] = $date_to . ' 23:59:59';
    }
    
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $query .= " ORDER BY qr.completed_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Calculate time taken if available
        $time_taken = 'N/A';
        if (isset($row['start_time']) && $row['start_time']) {
            $start = strtotime($row['start_time']);
            $end = strtotime($row['completed_at']);
            $time_taken = $end - $start;
        }
        
        fputcsv($output, array(
            $row['result_id'],
            $row['user_id'],
            $row['username'],
            $row['quiz_id'],
            $row['title'],
            round($row['score'], 2),
            $row['correct_answers'],
            $row['total_questions'],
            round($row['score'], 2) . '%',
            date('Y-m-d H:i:s', strtotime($row['completed_at'])),
            $time_taken
        ));
    }
    
    fclose($output);
    exit;
}

// Base query for HTML display
$query = "
    SELECT qr.*, u.username, q.title 
    FROM quiz_results qr
    JOIN users u ON qr.user_id = u.user_id
    JOIN quizzes q ON qr.quiz_id = q.quiz_id
";

// Add conditions based on filters
$conditions = [];
$params = [];

if ($quiz_filter) {
    $conditions[] = "qr.quiz_id = ?";
    $params[] = $quiz_filter;
}

if ($user_filter) {
    $conditions[] = "qr.user_id = ?";
    $params[] = $user_filter;
}

if ($date_from) {
    $conditions[] = "qr.completed_at >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $conditions[] = "qr.completed_at <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY qr.completed_at DESC";

// Get all results
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get quizzes for filter dropdown
$quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);

// Get users for filter dropdown
$users = $pdo->query("SELECT user_id, username FROM users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Results | Quiz Application</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .results-container {
            margin-top: 20px;
        }
        
        .filter-form {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .results-table th {
            background-color: #3498db;
            color: white;
            text-align: left;
            padding: 12px 15px;
            font-weight: 600;
        }
        
        .results-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }
        
        .results-table tr:hover {
            background-color: #f5f7fa;
        }
        
        .score-cell {
            font-weight: bold;
        }
        
        .score-high {
            color: #27ae60;
        }
        
        .score-medium {
            color: #f39c12;
        }
        
        .score-low {
            color: #e74c3c;
        }
        
        .action-cell {
            white-space: nowrap;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #7f8c8d;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, 
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #3498db;
        }
        
        .pagination span.current {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        .btn-view {
            background-color: #2ecc71;
            color: white;
            padding: 5px 10px;
            font-size: 13px;
        }
        
        .btn-view:hover {
            background-color: #27ae60;
        }
        
        .btn-download {
            background-color: #9b59b6;
            color: white;
        }
        
        .btn-download:hover {
            background-color: #8e44ad;
        }
        
        .btn-back {
            background-color: #34495e;
            color: white;
        }
        
        .btn-back:hover {
            background-color: #2c3e50;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'admin_header.php'; ?>
        
        <div class="dashboard">
            <?php include 'admin_sidebar.php'; ?>
            
            <div class="main-content">
                <div class="admin-header">
                    <h2 class="admin-title">Quiz Results</h2>
                </div>
                
                <!-- Action Buttons -->
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-back">← Back to Dashboard</a>
                    <a href="view_results.php?<?php echo http_build_query($_GET); ?>&download=csv" class="btn btn-download">Download Results (CSV)</a>
                </div>
                
                <!-- Filter Form -->
                <div class="filter-form">
                    <form method="get" action="view_results.php">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="quiz_id">Quiz</label>
                                <select id="quiz_id" name="quiz_id">
                                    <option value="">All Quizzes</option>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <option value="<?php echo $quiz['quiz_id']; ?>" <?php echo $quiz_filter == $quiz['quiz_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($quiz['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="user_id">User</label>
                                <select id="user_id" name="user_id">
                                    <option value="">All Users</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['user_id']; ?>" <?php echo $user_filter == $user['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_from">Date From</label>
                                <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to">Date To</label>
                                <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply Filters</button>
                            <a href="view_results.php" class="btn btn-secondary">Reset Filters</a>
                        </div>
                    </form>
                </div>
                
                <!-- Results Table -->
                <div class="results-container">
                    <?php if (!empty($results)): ?>
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Correct Answers</th>
                                    <th>Date Completed</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['username']); ?></td>
                                        <td><?php echo htmlspecialchars($result['title']); ?></td>
                                        <td class="score-cell 
                                            <?php 
                                            if ($result['score'] >= 70) echo 'score-high';
                                            elseif ($result['score'] >= 40) echo 'score-medium';
                                            else echo 'score-low';
                                            ?>">
                                            <?php echo round($result['score'], 2); ?>%
                                        </td>
                                        <td><?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?></td>
                                        <td><?php echo date('M j, Y g:i a', strtotime($result['completed_at'])); ?></td>
                                        <td class="action-cell">
                                            <a href="result_detail.php?result_id=<?php echo $result['result_id']; ?>" class="btn btn-view">View Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No quiz results found matching your criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php include 'admin_footer.php'; ?>
    </div>
</body>
</html>
