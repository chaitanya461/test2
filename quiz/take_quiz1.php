<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Fetch quiz details
$stmt = $pdo->prepare("SELECT * FROM quizzes WHERE quiz_id = ? AND is_active = TRUE");
$stmt->execute([$quiz_id]);
$quiz = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quiz) {
    die("Invalid quiz or quiz not found.");
}

// Check if user has already attempted this quiz
$stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$previous_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has pending request for reattempt
$stmt = $pdo->prepare("SELECT * FROM reattempt_requests WHERE user_id = ? AND quiz_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle reattempt request
if (isset($_POST['request_reattempt'])) {
    if (!$previous_attempt) {
        die("No previous attempt found to request reattempt.");
    }
    
    if ($pending_request) {
        die("You already have a pending request for this quiz.");
    }
    
    $stmt = $pdo->prepare("INSERT INTO reattempt_requests (user_id, quiz_id, request_date) VALUES (?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'], $quiz_id]);
    
    $_SESSION['message'] = "Your request for reattempt has been submitted to admin.";
    header("Location: quiz.php?quiz_id=$quiz_id");
    exit;
}

// Fetch questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY question_id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions available for this quiz.");
}

// Handle quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    // Check if allowed to attempt
    if ($previous_attempt && !isset($_SESSION['allowed_reattempt'][$quiz_id])) {
        die("You have already attempted this quiz. Please request admin for reattempt.");
    }
    
    // If this is a reattempt, delete previous results
    if ($previous_attempt && isset($_SESSION['allowed_reattempt'][$quiz_id])) {
        // Delete previous responses
        $stmt = $pdo->prepare("DELETE FROM user_responses WHERE user_id = ? AND question_id IN 
            (SELECT question_id FROM questions WHERE quiz_id = ?)");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        
        // Delete previous result
        $stmt = $pdo->prepare("DELETE FROM quiz_results WHERE user_id = ? AND quiz_id = ?");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        
        // Delete the reattempt request
        $stmt = $pdo->prepare("DELETE FROM reattempt_requests WHERE user_id = ? AND quiz_id = ?");
        $stmt->execute([$_SESSION['user_id'], $quiz_id]);
        
        // Remove permission
        unset($_SESSION['allowed_reattempt'][$quiz_id]);
    }

    $total_questions = count($questions);
    $correct_answers = 0;

    foreach ($questions as $question) {
        $question_id = $question['question_id'];
        $selected_answer = isset($_POST['question_' . $question_id]) ? trim($_POST['question_' . $question_id]) : null;

        $is_correct = ($selected_answer === trim($question['correct_answer'])) ? 1 : 0;

        if ($is_correct) {
            $correct_answers++;
        }

        // Save the user response
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
    }

    // Calculate score
    $score = ($correct_answers / $total_questions) * 100;

    // Save quiz result
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

    // Redirect
    header("Location: result.php?quiz_id=$quiz_id");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($quiz['title']); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($quiz['title']); ?></h1>
        <p><?php echo htmlspecialchars($quiz['description']); ?></p>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info"><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
        <?php endif; ?>

        <?php if ($previous_attempt && !isset($_SESSION['allowed_reattempt'][$quiz_id])): ?>
            <div class="attempt-info">
                <h3>You have already attempted this quiz.</h3>
                <p>Your previous score: <?php echo $previous_attempt['score']; ?>%</p>
                
                <?php if (!$pending_request): ?>
                    <form method="post">
                        <button type="submit" name="request_reattempt" class="btn btn-warning">
                            Request Admin for Reattempt
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-warning">
                        Your reattempt request is pending admin approval.
                    </div>
                <?php endif; ?>
                
                <a href="result.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-info">
                    View Previous Result
                </a>
            </div>
        <?php else: ?>
            <form method="post">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-card">
                        <h3>Question <?php echo $index + 1; ?></h3>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>

                        <div class="options">
                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="a" required>
                                <?php echo htmlspecialchars($question['option_a']); ?>
                            </label><br>

                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="b">
                                <?php echo htmlspecialchars($question['option_b']); ?>
                            </label><br>

                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="c">
                                <?php echo htmlspecialchars($question['option_c']); ?>
                            </label><br>

                            <label>
                                <input type="radio" name="question_<?php echo $question['question_id']; ?>" value="d">
                                <?php echo htmlspecialchars($question['option_d']); ?>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" name="submit_quiz" class="btn btn-primary">
                    <?php echo $previous_attempt ? 'Submit Reattempt' : 'Submit Quiz'; ?>
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
