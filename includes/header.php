<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$cssFile = $cssFile ?? 'style.css';
$pageTitle = $pageTitle ?? 'Roadwork Platform';
$basePath = '/roadwork-platform';
$activeNav = $activeNav ?? '';
$userName = trim((string) ($_SESSION['full_name'] ?? ''));
$userName = $userName !== '' ? $userName : 'Signed-in user';
$userRole = ($_SESSION['role'] ?? '') === 'admin' ? 'Administrator' : 'Organization user';
$organizationName = '';

if ($userRole === 'Organization user') {
	$organizationId = filter_var(
		$_SESSION['organization_id'] ?? null,
		FILTER_VALIDATE_INT,
		['options' => ['min_range' => 1]]
	);

	if ($organizationId !== false && $organizationId !== null) {
		if (!isset($pdo)) {
			require_once __DIR__ . '/../config/database.php';
		}

		$organizationStatement = $pdo->prepare(
			'SELECT organization_name FROM organizations WHERE id = :organization_id LIMIT 1'
		);
		$organizationStatement->execute(['organization_id' => $organizationId]);
		$organizationName = (string) ($organizationStatement->fetchColumn() ?: '');
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($pageTitle) ?></title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="<?= $basePath ?>/assets/css/<?= htmlspecialchars($cssFile) ?>">
	<?php if (!empty($includeLeaflet)): ?>
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
	<?php endif; ?>
</head>
<body>
	<div class="app-shell">
		<aside class="app-sidebar">
			<a class="sidebar-brand" href="<?= $basePath ?>/dashboard.php">
				<strong>Roadwork Coordination</strong>
				<span>Dasmariñas City</span>
			</a>

			<div class="sidebar-identity">
				<span class="sidebar-identity-label">Signed in as</span>
				<strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
				<span><?= htmlspecialchars($organizationName !== '' ? $organizationName : $userRole, ENT_QUOTES, 'UTF-8') ?></span>
			</div>

			<nav class="sidebar-nav" aria-label="Main navigation">
				<a class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>" href="<?= $basePath ?>/dashboard.php" <?= $activeNav === 'dashboard' ? 'aria-current="page"' : '' ?>>Dashboard</a>
				<a class="<?= $activeNav === 'roadworks' ? 'is-active' : '' ?>" href="<?= $basePath ?>/roadworks/index.php" <?= $activeNav === 'roadworks' ? 'aria-current="page"' : '' ?>>Roadworks</a>
				<a class="<?= $activeNav === 'schedule' ? 'is-active' : '' ?>" href="<?= $basePath ?>/schedule.php" <?= $activeNav === 'schedule' ? 'aria-current="page"' : '' ?>>Schedule</a>
				<a class="<?= $activeNav === 'map' ? 'is-active' : '' ?>" href="<?= $basePath ?>/map.php" <?= $activeNav === 'map' ? 'aria-current="page"' : '' ?>>Road Map</a>
				<a class="<?= $activeNav === 'conflicts' ? 'is-active' : '' ?>" href="<?= $basePath ?>/schedule.php#conflicts" <?= $activeNav === 'conflicts' ? 'aria-current="page"' : '' ?>>Potential Conflicts</a>
			</nav>

			<a class="sidebar-logout" href="<?= $basePath ?>/auth/logout.php">Logout</a>
		</aside>

		<div class="app-frame">
			<header class="app-topbar">
				<div class="topbar-context">
					<span>Roadwork Coordination</span>
					<strong><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></strong>
				</div>
				<div class="topbar-identity">
					<strong><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></strong>
					<span><?= htmlspecialchars($organizationName !== '' ? $organizationName : $userRole, ENT_QUOTES, 'UTF-8') ?></span>
				</div>
			</header>
			<div class="app-content">
