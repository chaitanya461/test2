<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

// Check admin status
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$quizzes = [];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle quiz selection/creation
        if (isset($_POST['quiz_option']) && $_POST['quiz_option'] === 'new' && !empty($_POST['new_quiz_name'])) {
            // Create new quiz
            $new_quiz_name = trim(htmlspecialchars($_POST['new_quiz_name']));
            if (empty($new_quiz_name)) {
                throw new Exception('Quiz name cannot be empty');
            }
            
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, is_active, created_by) VALUES (?, TRUE, ?)");
            $stmt->execute([$new_quiz_name, $_SESSION['user_id']]);
            $quiz_id = $pdo->lastInsertId();
        } elseif (isset($_POST['quiz_id']) && $_POST['quiz_id'] > 0) {
            // Use existing quiz
            $quiz_id = (int)$_POST['quiz_id'];
        } else {
            throw new Exception('Please select or create a quiz');
        }

        // Validate question data
        $question_text = trim(htmlspecialchars($_POST['question_text'] ?? ''));
        $option_a = trim(htmlspecialchars($_POST['option_a'] ?? ''));
        $option_b = trim(htmlspecialchars($_POST['option_b'] ?? ''));
        $option_c = trim(htmlspecialchars($_POST['option_c'] ?? ''));
        $option_d = trim(htmlspecialchars($_POST['option_d'] ?? ''));
        $correct_answer = isset($_POST['correct_answer']) && in_array($_POST['correct_answer'], ['a', 'b', 'c', 'd']) 
            ? $_POST['correct_answer'] 
            : '';
        $points = isset($_POST['points']) ? max(1, (int)$_POST['points']) : 1;

        // Validate required fields
        if (empty($question_text)) {
            throw new Exception('Question text is required');
        }
        if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
            throw new Exception('All options are required');
        }
        if (empty($correct_answer)) {
            throw new Exception('Please select the correct answer');
        }

        // Insert question
        $stmt = $pdo->prepare(
            "INSERT INTO questions 
            (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$quiz_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $points]);
        
        $success = "Question added successfully!";
        $_POST = []; // Clear form
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "A database error occurred. Please try again.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch available quizzes
$quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes WHERE is_active = TRUE ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Question | Quiz Admin</title>
    <link rel="stylesheet" href="../styles.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container .select2-selection--single {
            height: 38px;
            border-radius: 4px;
            border: 1px solid #ced4da;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        .is-invalid {
            border-color: #dc3545;
        }
        .invalid-feedback {
            color: #dc3545;
            font-size: 0.875em;
        }
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .quiz-option-container {
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }
        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="page-header">
            <h1>Add New Question</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post" class="quiz-form">
            <div class="quiz-option-container">
                <div class="form-group">
                    <label>Quiz Selection:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="quiz_option" id="existing_quiz" value="existing" checked>
                        <label class="form-check-label" for="existing_quiz">Select existing quiz</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="quiz_option" id="new_quiz" value="new">
                        <label class="form-check-label" for="new_quiz">Create new quiz</label>
                    </div>
                </div>

                <!-- Existing Quiz Selection -->
                <div id="existing_quiz_container" class="form-group">
                    <label for="quiz_id">Select Quiz:</label>
                    <select id="quiz_id" name="quiz_id" class="form-control select2">
                        <option value="">Search for a quiz...</option>
                        <?php foreach ($quizzes as $quiz): ?>
                            <option value="<?= $quiz['quiz_id'] ?>"
                                <?= isset($_POST['quiz_id']) && ($_POST['quiz_option'] ?? 'existing') === 'existing' && $_POST['quiz_id'] == $quiz['quiz_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($quiz['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- New Quiz Input -->
                <div id="new_quiz_container" class="form-group" style="display:none;">
                    <label for="new_quiz_name">New Quiz Name:</label>
                    <input type="text" id="new_quiz_name" name="new_quiz_name" class="form-control" 
                        value="<?= htmlspecialchars($_POST['new_quiz_name'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="question_text">Question Text:</label>
                <textarea id="question_text" name="question_text" class="form-control" rows="4" required><?= 
                    htmlspecialchars($_POST['question_text'] ?? '') 
                ?></textarea>
                <div class="invalid-feedback">Please enter the question text</div>
            </div>
            
            <div class="options-grid">
                <div class="form-group">
                    <label for="option_a">Option A:</label>
                    <input type="text" id="option_a" name="option_a" class="form-control" required
                        value="<?= htmlspecialchars($_POST['option_a'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter option A</div>
                </div>
                
                <div class="form-group">
                    <label for="option_b">Option B:</label>
                    <input type="text" id="option_b" name="option_b" class="form-control" required
                        value="<?= htmlspecialchars($_POST['option_b'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter option B</div>
                </div>
                
                <div class="form-group">
                    <label for="option_c">Option C:</label>
                    <input type="text" id="option_c" name="option_c" class="form-control" required
                        value="<?= htmlspecialchars($_POST['option_c'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter option C</div>
                </div>
                
                <div class="form-group">
                    <label for="option_d">Option D:</label>
                    <input type="text" id="option_d" name="option_d" class="form-control" required
                        value="<?= htmlspecialchars($_POST['option_d'] ?? '') ?>">
                    <div class="invalid-feedback">Please enter option D</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="correct_answer">Correct Answer:</label>
                <select id="correct_answer" name="correct_answer" class="form-control" required>
                    <option value="">Select correct answer</option>
                    <option value="a" <?= ($_POST['correct_answer'] ?? '') === 'a' ? 'selected' : '' ?>>Option A</option>
                    <option value="b" <?= ($_POST['correct_answer'] ?? '') === 'b' ? 'selected' : '' ?>>Option B</option>
                    <option value="c" <?= ($_POST['correct_answer'] ?? '') === 'c' ? 'selected' : '' ?>>Option C</option>
                    <option value="d" <?= ($_POST['correct_answer'] ?? '') === 'd' ? 'selected' : '' ?>>Option D</option>
                </select>
                <div class="invalid-feedback">Please select the correct answer</div>
            </div>
            
            <div class="form-group">
                <label for="points">Points:</label>
                <input type="number" id="points" name="points" class="form-control" 
                    value="<?= htmlspecialchars($_POST['points'] ?? 1) ?>" min="1" required>
                <div class="invalid-feedback">Points must be at least 1</div>
            </div>
            
            <button type="submit" class="btn btn-primary">Add Question</button>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2
            $('.select2').select2({
                placeholder: "Search for a quiz...",
                width: '100%'
            });

            // Toggle between existing and new quiz
            $('input[name="quiz_option"]').change(function() {
                if ($(this).val() === 'new') {
                    $('#existing_quiz_container').hide();
                    $('#new_quiz_container').show();
                    $('#quiz_id').removeAttr('required');
                    $('#new_quiz_name').attr('required', 'required');
                } else {
                    $('#existing_quiz_container').show();
                    $('#new_quiz_container').hide();
                    $('#quiz_id').attr('required', 'required');
                    $('#new_quiz_name').removeAttr('required');
                }
            });

            // Form validation
            $('.quiz-form').on('submit', function(e) {
                let isValid = true;
                $(this).find('[required]').each(function() {
                    if (!$(this).val()) {
                        $(this).addClass('is-invalid');
                        isValid = false;
                    } else {
                        $(this).removeClass('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    $('.invalid-feedback').hide();
                    $(this).find('.is-invalid').next('.invalid-feedback').show();
                }
            });
        });
    </script>
</body>
</html>
