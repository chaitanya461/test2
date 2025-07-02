
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

// Check if user has already taken this quiz
$stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE user_id = ? AND quiz_id = ?");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$existing_result = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has a pending reattempt request
$stmt = $pdo->prepare("SELECT * FROM quiz_reattempt_requests WHERE user_id = ? AND quiz_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$pending_request = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if admin has approved a reattempt
$stmt = $pdo->prepare("SELECT * FROM quiz_reattempt_requests WHERE user_id = ? AND quiz_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id'], $quiz_id]);
$approved_request = $stmt->fetch(PDO::FETCH_ASSOC);

// If user has already taken the quiz and no approved reattempt, show appropriate message
if ($existing_result && !$approved_request) {
    if ($pending_request) {
        die("Your request for reattempt is pending admin approval. Please wait.");
    } else {
        // Show option to request reattempt
        if (isset($_POST['request_reattempt'])) {
            // Create reattempt request
            $stmt = $pdo->prepare("
                INSERT INTO quiz_reattempt_requests (user_id, quiz_id, request_date, status)
                VALUES (?, ?, NOW(), 'pending')
            ");
            $stmt->execute([$_SESSION['user_id'], $quiz_id]);
            header("Location: take_quiz.php?quiz_id=$quiz_id");
            exit;
        }
        
        // Display message with reattempt request option
        echo "<!DOCTYPE html>
        <html lang='en'>
        <head>
            <meta charset='UTF-8'>
            <title>Quiz Already Taken</title>
            <link rel='stylesheet' href='styles.css'>
        </head>
        <body>
            <div class='container'>
                <h1>Quiz Already Taken</h1>
                <p>You have already completed this quiz. Your score was: {$existing_result['score']}%</p>
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

// If admin approved reattempt, delete previous results before allowing new attempt
if ($approved_request) {
    // Delete previous quiz results
    $pdo->prepare("DELETE FROM quiz_results WHERE user_id = ? AND quiz_id = ?")
        ->execute([$_SESSION['user_id'], $quiz_id]);
    
    // Delete previous responses
    $pdo->prepare("DELETE FROM user_responses WHERE user_id = ? AND question_id IN (SELECT question_id FROM questions WHERE quiz_id = ?)")
        ->execute([$_SESSION['user_id'], $quiz_id]);
    
    // Mark request as completed
    $pdo->prepare("UPDATE quiz_reattempt_requests SET status = 'completed' WHERE request_id = ?")
        ->execute([$approved_request['request_id']]);
}

// Fetch questions
$stmt = $pdo->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY question_id");
$stmt->execute([$quiz_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($questions)) {
    die("No questions available for this quiz.");
}

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_quiz'])) {
    $total_questions = count($questions);
    $correct_answers = 0;

    foreach ($questions as $question) {
        $question_id = $question['question_id'];
        $selected_answer = isset($_POST['question_' . $question_id]) ? trim($_POST['question_' . $question_id]) : null;

        // Make sure the answer is not empty
        if ($selected_answer === null) {
            $is_correct = 0;
        } else {
            // Compare explicitly as strings
            $is_correct = ($selected_answer === trim($question['correct_answer'])) ? 1 : 0;
        }

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

            <button type="submit" name="submit_quiz" class="btn btn-primary">Submit Quiz</button>
        </form>
    </div>
</body>
</html>
