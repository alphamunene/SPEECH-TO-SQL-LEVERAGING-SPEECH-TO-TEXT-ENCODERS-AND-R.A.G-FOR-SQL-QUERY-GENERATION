<?php
session_start();
include('config.php');

if (!isset($_SESSION['pending_email'])) {
    header("Location: loginn.php");
    exit();
}

if (isset($_POST['verify'])) {
    $entered_otp = $_POST['otp'];

    if (time() > $_SESSION['otp_expire']) {
        echo "<script>alert('OTP expired. Please login again.'); window.location='login.php';</script>";
        exit();
    }

    if ($entered_otp == $_SESSION['otp']) {

        // Save final login session
        $_SESSION['user_id'] = $_SESSION['pending_user_id'];
        $_SESSION['email'] = $_SESSION['pending_email'];

        // Clear temporary OTP
        unset($_SESSION['otp'], $_SESSION['otp_expire'], $_SESSION['pending_user_id'], $_SESSION['pending_email']);

        // Redirect to index
        echo "<script>alert('Login successful!'); window.location='index.php';</script>";
        exit();
    } else {
        echo "<script>alert('Invalid OTP!');</script>";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Verify OTP</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body style="background:#011f42;">
<div class="container d-flex justify-content-center align-items-center vh-100">
  <div class="card p-4" style="width:400px;">
    <h4 class="text-center mb-3">Enter OTP</h4>
    <form method="POST">
      <input type="number" name="otp" class="form-control mb-3" placeholder="6-digit code" required>
      <button class="btn btn-primary w-100" name="verify">Verify</button>
    </form>
  </div>
</div>
</body>
</html>
