<?php
require_once __DIR__ . '/../config/database.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = trim($_POST['email'] ?? '');
	$password = $_POST['password'] ?? '';

	if ($email === '' || $password === '') {
		$error = 'Please enter your email and password.';
	} else {
		$statement = $pdo->prepare(
			'SELECT id, organization_id, full_name, email, password_hash, role, status
			 FROM users
			 WHERE email = :email
			 LIMIT 1'
		);
		$statement->execute(['email' => $email]);
		$user = $statement->fetch();

		if ($user && $user['status'] === 'active' && password_verify($password, $user['password_hash'])) {
			session_regenerate_id(true);

			$_SESSION['user_id'] = $user['id'];
			$_SESSION['organization_id'] = $user['organization_id'];
			$_SESSION['full_name'] = $user['full_name'];
			$_SESSION['role'] = $user['role'];

			header('Location: ../dashboard.php');
			exit;
		}

		$error = 'Invalid login credentials.';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login | Roadwork Platform</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
	<main class="container auth-shell">
		<section class="card auth-card">
			<header class="auth-heading">
				<h1>Sign in</h1>
				<p>Access the Roadwork Coordination Platform.</p>
			</header>

			<?php if ($error !== ''): ?>
				<div class="alert"><?= htmlspecialchars($error) ?></div>
			<?php endif; ?>

			<form class="auth-form" method="post">
				<label for="email">Email address</label>
				<input type="email" id="email" name="email" autocomplete="username" required>
				<label for="password">Password</label>
				<input type="password" id="password" name="password" autocomplete="current-password" required>
				<button class="button" type="submit">Sign in</button>
			</form>
		</section>
	</main>
</body>
</html>
