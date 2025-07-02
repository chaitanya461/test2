<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

$quiz_id = isset($_GET['quiz_id']) ? intval($_GET['quiz_id']) : 0;

// Fetch quiz result for this user
$result = $pdo->prepare("
    SELECT qr.*, q.title 
    FROM quiz_results qr
    JOIN quizzes q ON qr.quiz_id = q.quiz_id
    WHERE qr.user_id = ? AND qr.quiz_id = ?
    ORDER BY qr.completed_at DESC
    LIMIT 1
");
$result->execute([$_SESSION['user_id'], $quiz_id]);
$result = $result->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    die("No results found for this quiz.");
}

// Fetch user responses with question details
$responses = $pdo->prepare("
    SELECT ur.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer
    FROM user_responses ur
    JOIN questions q ON ur.question_id = q.question_id
    WHERE ur.user_id = ? AND q.quiz_id = ?
    ORDER BY ur.question_id
");
$responses->execute([$_SESSION['user_id'], $quiz_id]);
$responses = $responses->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Results: <?php echo htmlspecialchars($result['title']); ?></title>
    <link rel="stylesheet" href="styles1.css">
</head>
<body>
    <div class="container">
        <h1>Quiz Results: <?php echo htmlspecialchars($result['title']); ?></h1>
        
        <div class="result-summary">
            <h2>Your Score: <?php echo round($result['score'], 2); ?>%</h2>
            <p>You answered <?php echo $result['correct_answers']; ?> out of <?php echo $result['total_questions']; ?> questions correctly.</p>
            <p>Completed on: <?php echo date('F j, Y, g:i a', strtotime($result['completed_at'])); ?></p>
        </div>
        
        <h3>Question Review</h3>
        <?php foreach ($responses as $response): ?>
            <div class="question-review <?php echo $response['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <p><strong>Question:</strong> <?php echo htmlspecialchars($response['question_text']); ?></p>
                <p><strong>Your Answer:</strong> 
                    <?php 
                    $selected_option = 'option_' . $response['selected_answer'];
                    echo htmlspecialchars($response[$selected_option]);
                    if (!$response['is_correct']) {
                        echo " (Incorrect)";
                    } else {
                        echo " (Correct)";
                    }
                    ?>
                </p>
                <?php if (!$response['is_correct']): ?>
                    <p><strong>Correct Answer:</strong> 
                        <?php 
                        $correct_option = 'option_' . $response['correct_answer'];
                        echo htmlspecialchars($response[$correct_option]);
                        ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        
        <p><a href="../index.php">Back to Home</a></p>
    </div>
</body>
</html>
