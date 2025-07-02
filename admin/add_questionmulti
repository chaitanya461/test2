<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if required files exist
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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin status
if (!isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$quizzes = [];
$formData = [
    'quiz_option' => 'existing',
    'quiz_id' => '',
    'new_quiz_name' => '',
    'question_text' => '',
    'option_a' => '',
    'option_b' => '',
    'option_c' => '',
    'option_d' => '',
    'points' => 1,
    'question_type' => 'single',
    'correct_answer' => '',
    'correct_answer_a' => false,
    'correct_answer_b' => false,
    'correct_answer_c' => false,
    'correct_answer_d' => false
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Store form data for repopulation
        $formData = array_merge($formData, $_POST);
        
        // Handle quiz selection/creation
        if (!isset($_POST['quiz_option'])) {
            throw new Exception('Please select a quiz option');
        }

        if ($_POST['quiz_option'] === 'new') {
            // Create new quiz
            if (empty($_POST['new_quiz_name'])) {
                throw new Exception('Quiz name cannot be empty');
            }
            
            $new_quiz_name = trim(htmlspecialchars($_POST['new_quiz_name']));
            $stmt = $pdo->prepare("INSERT INTO quizzes (title, is_active, created_by) VALUES (?, TRUE, ?)");
            $stmt->execute([$new_quiz_name, $_SESSION['user_id']]);
            $quiz_id = $pdo->lastInsertId();
        } else {
            // Use existing quiz
            if (empty($_POST['quiz_id'])) {
                throw new Exception('Please select a quiz');
            }
            $quiz_id = (int)$_POST['quiz_id'];
        }

        // Validate question data
        $required_fields = [
            'question_text' => 'Question text is required',
            'option_a' => 'Option A is required',
            'option_b' => 'Option B is required',
            'option_c' => 'Option C is required',
            'option_d' => 'Option D is required'
        ];
        
        foreach ($required_fields as $field => $message) {
            if (empty(trim($_POST[$field] ?? ''))) {
                throw new Exception($message);
            }
        }

        $question_text = trim(htmlspecialchars($_POST['question_text']));
        $option_a = trim(htmlspecialchars($_POST['option_a']));
        $option_b = trim(htmlspecialchars($_POST['option_b']));
        $option_c = trim(htmlspecialchars($_POST['option_c']));
        $option_d = trim(htmlspecialchars($_POST['option_d']));
        $points = max(1, (int)($_POST['points'] ?? 1));
        $question_type = ($_POST['question_type'] ?? 'single') === 'multi' ? 'multi' : 'single';
        
        // Handle correct answers
        if ($question_type === 'single') {
            if (!isset($_POST['correct_answer']) || !in_array($_POST['correct_answer'], ['a', 'b', 'c', 'd'])) {
                throw new Exception('Please select one correct answer');
            }
            $correct_answer = $_POST['correct_answer'];
        } else {
            $correct_answers = [];
            foreach (['a', 'b', 'c', 'd'] as $option) {
                if (isset($_POST['correct_answer_'.$option]) && $_POST['correct_answer_'.$option] === 'on') {
                    $correct_answers[] = $option;
                    $formData['correct_answer_'.$option] = true;
                }
            }
            if (empty($correct_answers)) {
                throw new Exception('Please select at least one correct answer');
            }
            sort($correct_answers);
            $correct_answer = implode(',', $correct_answers);
        }

        // Insert question
        $stmt = $pdo->prepare(
            "INSERT INTO questions 
            (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_answer, points, question_type) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $quiz_id, 
            $question_text, 
            $option_a, 
            $option_b, 
            $option_c, 
            $option_d, 
            $correct_answer, 
            $points,
            $question_type
        ]);
        
        $success = "Question added successfully!";
        // Reset form data on success
        $formData = [
            'quiz_option' => 'existing',
            'quiz_id' => '',
            'new_quiz_name' => '',
            'question_text' => '',
            'option_a' => '',
            'option_b' => '',
            'option_c' => '',
            'option_d' => '',
            'points' => 1,
            'question_type' => 'single',
            'correct_answer' => '',
            'correct_answer_a' => false,
            'correct_answer_b' => false,
            'correct_answer_c' => false,
            'correct_answer_d' => false
        ];
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $error = "A database error occurred. Please try again.";
        if (strpos($e->getMessage(), 'correct_answer_check') !== false) {
            $error .= " Invalid correct answer format for the selected question type.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch available quizzes
try {
    $quizzes = $pdo->query("SELECT quiz_id, title FROM quizzes WHERE is_active = TRUE ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Failed to load quizzes: " . $e->getMessage();
    $quizzes = [];
}
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
        /* Your existing CSS styles here */
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
        .question-type-container {
            margin-bottom: 15px;
        }
        .correct-answer-checkbox {
            margin-top: 5px;
        }
        .single-answer-section, .multi-answer-section {
            display: none;
        }
        .single-answer-section.active, .multi-answer-section.active {
            display: block;
        }
        @media (max-width: 768px) {
            .options-grid {
                grid-template-columns: 1fr;
            }
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin-bottom: 0;
            font-size: 14px;
            font-weight: 400;
            line-height: 1.42857143;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            background-image: none;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .btn-primary {
            color: #fff;
            background-color: #337ab7;
            border-color: #2e6da4;
        }
        .btn-secondary {
            color: #333;
            background-color: #fff;
            border-color: #ccc;
        }
        .form-control {
            display: block;
            width: 100%;
            height: 34px;
            padding: 6px 12px;
            font-size: 14px;
            line-height: 1.42857143;
            color: #555;
            background-color: #fff;
            background-image: none;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-shadow: inset 0 1px 1px rgba(0,0,0,.075);
        }
        textarea.form-control {
            height: auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-check {
            position: relative;
            display: block;
            margin-bottom: 10px;
        }
        .form-check-input {
            margin-right: 10px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .page-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
                        <input class="form-check-input" type="radio" name="quiz_option" id="existing_quiz" value="existing" 
                            <?= $formData['quiz_option'] === 'existing' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="existing_quiz">Select existing quiz</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="quiz_option" id="new_quiz" value="new"
                            <?= $formData['quiz_option'] === 'new' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="new_quiz">Create new quiz</label>
                    </div>
                </div>

                <!-- Existing Quiz Selection -->
                <div id="existing_quiz_container" class="form-group" 
                    style="<?= $formData['quiz_option'] === 'new' ? 'display:none;' : '' ?>">
                    <label for="quiz_id">Select Quiz:</label>
                    <select id="quiz_id" name="quiz_id" class="form-control select2">
                        <option value="">Search for a quiz...</option>
                        <?php foreach ($quizzes as $quiz): ?>
                            <option value="<?= $quiz['quiz_id'] ?>"
                                <?= $formData['quiz_id'] == $quiz['quiz_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($quiz['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- New Quiz Input -->
                <div id="new_quiz_container" class="form-group" 
                    style="<?= $formData['quiz_option'] === 'new' ? '' : 'display:none;' ?>">
                    <label for="new_quiz_name">New Quiz Name:</label>
                    <input type="text" id="new_quiz_name" name="new_quiz_name" class="form-control" 
                        value="<?= htmlspecialchars($formData['new_quiz_name']) ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="question_text">Question Text:</label>
                <textarea id="question_text" name="question_text" class="form-control" rows="4" required><?= 
                    htmlspecialchars($formData['question_text']) 
                ?></textarea>
                <div class="invalid-feedback">Please enter the question text</div>
            </div>
            
            <div class="question-type-container">
                <label>Question Type:</label>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="question_type" id="single_answer" value="single"
                        <?= $formData['question_type'] === 'single' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="single_answer">Single Answer</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="question_type" id="multi_answer" value="multi"
                        <?= $formData['question_type'] === 'multi' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="multi_answer">Multiple Answers</label>
                </div>
            </div>
            
            <div class="options-grid">
                <div class="form-group">
                    <label for="option_a">Option A:</label>
                    <input type="text" id="option_a" name="option_a" class="form-control" required
                        value="<?= htmlspecialchars($formData['option_a']) ?>">
                    <div class="invalid-feedback">Please enter option A</div>
                    
                    <div class="single-answer-section <?= $formData['question_type'] === 'single' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="radio" name="correct_answer" id="correct_answer_a" value="a"
                                <?= $formData['correct_answer'] === 'a' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="correct_answer_a">Correct Answer</label>
                        </div>
                    </div>
                    <div class="multi-answer-section <?= $formData['question_type'] === 'multi' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="checkbox" name="correct_answer_a" id="multi_correct_a"
                                <?= $formData['correct_answer_a'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="multi_correct_a">Correct Answer</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="option_b">Option B:</label>
                    <input type="text" id="option_b" name="option_b" class="form-control" required
                        value="<?= htmlspecialchars($formData['option_b']) ?>">
                    <div class="invalid-feedback">Please enter option B</div>
                    
                    <div class="single-answer-section <?= $formData['question_type'] === 'single' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="radio" name="correct_answer" id="correct_answer_b" value="b"
                                <?= $formData['correct_answer'] === 'b' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="correct_answer_b">Correct Answer</label>
                        </div>
                    </div>
                    <div class="multi-answer-section <?= $formData['question_type'] === 'multi' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="checkbox" name="correct_answer_b" id="multi_correct_b"
                                <?= $formData['correct_answer_b'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="multi_correct_b">Correct Answer</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="option_c">Option C:</label>
                    <input type="text" id="option_c" name="option_c" class="form-control" required
                        value="<?= htmlspecialchars($formData['option_c']) ?>">
                    <div class="invalid-feedback">Please enter option C</div>
                    
                    <div class="single-answer-section <?= $formData['question_type'] === 'single' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="radio" name="correct_answer" id="correct_answer_c" value="c"
                                <?= $formData['correct_answer'] === 'c' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="correct_answer_c">Correct Answer</label>
                        </div>
                    </div>
                    <div class="multi-answer-section <?= $formData['question_type'] === 'multi' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="checkbox" name="correct_answer_c" id="multi_correct_c"
                                <?= $formData['correct_answer_c'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="multi_correct_c">Correct Answer</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="option_d">Option D:</label>
                    <input type="text" id="option_d" name="option_d" class="form-control" required
                        value="<?= htmlspecialchars($formData['option_d']) ?>">
                    <div class="invalid-feedback">Please enter option D</div>
                    
                    <div class="single-answer-section <?= $formData['question_type'] === 'single' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="radio" name="correct_answer" id="correct_answer_d" value="d"
                                <?= $formData['correct_answer'] === 'd' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="correct_answer_d">Correct Answer</label>
                        </div>
                    </div>
                    <div class="multi-answer-section <?= $formData['question_type'] === 'multi' ? 'active' : '' ?>">
                        <div class="form-check correct-answer-checkbox">
                            <input class="form-check-input" type="checkbox" name="correct_answer_d" id="multi_correct_d"
                                <?= $formData['correct_answer_d'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="multi_correct_d">Correct Answer</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="points">Points:</label>
                <input type="number" id="points" name="points" class="form-control" 
                    value="<?= htmlspecialchars($formData['points']) ?>" min="1" required>
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

            // Toggle between single and multi answer questions
            $('input[name="question_type"]').change(function() {
                if ($(this).val() === 'multi') {
                    $('.single-answer-section').removeClass('active');
                    $('.multi-answer-section').addClass('active');
                    $('input[name="correct_answer"]').prop('checked', false);
                } else {
                    $('.single-answer-section').addClass('active');
                    $('.multi-answer-section').removeClass('active');
                    $('input[name^="correct_answer_"]').prop('checked', false);
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
                
                // Validate at least one correct answer is selected
                const questionType = $('input[name="question_type"]:checked').val();
                if (questionType === 'single') {
                    if (!$('input[name="correct_answer"]:checked').length) {
                        $('.single-answer-section').addClass('is-invalid');
                        isValid = false;
                    } else {
                        $('.single-answer-section').removeClass('is-invalid');
                    }
                } else {
                    if (!$('input[type="checkbox"][name^="correct_answer_"]:checked').length) {
                        $('.multi-answer-section').addClass('is-invalid');
                        isValid = false;
                    } else {
                        $('.multi-answer-section').removeClass('is-invalid');
                    }
                }
                
                if (!isValid) {
                    e.preventDefault();
                    $('.invalid-feedback').hide();
                    $(this).find('.is-invalid').next('.invalid-feedback').show();
                    $('html, body').animate({
                        scrollTop: $(this).find('.is-invalid').first().offset().top - 100
                    }, 500);
                }
            });

            // Initialize form state based on current values
            if ($('input[name="question_type"]:checked').val() === 'multi') {
                $('.single-answer-section').removeClass('active');
                $('.multi-answer-section').addClass('active');
            }
        });
    </script>
</body>
</html>
