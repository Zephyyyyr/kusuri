<?php
session_start();
require_once 'config/database.php';
require_once 'classes/User.php';
require_once 'classes/Mission.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'shimei') {
    header('Location: login.php');
    exit;
}

$user = new User($pdo);
$mission = new Mission($pdo);
$currentUser = $user->getUserById($_SESSION['user_id']);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mission_id']) && isset($_POST['status'])) {
        $missionId = filter_input(INPUT_POST, 'mission_id', FILTER_SANITIZE_NUMBER_INT);
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
        
        if ($mission->updateMissionStatus($missionId, $status)) {
            $success = 'Mission status updated successfully!';
        } else {
            $error = 'Failed to update mission status.';
        }
    }
}

$allMissions = $mission->getAllMissions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shimei Dashboard - Mission Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Shimei Mission Management</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card shadow">
            <div class="card-body">
                <h3 class="card-title mb-4">Mission Management</h3>
                <?php if (empty($allMissions)): ?>
                    <p class="text-muted">No missions available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Submitted By</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allMissions as $mission): ?>
                                    <tr>
                                        <td>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#missionModal<?php echo $mission['id']; ?>">
                                                <?php echo htmlspecialchars($mission['title']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($mission['username']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $mission['priority'] === 'high' ? 'danger' : 
                                                    ($mission['priority'] === 'medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($mission['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $mission['status'] === 'pending' ? 'warning' : 
                                                    ($mission['status'] === 'approved' ? 'success' : 
                                                    ($mission['status'] === 'rejected' ? 'danger' : 'info')); 
                                            ?>">
                                                <?php echo ucfirst($mission['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d', strtotime($mission['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="updateStatus(<?php echo $mission['id']; ?>, 'approved')">
                                                    Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="updateStatus(<?php echo $mission['id']; ?>, 'rejected')">
                                                    Reject
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Mission Modal -->
                                    <div class="modal fade" id="missionModal<?php echo $mission['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?php echo htmlspecialchars($mission['title']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <p><strong>Description:</strong></p>
                                                    <p><?php echo nl2br(htmlspecialchars($mission['description'])); ?></p>
                                                    <p><strong>Submitted by:</strong> <?php echo htmlspecialchars($mission['username']); ?></p>
                                                    <p><strong>Priority:</strong> <?php echo ucfirst($mission['priority']); ?></p>
                                                    <p><strong>Status:</strong> <?php echo ucfirst($mission['status']); ?></p>
                                                    <p><strong>Date:</strong> <?php echo date('Y-m-d H:i', strtotime($mission['created_at'])); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="mission_id" id="missionId">
        <input type="hidden" name="status" id="status">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus(missionId, status) {
            document.getElementById('missionId').value = missionId;
            document.getElementById('status').value = status;
            document.getElementById('statusForm').submit();
        }
    </script>
</body>
</html> 