<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/roadworks.php';

$roadworks = fetchVisibleRoadworks($pdo);
$conflicts = fetchPotentialCoordinationConflicts($pdo);
$statusCounts = [
    'planned' => 0,
    'ongoing' => 0,
    'completed' => 0,
];
$upcomingRoadworks = [];
$today = date('Y-m-d');

foreach ($roadworks as $roadwork) {
    if (isset($statusCounts[$roadwork['status']])) {
        $statusCounts[$roadwork['status']]++;
    }

    if (
        $roadwork['status'] === 'planned'
        && $roadwork['start_date'] >= $today
        && count($upcomingRoadworks) < 6
    ) {
        $upcomingRoadworks[] = $roadwork;
    }
}

$pageTitle = 'Dashboard';
$cssFile = 'dashboard.css';
$activeNav = 'dashboard';

require __DIR__ . '/includes/header.php';
?>
<main class="container dashboard-page">
	<section class="page-intro">
		<h1>Dashboard</h1>
		<p>Current roadwork activity and coordination overview.</p>
	</section>

	<section class="dashboard-summary" aria-label="Roadwork summary">
		<article class="card dashboard-stat dashboard-stat--total">
			<span>Total Roadworks</span>
			<strong><?= count($roadworks) ?></strong>
		</article>
		<article class="card dashboard-stat dashboard-stat--planned">
			<span>Planned Roadworks</span>
			<strong><?= $statusCounts['planned'] ?></strong>
		</article>
		<article class="card dashboard-stat dashboard-stat--ongoing">
			<span>Ongoing Roadworks</span>
			<strong><?= $statusCounts['ongoing'] ?></strong>
		</article>
		<article class="card dashboard-stat dashboard-stat--conflicts">
			<span>Potential Coordination Conflicts</span>
			<strong><?= count($conflicts) ?></strong>
		</article>
	</section>

	<p class="dashboard-status-line" aria-label="Roadwork status overview">
		<strong>Status overview</strong>
		<span class="status-badge status-badge--planned">Planned <?= $statusCounts['planned'] ?></span>
		<span class="status-badge status-badge--ongoing">Ongoing <?= $statusCounts['ongoing'] ?></span>
		<span class="status-badge status-badge--completed">Completed <?= $statusCounts['completed'] ?></span>
	</p>

	<section class="dashboard-section dashboard-section--conflicts">
		<div class="dashboard-section-heading">
			<div>
				<h2>Potential Coordination Conflicts</h2>
				<p>Review these schedule overlaps with the relevant stakeholders.</p>
			</div>
			<a href="schedule.php#conflicts">View schedule</a>
		</div>
		<div class="card dashboard-panel dashboard-panel--conflicts">
			<div class="dashboard-table-wrap">
				<table class="dashboard-table dashboard-conflict-table">
					<thead>
						<tr>
							<th>Road</th>
							<th>Project</th>
							<th>Conflicting project</th>
							<th>Overlap period</th>
						</tr>
					</thead>
					<tbody>
					<?php if (!$conflicts): ?>
						<tr>
							<td colspan="4" class="empty-state">No Potential Coordination Conflict identified.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($conflicts as $conflict): ?>
							<tr>
								<td><?= htmlspecialchars($conflict['road_name']) ?></td>
								<td><?= htmlspecialchars($conflict['project_name']) ?></td>
								<td><?= $conflict['details_restricted'] ? 'Details restricted' : htmlspecialchars($conflict['conflicting_project_name']) ?></td>
								<td><?= date('M j, Y', strtotime($conflict['overlap_start'])) ?> to <?= date('M j, Y', strtotime($conflict['overlap_end'])) ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<p class="dashboard-review-note">Potential coordination conflicts are decision-support information and require engineering/stakeholder review.</p>
	</section>

	<section class="dashboard-section">
		<div class="dashboard-section-heading">
			<div>
				<h2>Upcoming Roadworks</h2>
				<p>Planned work scheduled to start next.</p>
			</div>
			<a href="roadworks/index.php">View all roadworks</a>
		</div>
		<div class="card dashboard-panel">
			<div class="dashboard-table-wrap">
				<table class="dashboard-table">
					<thead>
						<tr>
							<th>Project</th>
							<th>Road</th>
							<th>Organization</th>
							<th>Start date</th>
							<th>Target end date</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody>
					<?php if (!$upcomingRoadworks): ?>
						<tr>
							<td colspan="6" class="empty-state">No upcoming planned roadworks.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($upcomingRoadworks as $roadwork): ?>
							<tr>
								<td>
									<a href="roadworks/view.php?id=<?= (int) $roadwork['id'] ?>"><?= htmlspecialchars($roadwork['project_name']) ?></a>
								</td>
								<td><?= htmlspecialchars($roadwork['road_name']) ?></td>
								<td><?= htmlspecialchars($roadwork['organization_name']) ?></td>
								<td><time datetime="<?= htmlspecialchars($roadwork['start_date']) ?>"><?= date('M j, Y', strtotime($roadwork['start_date'])) ?></time></td>
								<td><time datetime="<?= htmlspecialchars($roadwork['target_end_date']) ?>"><?= date('M j, Y', strtotime($roadwork['target_end_date'])) ?></time></td>
								<td><span class="status-badge status-badge--<?= htmlspecialchars($roadwork['status']) ?>"><?= htmlspecialchars(ucfirst($roadwork['status'])) ?></span></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>
</main>
<?php require __DIR__ . '/includes/footer.php'; ?>
