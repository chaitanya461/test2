<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Required files check
$required_files = [
    '../includes/config.php',
    '../includes/auth_functions.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Required file missing: $file");
    }
}

require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Role verification (with proper session handling)
if (!isset($_SESSION['user_role'])) {
    // Don't automatically set to admin - this is a security issue
    die("Session error: User role not defined.");
}

if ($_SESSION['user_role'] !== 'admin') {
    die("Access denied. Admin privileges required.");
}

// Initialize variables
$error = $success = '';
$quizzes = [];

// Database connection verification
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_time_limit'])) {
    $quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
    $time_limit = isset($_POST['time_limit']) ? (int)$_POST['time_limit'] : 0;

    // Validation
    if ($quiz_id <= 0) {
        $error = "Invalid quiz selected.";
    } elseif ($time_limit < 1 || $time_limit > 240) {
        $error = "Time limit must be between 1 and 240 minutes.";
    } else {
        try {
            // Verify quiz exists first
            $check_stmt = $pdo->prepare("SELECT quiz_id FROM quizzes WHERE quiz_id = ?");
            $check_stmt->execute([$quiz_id]);
            
            if ($check_stmt->rowCount() === 0) {
                $error = "Quiz not found in database.";
            } else {
                // Perform the update
                $update_stmt = $pdo->prepare("UPDATE quizzes SET time_limit = ? WHERE quiz_id = ?");
                $update_stmt->execute([$time_limit, $quiz_id]);
                
                if ($update_stmt->rowCount() > 0) {
                    $success = "Time limit updated successfully!";
                } else {
                    // Check if the value was the same
                    $current_stmt = $pdo->prepare("SELECT time_limit FROM quizzes WHERE quiz_id = ?");
                    $current_stmt->execute([$quiz_id]);
                    $current_limit = $current_stmt->fetchColumn();
                    
                    if ($current_limit == $time_limit) {
                        $error = "No changes made - time limit is already set to $time_limit minutes.";
                    } else {
                        $error = "Update failed - no rows affected.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch active quizzes
try {
    $stmt = $pdo->prepare("SELECT quiz_id, title, time_limit FROM quizzes WHERE is_active = TRUE ORDER BY title");
    $stmt->execute();
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Quiz Time Limit</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        select, input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
        select { height: 40px; }
        input[type="number"] { max-width: 100px; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-decoration: none; display: inline-block; }
        .btn-primary { background-color: #007bff; color: white; }
        .btn-primary:hover { background-color: #0069d9; }
        .btn-secondary { background-color: #6c757d; color: white; }
        .btn-secondary:hover { background-color: #5a6268; }
        .quiz-info { background-color: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .quiz-info p { margin: 5px 0; }
        .current-limit { font-weight: bold; color: #28a745; }
        .debug-info { background-color: #f0f0f0; padding: 10px; margin-top: 20px; border-radius: 4px; font-family: monospace; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Set Quiz Time Limit</h1>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <div class="form-group">
                <label for="quiz_id">Select Quiz:</label>
                <select name="quiz_id" id="quiz_id" required>
                    <option value="">-- Select a Quiz --</option>
                    <?php foreach ($quizzes as $quiz): ?>
                        <option value="<?php echo htmlspecialchars($quiz['quiz_id']); ?>"
                            <?php if (isset($_POST['quiz_id']) && $_POST['quiz_id'] == $quiz['quiz_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($quiz['title']); ?>
                            (Current: <?php echo htmlspecialchars($quiz['time_limit']); ?> mins)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="time_limit">Time Limit (minutes):</label>
                <input type="number" name="time_limit" id="time_limit"
                       min="1" max="240" required
                       value="<?php echo isset($_POST['time_limit']) ? htmlspecialchars($_POST['time_limit']) : '30'; ?>">
                <small>Must be between 1 and 240 minutes</small>
            </div>

            <button type="submit" name="update_time_limit" class="btn btn-primary">Update Time Limit</button>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </form>

        <div class="quiz-info">
            <h3>About Time Limits</h3>
            <p>- Time limit is the maximum duration allowed to complete the quiz.</p>
            <p>- When time expires, the quiz will be automatically submitted.</p>
        </div>

        <!-- Debug information (visible only during development) -->
        <div class="debug-info">
            <h4>Debug Information</h4>
            <p><strong>Session Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Not set'); ?></p>
            <p><strong>Last POST data:</strong> <?php echo htmlspecialchars(print_r($_POST, true)); ?></p>
            <p><strong>Quizzes count:</strong> <?php echo count($quizzes); ?></p>
        </div>
    </div>

    <script>
        // Auto-fill time limit when quiz is selected
        document.getElementById('quiz_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const match = selectedOption.text.match(/Current: (\d+) mins/);
                if (match && match[1]) {
                    document.getElementById('time_limit').value = match[1];
                }
            }
        });

        // Show debug info with Ctrl+Shift+D
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                document.querySelector('.debug-info').style.display = 'block';
            }
        });
    </script>
</body>
</html>
