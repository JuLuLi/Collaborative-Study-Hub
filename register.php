<?php
require_once __DIR__ . "/includes/db.php";
if (session_status() === PHP_SESSION_NONE) session_start();

$err = "";
$ok  = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name = trim($_POST["name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $pass = $_POST["password"] ?? "";
  $pass2 = $_POST["password2"] ?? "";

  if ($name === "" || $email === "" || $pass === "" || $pass2 === "") {
    $err = "Please fill in all fields.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $err = "Please enter a valid email address.";
  } elseif (strlen($pass) < 6) {
    $err = "Password must be at least 6 characters.";
  } elseif ($pass !== $pass2) {
    $err = "Passwords do not match.";
  } else {
    $hash = password_hash($pass, PASSWORD_DEFAULT);

    try {
      $stmt = $conn->prepare("INSERT INTO users(name,email,password_hash) VALUES (?,?,?)");
      $stmt->bind_param("sss", $name, $email, $hash);
      $stmt->execute();

      $ok = "Account created! You can login now.";
    } catch (mysqli_sql_exception $e) {
      if (str_contains($e->getMessage(), "Duplicate")) {
        $err = "This email is already registered.";
      } else {
        $err = "Registration failed. Try again.";
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSH - Register</title>
  <link rel="stylesheet" href="assets/css/theme.css">
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="hd">
        <h1 class="h1">Create your CSH account</h1>
      </div>
      <div class="bd">

        <?php if ($err): ?>
          <div class="notice bad" style="margin-top:12px;"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>
        <?php if ($ok): ?>
          <div class="notice good" style="margin-top:12px;"><?= htmlspecialchars($ok) ?></div>
        <?php endif; ?>

        <form method="POST" class="grid" style="margin-top:14px;">
          <input class="input" name="name" placeholder="Full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          <input class="input" name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          <input class="input" name="password" type="password" placeholder="Password (min 6 chars)">
          <input class="input" name="password2" type="password" placeholder="Confirm password">

          <div class="row">
            <button class="btn btn-primary" type="submit">Create Account</button>
            <a class="btn" href="index.php">Back to Login</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>