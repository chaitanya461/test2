-- Create users table
CREATE TABLE users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create quizzes table
CREATE TABLE quizzes (
    quiz_id SERIAL PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    created_by INT REFERENCES users(user_id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

-- Create questions table
CREATE TABLE questions (
    question_id SERIAL PRIMARY KEY,
    quiz_id INT REFERENCES quizzes(quiz_id),
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer CHAR(1) NOT NULL CHECK (correct_answer IN ('a', 'b', 'c', 'd')),
    points INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create user responses table
CREATE TABLE user_responses (
    response_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(user_id),
    question_id INT REFERENCES questions(question_id),
    selected_answer CHAR(1) CHECK (selected_answer IN ('a', 'b', 'c', 'd')),
    is_correct BOOLEAN,
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create quiz results table
CREATE TABLE quiz_results (
    result_id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(user_id),
    quiz_id INT REFERENCES quizzes(quiz_id),
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    score DECIMAL(5,2) NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE quiz_reattempt_requests (
    request_id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    quiz_id INTEGER NOT NULL,
    request_date TIMESTAMP NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' 
        CHECK (status IN ('pending', 'approved', 'rejected', 'completed')),
    admin_id INTEGER NULL,
    response_date TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id),
    FOREIGN KEY (admin_id) REFERENCES users(user_id)
);

-- Create an admin user (password: admin123 - change this in production)
INSERT INTO users (username, email, password_hash, is_admin)
VALUES ('admin', 'admin@example.com', '$2y$12$no5q4DPdA26jXMsj26cXs.MC9OrD.LDMsHUoQvHkNvIah9B2NE0HG', TRUE);


--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
ALTER TABLE user_responses 
ALTER COLUMN selected_answer TYPE VARCHAR(10),
DROP CONSTRAINT IF EXISTS user_responses_selected_answer_check;

ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_correct_answer_check;

-- Modify the correct_answer column to support multiple answers
ALTER TABLE questions 
ALTER COLUMN correct_answer TYPE VARCHAR(10);

ALTER TABLE questions 
ADD COLUMN IF NOT EXISTS question_type VARCHAR(10) NOT NULL DEFAULT 'single' CHECK (question_type IN ('single', 'multi'));
--------
ALTER TABLE user_responses 
ALTER COLUMN selected_answer TYPE VARCHAR(10),
DROP CONSTRAINT IF EXISTS user_responses_selected_answer_check;

ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_correct_answer_check;
---------------


-------------------------------------------------------------------------------------------------------------------------------------------
ALTER TABLE questions
ADD CONSTRAINT questions_correct_answer_check 
CHECK (
    (question_type = 'single' AND correct_answer IN ('a', 'b', 'c', 'd')) OR
    (question_type = 'multi' AND correct_answer ~ '^[a-d](,[a-d])*$')
);


DROP TABLE questions CASCADE;



--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------


CREATE TABLE IF NOT EXISTS questions (
    question_id SERIAL PRIMARY KEY,
    quiz_id INT NOT NULL REFERENCES quizzes(quiz_id),
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_answer VARCHAR(10) NOT NULL,
    points INT NOT NULL DEFAULT 1 CHECK (points > 0),
    question_type VARCHAR(5) NOT NULL CHECK (question_type IN ('single', 'multi')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT correct_answer_check CHECK (
        (question_type = 'single' AND correct_answer IN ('a', 'b', 'c', 'd')) OR
        (question_type = 'multi' AND correct_answer ~ '^[a-d](,[a-d])*$')
    )
);

ALTER TABLE user_responses 
ALTER COLUMN selected_answer TYPE VARCHAR(10);

-- First, drop existing constraints if they exist
ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_correct_answer_check;

-- Modify the correct_answer column to support multiple answers
ALTER TABLE questions 
ALTER COLUMN correct_answer TYPE VARCHAR(10);

-- Add question_type column if it doesn't exist
ALTER TABLE questions 
ADD COLUMN IF NOT EXISTS question_type VARCHAR(5) NOT NULL DEFAULT 'single' CHECK (question_type IN ('single', 'multi'));

-- Add the new check constraint
ALTER TABLE questions
ADD CONSTRAINT questions_correct_answer_check 
CHECK (
    (question_type = 'single' AND correct_answer IN ('a', 'b', 'c', 'd')) OR
    (question_type = 'multi' AND correct_answer ~ '^[a-d](,[a-d])*$')
);



CREATE TABLE reattempt_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    request_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_id INT NULL,
    action_date DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (quiz_id) REFERENCES quizzes(quiz_id),
    FOREIGN KEY (admin_id) REFERENCES users(user_id)
);
-- Quiz attempts tracking
CREATE TABLE quiz_attempts (
    attempt_id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(user_id),
    quiz_id INTEGER NOT NULL REFERENCES quizzes(quiz_id),
    attempt_count INTEGER DEFAULT 1,
    reattempt_allowed BOOLEAN DEFAULT FALSE,
    last_attempt_date TIMESTAMP WITH TIME ZONE,
    CONSTRAINT unique_user_quiz UNIQUE (user_id, quiz_id)
);

-- Reattempt requests table
CREATE TABLE reattempt_requests (
    request_id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    quiz_id INTEGER NOT NULL REFERENCES quizzes(quiz_id) ON DELETE CASCADE,
    request_date TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    admin_id INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    response_date TIMESTAMP WITH TIME ZONE
);

-- Create partial unique index separately
CREATE UNIQUE INDEX unique_pending_request ON reattempt_requests(user_id, quiz_id) 
WHERE status = 'pending';

SELECT r.*, u.username, q.title as quiz_title
FROM reattempt_requests r
JOIN users u ON r.user_id = u.user_id
JOIN quizzes q ON r.quiz_id = q.quiz_id
WHERE r.status = 'pending'
ORDER BY r.request_date ASC;


