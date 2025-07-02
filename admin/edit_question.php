<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Get question ID from URL
$question_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch question data
$stmt = $pdo->prepare("
    SELECT q.*, qz.title as quiz_title 
    FROM questions q
    JOIN quizzes qz ON q.quiz_id = qz.quiz_id
    WHERE q.question_id = ?
");
$stmt->execute([$question_id]);
$question = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$question) {
    die("Question not found.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $quiz_id = intval($_POST['quiz_id']);
    $question_text = trim($_POST['question_text']);
    $option_a = trim($_POST['option_a']);
    $option_b = trim($_POST['option_b']);
    $option_c = trim($_POST['option_c']);
    $option_d = trim($_POST['option_d']);
    $correct_answer = strtolower(trim($_POST['correct_answer']));
    
    // Validate correct answer is a-d
    if (!in_array($correct_answer, ['a', 'b', 'c', 'd'])) {
        $_SESSION['error'] = "Correct answer must be A, B, C, or D";
        header("Location: edit_question.php?id=$question_id");
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE questions 
            SET quiz_id = ?, 
                question_text = ?, 
                option_a = ?, 
                option_b = ?, 
                option_c = ?, 
                option_d = ?, 
                correct_answer = ?
            WHERE question_id = ?
        ");
        $stmt->execute([
            $quiz_id,
            $question_text,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $correct_answer,
            $question_id
        ]);
        
        $_SESSION['message'] = "Question updated successfully.";
        header("Location: manage_questions.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating question: " . $e->getMessage();
        header("Location: edit_question.php?id=$question_id");
        exit;
    }
}

// Fetch all quizzes for dropdown
$quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes ORDER BY title")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Question | Quiz Application</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .edit-form {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .form-group input[type="text"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            min-height: 100px;
        }
        
        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .option-letter {
            font-weight: bold;
            width: 20px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
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
                    <li><a href="manage_questions.php">Manage Questions</a></li>
                    <li><a href="manage_quizzes.php">Manage Quizzes</a></li>
                    <li><a href="manage_users.php">Manage Users</a></li>
                    <li><a href="view_results.php">View Results</a></li>
                </ul>
            </div>
            
            <!-- Main Content Area -->
            <div class="main-content">
                <div class="admin-header">
                    <h2 class="admin-title">Edit Question</h2>
                    <a href="manage_questions.php" class="btn btn-primary">Back to Questions</a>
                </div>
                
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <div class="edit-form">
                    <form method="post">
                        <div class="form-group">
                            <label for="quiz_id">Quiz</label>
                            <select name="quiz_id" id="quiz_id" required>
                                <?php foreach ($quizzes as $quiz): ?>
                                    <option value="<?php echo $quiz['quiz_id']; ?>"
                                        <?php echo $quiz['quiz_id'] == $question['quiz_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($quiz['title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_text">Question Text</label>
                            <textarea name="question_text" id="question_text" required><?php echo htmlspecialchars($question['question_text']); ?></textarea>
                        </div>
                        
                        <div class="options-grid">
                            <div class="option-item">
                                <span class="option-letter">A:</span>
                                <input type="text" name="option_a" value="<?php echo htmlspecialchars($question['option_a']); ?>" required>
                            </div>
                            
                            <div class="option-item">
                                <span class="option-letter">B:</span>
                                <input type="text" name="option_b" value="<?php echo htmlspecialchars($question['option_b']); ?>" required>
                            </div>
                            
                            <div class="option-item">
                                <span class="option-letter">C:</span>
                                <input type="text" name="option_c" value="<?php echo htmlspecialchars($question['option_c']); ?>" required>
                            </div>
                            
                            <div class="option-item">
                                <span class="option-letter">D:</span>
                                <input type="text" name="option_d" value="<?php echo htmlspecialchars($question['option_d']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="correct_answer">Correct Answer</label>
                            <select name="correct_answer" id="correct_answer" required>
                                <option value="a" <?php echo $question['correct_answer'] == 'a' ? 'selected' : ''; ?>>A</option>
                                <option value="b" <?php echo $question['correct_answer'] == 'b' ? 'selected' : ''; ?>>B</option>
                                <option value="c" <?php echo $question['correct_answer'] == 'c' ? 'selected' : ''; ?>>C</option>
                                <option value="d" <?php echo $question['correct_answer'] == 'd' ? 'selected' : ''; ?>>D</option>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <a href="manage_questions.php" class="btn btn-delete">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Question</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <footer class="footer">
            <p>&copy; <?php echo date('Y'); ?> Quiz Application. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
