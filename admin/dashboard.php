<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Get statistics for the dashboard
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalQuizzes = $pdo->query("SELECT COUNT(*) FROM quizzes")->fetchColumn();
$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalResults = $pdo->query("SELECT COUNT(*) FROM quiz_results")->fetchColumn();

// Get recent quiz results
$recentResults = $pdo->query("
    SELECT qr.*, u.username, q.title 
    FROM quiz_results qr
    JOIN users u ON qr.user_id = u.user_id
    JOIN quizzes q ON qr.quiz_id = q.quiz_id
    ORDER BY qr.completed_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Get recently added quizzes
$recentQuizzes = $pdo->query("
    SELECT q.*, u.username as created_by_name
    FROM quizzes q
    JOIN users u ON q.created_by = u.user_id
    ORDER BY q.created_at DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Quiz Application</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .dashboard {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .sidebar {
            background-color: #2c3e50;
            color: white;
            padding: 20px;
            border-radius: 8px;
            height: fit-content;
        }
        
        .sidebar ul {
            list-style: none;
        }
        
        .sidebar li {
            margin-bottom: 10px;
        }
        
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px 10px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .sidebar a:hover, .sidebar a.active {
            background-color: #3498db;
        }
        
        .main-content {
            display: grid;
            gap: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .stat-card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #7f8c8d;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .recent-activity {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .activity-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .activity-table th, .activity-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .activity-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .activity-table tr:hover {
            background-color: #f8f9fa;
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
        
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .admin-title {
            font-size: 1.8rem;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>Quiz Application</h1>
            <div class="auth-links">
                <span>Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="btn btn-logout">Logout</a>
            </div>
        </header>
        
        <div class="dashboard">
            <!-- Sidebar Navigation -->
            <div class="sidebar">
                <h3>Admin Menu</h3>
                <ul>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="add_question.php">Add Questions</a></li>
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="admin_reattempt_requests.php">Approve Rreattempt</a></li>
                    <li><a href="#">Manage Users</a></li>
                    <li><a href="view_results.php">View Results</a></li>
                </ul>
            </div>
            
            <!-- Main Content Area -->
            <div class="main-content">
                <div class="admin-header">
                    <h2 class="admin-title">Admin Dashboard</h2>
                    <a href="add_question.php" class="btn btn-primary">Add New Question</a>
                </div>
                
                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Total Users</h3>
                        <p><?php echo $totalUsers; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Quizzes</h3>
                        <p><?php echo $totalQuizzes; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Total Questions</h3>
                        <p><?php echo $totalQuestions; ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Quiz Attempts</h3>
                        <p><?php echo $totalResults; ?></p>
                    </div>
                </div>
                
                <!-- Recent Quiz Results -->
                <div class="recent-activity">
                    <h3>Recent Quiz Results</h3>
                    <?php if (!empty($recentResults)): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Quiz</th>
                                    <th>Score</th>
                                    <th>Correct</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentResults as $result): ?>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No quiz results found.</p>
                    <?php endif; ?>
                </div>
                
                <!-- Recently Added Quizzes -->
                <div class="recent-activity">
                    <h3>Recently Added Quizzes</h3>
                    <?php if (!empty($recentQuizzes)): ?>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Created By</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentQuizzes as $quiz): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($quiz['title']); ?></td>
                                        <td><?php echo htmlspecialchars($quiz['created_by_name']); ?></td>
                                        <td>
                                            <span class="<?php echo $quiz['is_active'] ? 'score-high' : 'score-low'; ?>">
                                                <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No quizzes found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Quiz Application. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
