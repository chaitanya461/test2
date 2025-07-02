<?php
// Optional: Show errors while developing (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/auth_functions.php';

if (!isLoggedIn()) {
    header("Location: ../login.php");
    exit;
}

// Check if user is admin
$stmt = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !$user['is_admin']) {
    die("Access denied. Admin privileges required.");
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $request_id = intval($_POST['request_id']);
        $action = $_POST['action'];
        
        if (in_array($action, ['approve', 'reject'])) {
            $status = $action === 'approve' ? 'approved' : 'rejected';
            
            $stmt = $pdo->prepare("
                UPDATE quiz_reattempt_requests 
                SET status = ?, 
                    admin_id = ?, 
                    response_date = NOW() 
                WHERE request_id = ? AND status = 'pending'
            ");
            $stmt->execute([
                $status,
                $_SESSION['user_id'],
                $request_id
            ]);
            
            $_SESSION['message'] = "Request $action successfully.";
            header("Location: admin_reattempt_requests.php");
            exit;
        }
    }
}

// Fetch all pending requests with user and quiz details
$stmt = $pdo->prepare("
    SELECT r.*, u.username, q.title as quiz_title
    FROM quiz_reattempt_requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN quizzes q ON r.quiz_id = q.quiz_id
    WHERE r.status = 'pending'
    ORDER BY r.request_date ASC
");
$stmt->execute();
$pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch request history
$stmt = $pdo->prepare("
    SELECT r.*, u.username, q.title as quiz_title, a.username as admin_username
    FROM quiz_reattempt_requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN quizzes q ON r.quiz_id = q.quiz_id
    LEFT JOIN users a ON r.admin_id = a.user_id
    WHERE r.status != 'pending'
    ORDER BY r.response_date DESC
    LIMIT 50
");
$stmt->execute();
$request_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quiz Reattempt Requests</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1, h2 {
            color: #333;
        }
        .back-button {
            display: inline-block;
            margin-bottom: 20px;
            padding: 8px 16px;
            background-color: #0275d8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-button:hover {
            background-color: #025aa5;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success {
            background-color: #dff0d8;
            color: #3c763d;
        }
        .btn {
            padding: 6px 12px;
            margin: 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve {
            background-color: #5cb85c;
            color: white;
        }
        .btn-reject {
            background-color: #d9534f;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .tab {
            overflow: hidden;
            border: 1px solid #ccc;
            background-color: #f1f1f1;
            border-radius: 4px 4px 0 0;
        }
        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 10px 16px;
            transition: 0.3s;
        }
        .tab button:hover {
            background-color: #ddd;
        }
        .tab button.active {
            background-color: #fff;
            font-weight: bold;
        }
        .tabcontent {
            display: none;
            padding: 20px;
            border: 1px solid #ccc;
            border-top: none;
            border-radius: 0 0 4px 4px;
        }
        .tabcontent.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Quiz Reattempt Requests</h1>

        <a href="dashboard.php" class="back-button">← Back to Dashboard</a>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="message success"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></div>
        <?php endif; ?>

        <div class="tab">
            <button class="tablinks active" onclick="openTab(event, 'pending')">Pending Requests</button>
            <button class="tablinks" onclick="openTab(event, 'history')">Request History</button>
        </div>

        <div id="pending" class="tabcontent active">
            <h2>Pending Requests</h2>
            <?php if (empty($pending_requests)): ?>
                <p>No pending requests.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Quiz</th>
                            <th>Request Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><?php echo htmlspecialchars($request['username']); ?></td>
                                <td><?php echo htmlspecialchars($request['quiz_title']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button type="submit" class="btn btn-approve">Approve</button>
                                    </form>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-reject">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div id="history" class="tabcontent">
            <h2>Request History</h2>
            <?php if (empty($request_history)): ?>
                <p>No request history.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>User</th>
                            <th>Quiz</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Admin</th>
                            <th>Response Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($request_history as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                                <td><?php echo htmlspecialchars($request['username']); ?></td>
                                <td><?php echo htmlspecialchars($request['quiz_title']); ?></td>
                                <td><?php echo date('M j, Y H:i', strtotime($request['request_date'])); ?></td>
                                <td>
                                    <?php
                                    $status = htmlspecialchars($request['status']);
                                    $color = '';
                                    if ($status === 'approved') $color = 'green';
                                    elseif ($status === 'rejected') $color = 'red';
                                    elseif ($status === 'completed') $color = 'blue';
                                    echo "<span style='color: $color; font-weight: bold;'>$status</span>";
                                    ?>
                                </td>
                                <td><?php echo $request['admin_username'] ? htmlspecialchars($request['admin_username']) : 'N/A'; ?></td>
                                <td><?php echo $request['response_date'] ? date('M j, Y H:i', strtotime($request['response_date'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tabcontent");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].className = tabcontent[i].className.replace(" active", "");
            }
            tablinks = document.getElementsByClassName("tablinks");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }
            document.getElementById(tabName).className += " active";
            evt.currentTarget.className += " active";
        }
    </script>
</body>
</html>
