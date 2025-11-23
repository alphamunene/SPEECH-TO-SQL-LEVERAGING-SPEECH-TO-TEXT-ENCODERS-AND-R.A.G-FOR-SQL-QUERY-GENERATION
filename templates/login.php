<?php
session_start();
include('config.php');

// --- Handle login form submission ---
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Secure query using prepared statement
    $stmt = $mysql->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            // Store session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];

            // --- Log login activity ---
            $sqlite->exec("INSERT INTO logs (action, email, timestamp, status) VALUES (
                'Login',
                '".SQLite3::escapeString($email)."',
                '".date('Y-m-d H:i:s')."',
                'active'
            )");

            echo "<script>
                alert('Login successful!');
                window.location.href = 'index.php'; // Redirect to main page
            </script>";
        } else {
            echo "<script>alert('Incorrect password!');</script>";
        }
    } else {
        echo "<script>alert('No account found with that email!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login Page</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"/>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    body {
      background-color: #011f42;
      font-family: 'Segoe UI', sans-serif;
    }
    .card { border-radius: 1rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    .form-label { font-weight: 500; }
    .btn-primary { background-color: #0d6efd; border: none; }
    .btn-primary:hover { background-color: #084298; }
  </style>
</head>

<body>
<section class="vh-100 d-flex align-items-center justify-content-center">
  <div class="card w-75">
    <div class="row g-0">
      <div class="col-md-6 d-none d-md-block">
        <img src="https://thumbs.dreamstime.com/b/illustration-vector-graphic-cartoon-character-login-concept-page-user-account-mobile-screen-desktop-computer-forms-278499964.jpg"
          alt="login form" class="img-fluid" style="border-radius: 1rem 0 0 1rem; height:100%; object-fit:cover;" />
      </div>
      <div class="col-md-6 d-flex align-items-center">
        <div class="card-body p-5 text-black">

          <form action="login.php" method="POST">
            <div class="d-flex align-items-center mb-3 pb-1">
              <i class="fas fa-database fa-2x me-3" style="color: #0d6efd;"></i>
              <span class="h1 fw-bold mb-0">Speech2SQL</span>
            </div>

            <h5 class="fw-normal mb-3 pb-3">Login into your account</h5>

            <div class="form-outline mb-4">
              <input type="email" name="email" class="form-control form-control-lg" required />
              <label class="form-label">Email address</label>
            </div>

            <div class="form-outline mb-4">
              <input type="password" name="password" class="form-control form-control-lg" required />
              <label class="form-label">Password</label>
            </div>

            <button class="btn btn-primary btn-lg w-100 mb-3" type="submit" name="login">Login</button>

            <p class="mt-3">Don't have an account? 
              <a href="signup.php" style="color: #0d6efd; text-decoration: none;">Register here</a>
            </p>
            <p class="mt-3">forgot password? 
              <a href="passchange.php" style="color: #0d6efd; text-decoration: none;">Change password</a>
            </p>
          </form>

        </div>
      </div>
    </div>
  </div>
</section>
</body>
</html>
