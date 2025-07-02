<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['selected_questions'])) {
        $question_ids = array_map('intval', $_POST['selected_questions']);
        
        if ($_POST['bulk_action'] === 'delete') {
            try {
                $pdo->beginTransaction();
                
                // Delete from questions table
                $placeholders = implode(',', array_fill(0, count($question_ids), '?'));
                $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id IN ($placeholders)");
                $stmt->execute($question_ids);
                
                // Delete related user responses
                $stmt = $pdo->prepare("DELETE FROM user_responses WHERE question_id IN ($placeholders)");
                $stmt->execute($question_ids);
                
                $pdo->commit();
                $_SESSION['message'] = "Selected questions deleted successfully.";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error deleting questions: " . $e->getMessage();
            }
        }
        
        header("Location: manage_questions.php");
        exit;
    }
} elseif (isset($_GET['delete'])) {
    // Single question deletion
    $question_id = intval($_GET['delete']);
    
    try {
        $pdo->beginTransaction();
        
        // Delete from questions table
        $stmt = $pdo->prepare("DELETE FROM questions WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        // Delete related user responses
        $stmt = $pdo->prepare("DELETE FROM user_responses WHERE question_id = ?");
        $stmt->execute([$question_id]);
        
        $pdo->commit();
        $_SESSION['message'] = "Question deleted successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting question: " . $e->getMessage();
    }
    
    header("Location: manage_questions.php");
    exit;
}

// Pagination setup
$per_page = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Get total count of questions
$total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$total_pages = ceil($total_questions / $per_page);

// Get questions with quiz information (paginated)
$questions = $pdo->prepare("
    SELECT q.*, qz.title as quiz_title 
    FROM questions q
    JOIN quizzes qz ON q.quiz_id = qz.quiz_id
    ORDER BY q.question_id DESC
    LIMIT ? OFFSET ?
");
$questions->bindValue(1, $per_page, PDO::PARAM_INT);
$questions->bindValue(2, $offset, PDO::PARAM_INT);
$questions->execute();
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions | Quiz Application</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        /* Maintain consistent styling with dashboard */
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
            padding: 0;
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
        
        /* Question management specific styles */
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
        
        .questions-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .questions-table th, .questions-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .questions-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .questions-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: #3498db;
            color: white;
        }
        
        .btn-edit {
            background-color: #f39c12;
            color: white;
        }
        
        .btn-delete {
            background-color: #e74c3c;
            color: white;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        
        .error {
            background-color: #f2dede;
            color: #a94442;
        }
        
        .question-text {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #3498db;
        }
        
        .pagination a:hover {
            background-color: #f8f9fa;
        }
        
        .pagination .current {
            background-color: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .search-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-filter input, .search-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-filter button {
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
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
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="add_question.php">Add Questions</a></li>
                    <li><a href="manage_questions.php" class="active">Manage Questions</a></li>
                    <li><a href="manage_quizzes.php">Manage Quizzes</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="view_results.php">View Results</a></li>
                </ul>
            </div>
            
            <!-- Main Content Area -->
            <div class="main-content">
                <div class="admin-header">
                    <h2 class="admin-title">Manage Questions</h2>
                    <a href="add_question.php" class="btn btn-primary">Add New Question</a>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <!-- Search and Filter Form -->
                <form method="get" class="search-filter">
                    <input type="text" name="search" placeholder="Search questions..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <select name="quiz_filter">
                        <option value="">All Quizzes</option>
                        <?php
                        $quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title")->fetchAll();
                        foreach ($quizzes as $quiz) {
                            $selected = ($_GET['quiz_filter'] ?? '') == $quiz['quiz_id'] ? 'selected' : '';
                            echo "<option value='{$quiz['quiz_id']}' $selected>{$quiz['title']}</option>";
                        }
                        ?>
                    </select>
                    <button type="submit">Filter</button>
                </form>
                
                <!-- Bulk Actions -->
                <form method="post" class="bulk-actions">
                    <select name="bulk_action" style="padding: 8px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Apply</button>
                </form>
                
                <div class="recent-activity">
                    <?php if (!empty($questions)): ?>
                        <form method="post" id="bulk-form">
                            <table class="questions-table">
                                <thead>
                                    <tr>
                                        <th width="30"><input type="checkbox" id="select-all"></th>
                                        <th>ID</th>
                                        <th>Quiz</th>
                                        <th>Question</th>
                                        <th>Options</th>
                                        <th>Correct Answer</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($questions as $question): ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_questions[]" value="<?php echo $question['question_id']; ?>"></td>
                                            <td><?php echo htmlspecialchars($question['question_id']); ?></td>
                                            <td><?php echo htmlspecialchars($question['quiz_title']); ?></td>
                                            <td class="question-text" title="<?php echo htmlspecialchars($question['question_text']); ?>">
                                                <?php echo htmlspecialchars($question['question_text']); ?>
                                            </td>
                                            <td>
                                                A: <?php echo htmlspecialchars($question['option_a']); ?><br>
                                                B: <?php echo htmlspecialchars($question['option_b']); ?><br>
                                                C: <?php echo htmlspecialchars($question['option_c']); ?><br>
                                                D: <?php echo htmlspecialchars($question['option_d']); ?>
                                            </td>
                                            <td><?php echo strtoupper(htmlspecialchars($question['correct_answer'])); ?></td>
                                            <td class="action-buttons">
                                                <a href="edit_question.php?id=<?php echo $question['question_id']; ?>" class="btn btn-edit">Edit</a>
                                                <a href="manage_questions.php?delete=<?php echo $question['question_id']; ?>" 
                                                   class="btn btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this question?');">Delete</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </form>
                        
                        <!-- Pagination -->
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>">&laquo; Previous</a>
                            <?php endif; ?>
                            
                            <?php 
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            if ($start > 1) {
                                echo '<a href="?page=1">1</a>';
                                if ($start > 2) echo '<span>...</span>';
                            }
                            
                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $page) {
                                    echo '<span class="current">'.$i.'</span>';
                                } else {
                                    echo '<a href="?page='.$i.'">'.$i.'</a>';
                                }
                            }
                            
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) echo '<span>...</span>';
                                echo '<a href="?page='.$total_pages.'">'.$total_pages.'</a>';
                            }
                            ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>">Next &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p>No questions found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Quiz Application. All rights reserved.</p>
        </footer>
    </div>

    <script>
        // Bulk actions functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_questions[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        document.querySelector('.bulk-actions button').addEventListener('click', function(e) {
            const bulkAction = document.querySelector('select[name="bulk_action"]').value;
            if (!bulkAction) {
                e.preventDefault();
                alert('Please select a bulk action');
                return false;
            }
            
            const checkedBoxes = document.querySelectorAll('input[name="selected_questions[]"]:checked');
            if (checkedBoxes.length === 0) {
                e.preventDefault();
                alert('Please select at least one question');
                return false;
            }
            
            if (bulkAction === 'delete' && !confirm('Are you sure you want to delete the selected questions?')) {
                e.preventDefault();
                return false;
            }
            
            document.getElementById('bulk-form').submit();
        });
    </script>
</body>
</html>
