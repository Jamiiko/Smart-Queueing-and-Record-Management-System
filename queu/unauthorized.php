<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access | Camp Evangelista Hospital</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #E7F3FB 0%, #F2F2F2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .error-card {
            background: white;
            border-radius: 32px;
            padding: 48px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border: 1px solid #E5E9F0;
        }
        .error-icon {
            width: 80px;
            height: 80px;
            background: #FEF2F0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .error-icon i { font-size: 40px; color: #FF6F61; }
        h1 { color: #212121; margin-bottom: 12px; font-size: 1.8rem; }
        p { color: #666; margin-bottom: 24px; line-height: 1.6; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #009688;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn:hover { background: #00796B; transform: translateY(-1px); }
        .btn-secondary {
            background: #E7F3FB;
            color: #4A90E2;
            margin-left: 12px;
        }
        .btn-secondary:hover { background: #d4e4f0; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">
            <i class="fas fa-ban"></i>
        </div>
        <h1>🚫 Unauthorized Access</h1>
        <p>You do not have permission to access this page.<br>Please contact your administrator if you believe this is an error.</p>
        <a href="index.php" class="btn">
            <i class="fas fa-sign-out-alt"></i> Back to Login
        </a>
        <a href="javascript:history.back()" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Go Back
        </a>
    </div>
</body>
</html>