<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Safe HTML output function
function safe_html($string) {
    if ($string === null || $string === false) {
        return '';
    }
    return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Check required files
$required_files = [
    '../includes/config.php',
    '../includes/auth_functions.php'
];

foreach ($required_files as $file) {
    if (!file_exists($file)) {
        die("Required file missing: $file");
    }
}

// Load dependencies
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Get quiz ID
$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Fetch quiz details
try {
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND is_active = TRUE");
    $stmt->execute([$quiz_id]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        die("Invalid quiz or quiz not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Check previous attempts
try {
    $stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE user_id = ? AND quiz_id = ?");
    $stmt->execute([$_SESSION['user_id'], $quiz_id]);
    $existing_result = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Check reattempt requests
try {
    $stmt = $pdo->prepare("SELECT * FROM quiz_reattempt_requests WHERE user_id = ? AND quiz_id = ? AND status = 'pending'");
    $stmt->execute([$_SESSION['user_id'], $quiz_id]);
    $pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM quiz_reattempt_requests WHERE user_id = ? AND quiz_id = ? AND status = 'approved'");
    $stmt->execute([$_SESSION['user_id'], $quiz_id]);
    $approved_request = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle quiz retake logic
if ($existing_result && !$approved_request) {
    if ($pending_request) {
        die("Your request for reattempt is pending admin approval. Please wait.");
    } else {
        if (isset($_POST['request_reattempt'])) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_reattempt_requests (user_id, quiz_id, request_date, status)
                    VALUES (?, ?, NOW(), 'pending')
                ");
                $stmt->execute([$_SESSION['user_id'], $quiz_id]);
                header("Location: take_quiz.php?quiz_id=$quiz_id");
                exit;
            } catch (PDOException $e) {
                die("Database error: " . $e->getMessage());
            }
        }
        
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Quiz Already Taken</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px; }
                .btn { padding: 10px 15px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                .btn-primary { background-color: #007bff; color: white; }
                .btn-secondary { background-color: #6c757d; color: white; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1>Quiz Already Taken</h1>
                <p>You have already completed this quiz. Your score was: " . safe_html($existing_result['score'] ?? '') . "%</p>
                <p>If you want to retake this quiz, you need to request permission from admin.</p>
                <form method='post'>
                    <button type='submit' name='request_reattempt' class='btn btn-primary'>Request Reattempt</button>
                    <a href='../index.php' class='btn btn-secondary'>Back to Dashboard</a>
                </form>
            </div>
        </body>
        </html>";
        exit;
    }
}

// Handle approved reattempt
if ($approved_request) {
    try {
        $pdo->beginTransaction();
        
        $pdo->prepare("DELETE FROM quiz_results WHERE user_id = ? AND quiz_id = ?")
            ->execute([$_SESSION['user_id'], $quiz_id]);
        
        $pdo->prepare("DELETE FROM user_responses WHERE user_id = ? AND question_id IN (SELECT question_id FROM questions WHERE quiz_id = ?)")
            ->execute([$_SESSION['user_id'], $quiz_id]);
        
        $pdo->prepare("UPDATE quiz_reattempt_requests SET status = 'completed' WHERE request_id = ?")
            ->execute([$approved_request['request_id']]);
            
        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Database error: " . $e->getMessage());
    }
}

// Fetch questions
try {
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY question_id");
    $stmt->execute([$quiz_id]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($questions)) {
        die("No questions available for this quiz.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    try {
        $total_questions = count($questions);
        $correct_answers = 0;

        $pdo->beginTransaction();

        foreach ($questions as $question) {
            $question_id = $question['question_id'];
            $is_correct = 0;
            $selected_answer = null;

            if ($question['question_type'] === 'single') {
                $selected_answer = isset($_POST['question_' . $question_id]) ? trim($_POST['question_' . $question_id]) : null;
                
                if ($selected_answer !== null) {
                    $is_correct = ($selected_answer === trim($question['correct_answer'])) ? 1 : 0;
                }
            } else {
                $selected_answers = isset($_POST['question_' . $question_id]) ? $_POST['question_' . $question_id] : [];
                
                if (!is_array($selected_answers)) {
                    $selected_answers = [];
                }

                sort($selected_answers);
                $selected_answer = implode(',', $selected_answers);
                
                $correct_answers_array = explode(',', $question['correct_answer']);
                sort($correct_answers_array);
                $correct_answer_str = implode(',', $correct_answers_array);
                
                $is_correct = ($selected_answer === $correct_answer_str) ? 1 : 0;
            }

            $stmt = $pdo->prepare("
                INSERT INTO user_responses (user_id, question_id, selected_answer, is_correct)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $question_id,
                $selected_answer,
                $is_correct
            ]);

            if ($is_correct) {
                $correct_answers++;
            }
        }

        $score = ($total_questions > 0) ? ($correct_answers / $total_questions) * 100 : 0;

        $stmt = $pdo->prepare("
            INSERT INTO quiz_results (user_id, quiz_id, total_questions, correct_answers, score)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $quiz_id,
            $total_questions,
            $correct_answers,
            $score
        ]);

        $pdo->commit();

        header("Location: result.php?quiz_id=$quiz_id");
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo safe_html($quiz['title'] ?? ''); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: white;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-info {
            background-color: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
        .question-card {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .options {
            margin-top: 15px;
        }
        .options label {
            display: block;
            margin: 8px 0;
            padding: 10px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .options label:hover {
            background-color: #f0f0f0;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background-color: #28a745;
            color: white;
        }
        .btn-primary:hover {
            background-color: #218838;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .question-type {
            font-style: italic;
            color: #6c757d;
            margin-bottom: 10px;
        }
        input[type="radio"], input[type="checkbox"] {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo safe_html($quiz['title'] ?? ''); ?></h1>
        <p><?php echo safe_html($quiz['description'] ?? ''); ?></p>

        <?php if ($approved_request): ?>
            <div class="alert alert-info">
                Your reattempt request was approved. You can now retake this quiz.
            </div>
            <a href='../index.php' class='btn btn-secondary'>Back to Dashboard</a>
        <?php endif; ?>

        <form method="post">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-card">
                    <h3>Question <?php echo $index + 1; ?></h3>
                    <p><?php echo safe_html($question['question_text'] ?? ''); ?></p>
                    <p class="question-type"><?php echo $question['question_type'] === 'multi' ? 'Select all that apply' : 'Select one answer'; ?></p>

                    <div class="options">
                        <?php if ($question['question_type'] === 'single'): ?>
                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="a" required>
                                <?php echo safe_html($question['option_a'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="b">
                                <?php echo safe_html($question['option_b'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="c">
                                <?php echo safe_html($question['option_c'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="d">
                                <?php echo safe_html($question['option_d'] ?? ''); ?>
                            </label>
                        <?php else: ?>
                            <label>
                                <input type="checkbox" name="question_<?php echo $question['question_id']; ?>[]" value="a">
                                <?php echo safe_html($question['option_a'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="question_<?php echo $question['question_id']; ?>[]" value="b">
                                <?php echo safe_html($question['option_b'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="question_<?php echo $question['question_id']; ?>[]" value="c">
                                <?php echo safe_html($question['option_c'] ?? ''); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="question_<?php echo $question['question_id']; ?>[]" value="d">
                                <?php echo safe_html($question['option_d'] ?? ''); ?>
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <button type="submit" name="submit_quiz" class="btn btn-primary">Submit Quiz</button>
        </form>
    </div>
</body>
</html>
