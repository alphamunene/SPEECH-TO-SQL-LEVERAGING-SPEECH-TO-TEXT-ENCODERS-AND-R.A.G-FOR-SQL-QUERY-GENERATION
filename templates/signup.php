<?php
session_start();
include('config.php');

if (isset($_POST['signup'])) {
    // Collect form data
    $fname = trim($_POST['firstName']);
    $lname = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert into MySQL
    $stmt = $mysql->prepare("INSERT INTO users (firstName, lastName, email, password) VALUES (?, ?, ?, ?)");
    if (!$stmt) {
        die("<script>alert('Database error: " . $mysql->error . "');</script>");
    }

    $stmt->bind_param("ssss", $fname, $lname, $email, $password);

    if ($stmt->execute()) {
        // Store session info
        $_SESSION['user_id'] = $stmt->insert_id;
        $_SESSION['email'] = $email;

        // --- Log signup activity ---
        $sqlite->exec("INSERT INTO logs (action, email, timestamp, status) VALUES (
            'Signup',
            '".SQLite3::escapeString($email)."',
            '".date('Y-m-d H:i:s')."',
            'active'
        )");

        echo "<script>
            alert('Signup successful!');
            window.location.href = 'index.php'; // Redirect after signup
        </script>";
    } else {
        echo "<script>alert('Error: Email may already exist or database issue.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Signup Page</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      background-image: url('https://tse4.mm.bing.net/th/id/OIP.IbfYuj7BWi1MOwkqXWQWrQHaJ4?cb=12ucfimg=1&w=1200&h=1600&rs=1&pid=ImgDetMain&o=7&rm=3');
      background-size: cover;
      background-repeat: no-repeat;
      background-position: center;
      background-attachment: fixed;
      height: 100vh;
      font-family: 'Segoe UI', sans-serif;
    }
    .card {
      background-color: rgba(255, 255, 255, 0.92);
      border-radius: 20px;
      max-width: 600px;
      width: 100%;
    }
    .form-control { border-radius: 10px; }
    h2 { color: #0d6efd; }
  </style>
</head>
<body>
<section class="vh-100 d-flex align-items-center justify-content-center">
  <div class="card shadow-lg p-5">
    <h2 class="fw-bold mb-4 text-center">Sign up now</h2>

    <form action="signup.php" method="POST">
      <div class="row">
        <div class="col-md-6 mb-3">
          <input type="text" name="firstName" class="form-control" placeholder="First name" required>
        </div>
        <div class="col-md-6 mb-3">
          <input type="text" name="lastName" class="form-control" placeholder="Last name" required>
        </div>
      </div>

      <input type="email" name="email" class="form-control mb-3" placeholder="Email" required>
      <input type="password" name="password" class="form-control mb-4" placeholder="Password" required>

      <button type="submit" name="signup" class="btn btn-primary btn-lg w-100 mb-3">Sign up</button>
    </form>

    <p class="text-center">Already have an account? <a href="login.php">Login here</a></p>
  </div>
</section>
</body>
</html>
