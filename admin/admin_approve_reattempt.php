<?php
require_once '../includes/config.php';
require_once '../includes/auth_functions.php';
require_admin();

// Fetch pending requests
$stmt = $pdo->prepare("
    SELECT r.*, u.username, q.title 
    FROM reattempt_requests r
    JOIN users u ON r.user_id = u.user_id
    JOIN quizzes q ON r.quiz_id = q.quiz_id
    WHERE r.status = 'pending'
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    
    if (!in_array($action, ['approve', 'reject'])) {
        die("Invalid action");
    }
    
    $status = $action === 'approve' ? 'approved' : 'rejected';
    
    $stmt = $pdo->prepare("
        UPDATE reattempt_requests 
        SET status = ?, admin_id = ?, action_date = NOW()
        WHERE request_id = ?
    ");
    $stmt->execute([$status, $_SESSION['user_id'], $request_id]);
    
    if ($action === 'approve') {
        // Get user_id and quiz_id for this request
        $stmt = $pdo->prepare("SELECT user_id, quiz_id FROM reattempt_requests WHERE request_id = ?");
        $stmt->execute([$request_id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // You might want to implement a notification system here
        // For now, we'll just set a session variable that the quiz page checks
        // In a real system, you'd use a more robust notification method
        $_SESSION['message'] = "Reattempt request approved for user ID {$req['user_id']}";
    }
    
    header("Location: admin_approve_reattempt.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Approve Reattempt Requests</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <h1>Pending Reattempt Requests</h1>
        
        <?php if (empty($requests)): ?>
            <p>No pending requests.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Quiz</th>
                        <th>Request Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['username']); ?></td>
                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </form>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-danger">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
