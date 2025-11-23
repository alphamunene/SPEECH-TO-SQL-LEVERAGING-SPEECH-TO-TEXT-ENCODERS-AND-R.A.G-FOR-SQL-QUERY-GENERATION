<?php
session_start();
include('config.php'); // expects $mysql (MySQLi) and $sqlite (SQLite3)

// Helper: generate a simple CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Prefill email if user is logged in
$prefillEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change'])) {
    // Basic CSRF check
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please reload the page and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Validate inputs
        if ($email === '' || $current === '' || $new === '' || $confirm === '') {
            $error = 'All fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new === $current) {
            $error = 'New password must be different from the current password.';
        } else {
            // Fetch user securely
            $stmt = $mysql->prepare("SELECT id, email, password FROM users WHERE email = ? LIMIT 1");
            if (!$stmt) {
                $error = 'Server error. Please try again later.';
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $user = $result->fetch_assoc();

                    // Verify current password
                    if (!password_verify($current, $user['password'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        // Hash new password
                        $newHash = password_hash($new, PASSWORD_DEFAULT);

                        // Update password
                        $update = $mysql->prepare("UPDATE users SET password = ? WHERE id = ?");
                        if (!$update) {
                            $error = 'Server error while updating password.';
                        } else {
                            $update->bind_param("si", $newHash, $user['id']);
                            if ($update->execute()) {
                                // Log activity in SQLite
                                $sqlite->exec(
                                    "INSERT INTO logs (action, email, timestamp, status) VALUES (
                                        'Password Change',
                                        '".SQLite3::escapeString($user['email'])."',
                                        '".date('Y-m-d H:i:s')."',
                                        'success'
                                    )"
                                );

                                // If the changing user is the logged-in user, keep session; otherwise no change
                                if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user['id']) {
                                    $success = 'Password changed successfully.';
                                } else {
                                    $success = 'Password changed successfully. You can now log in with your new password.';
                                }

                                // Optional: rotate CSRF token after success
                                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            } else {
                                $error = 'Failed to update password. Please try again.';
                            }
                        }
                    }
                } else {
                    $error = 'No account found with that email.';
                }
            }
        }
    }

    // Log failure in SQLite (if any error occurred)
    if ($error !== '') {
        $safeEmail = SQLite3::escapeString($prefillEmail ?: ($email ?? ''));
        $sqlite->exec(
            "INSERT INTO logs (action, email, timestamp, status) VALUES (
                'Password Change',
                '".$safeEmail."',
                '".date('Y-m-d H:i:s')."',
                'failed'
            )"
        );
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <style>
        body { background-color: #011f42; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .form-label { font-weight: 500; }
        .btn-primary { background-color: #0d6efd; border: none; }
        .btn-primary:hover { background-color: #084298; }
        .hint { font-size: 0.9rem; color: #6c757d; }
    </style>
</head>
<body>
<section class="vh-100 d-flex align-items-center justify-content-center">
    <div class="card w-75">
        <div class="row g-0">
            <div class="col-md-6 d-none d-md-block">
                <img src="https://th.bing.com/th/id/R.32f41639545b0089f07cc819e1c9ce0d?rik=fdg67pqFerZq1w&pid=ImgRaw&r=0"
                     alt="password change" class="img-fluid"
                     style="border-radius: 1rem 0 0 1rem; height:100%; object-fit:cover;" />
            </div>
            <div class="col-md-6 d-flex align-items-center">
                <div class="card-body p-5 text-black">
                    <div class="d-flex align-items-center mb-3 pb-1">
                        <i class="fas fa-key fa-2x me-3" style="color: #0d6efd;"></i>
                        <span class="h1 fw-bold mb-0">Speech2SQL</span>
                    </div>
                    <h5 class="fw-normal mb-3 pb-3">Change your password</h5>

                    <?php if ($error !== ''): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php elseif ($success !== ''): ?>
                        <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form action="passchange.php" method="POST" novalidate>
                        <div class="form-outline mb-4">
                            <input type="email" name="email" class="form-control form-control-lg"
                                   value="<?php echo htmlspecialchars($prefillEmail); ?>" required />
                            <label class="form-label">Email address</label>
                        </div>

                        <div class="form-outline mb-4">
                            <input type="password" name="current_password" class="form-control form-control-lg" required />
                            <label class="form-label">Current password</label>
                        </div>

                        <div class="form-outline mb-2">
                            <input type="password" name="new_password" class="form-control form-control-lg" required />
                            <label class="form-label">New password</label>
                        </div>
                        <p class="hint mb-3">Use at least 8 characters. Include letters, numbers, and symbols for strength.</p>

                        <div class="form-outline mb-4">
                            <input type="password" name="confirm_password" class="form-control form-control-lg" required />
                            <label class="form-label">Confirm new password</label>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                        <button class="btn btn-primary btn-lg w-100 mb-3" type="submit" name="change">Change password</button>

                        <p class="mt-3"><a href="login.php" style="color: #0d6efd; text-decoration: none;">Back to login</a></p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>
