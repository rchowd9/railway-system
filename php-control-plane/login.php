<?php
require_once 'auth.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login_dispatcher($username, $password)) {
        header("Location: admin.php");
        exit();
    } else {
        $error = "Invalid operational signature clearance credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transit Core Security Gateway</title>
    <style>
        body { font-family: sans-serif; background-color: #0f172a; color: #f8fafc; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .login-box { background: #1e293b; padding: 40px; border-radius: 8px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); border: 1px solid #334155; width: 320px; }
        h2 { margin-bottom: 20px; color: #38bdf8; font-size: 20px; text-transform: uppercase; letter-spacing: 1px; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #0f172a; border: 1px solid #475569; color: white; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #0284c7; border: none; color: white; font-weight: bold; border-radius: 4px; cursor: pointer; margin-top: 10px; }
        button:hover { background: #0369a1; }
        .error-msg { color: #f43f5e; font-size: 13px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>System Access Terminal</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Operator ID" required>
            <input type="password" name="password" placeholder="Secure Password Key" required>
            <button type="submit">Initialize Clearance Bridge</button>
        </form>
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
    </div>
</body>
</html>