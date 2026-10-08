<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/roadworks.php';

$roadworks = fetchVisibleRoadworks($pdo);
$conflicts = fetchPotentialCoordinationConflicts($pdo);
$conflictedRoadworkIds = [];
foreach ($conflicts as $conflict) {
	if ($conflict['roadwork_id'] !== null) {
		$conflictedRoadworkIds[$conflict['roadwork_id']] = true;
	}
	if ($conflict['conflicting_roadwork_id'] !== null) {
		$conflictedRoadworkIds[$conflict['conflicting_roadwork_id']] = true;
	}
}

$pageTitle = 'Roadwork Schedule';
$cssFile = 'schedule.css';
$activeNav = 'schedule';

require __DIR__ . '/includes/header.php';
?>
<main class="container">
	<section class="page-heading">
		<div>
			<h1>Roadwork Schedule</h1>
			<p>Roadworks ordered by start date.</p>
		</div>
	</section>

	<section class="card schedule-card">
		<div class="schedule-table-wrap">
			<table class="schedule-table">
				<thead>
					<tr>
						<th>Project</th>
						<th>Road</th>
						<th>Segment</th>
						<th>Organization</th>
						<th>Work type</th>
						<th>Start date</th>
						<th>Target end date</th>
						<th>Coordination status</th>
						<th>Status</th>
						<th>Review</th>
					</tr>
				</thead>
				<tbody>
				<?php if (!$roadworks): ?>
					<tr>
						<td colspan="10" class="empty-state">No roadwork records found.</td>
					</tr>
				<?php else: ?>
					<?php foreach ($roadworks as $row): ?>
						<tr>
							<td><?= htmlspecialchars($row['project_name']) ?></td>
							<td><?= htmlspecialchars($row['road_name']) ?></td>
							<td><?= $row['segment_name'] !== null ? htmlspecialchars($row['segment_name']) : 'N/A' ?></td>
							<td><?= htmlspecialchars($row['organization_name']) ?></td>
							<td><?= htmlspecialchars($row['work_type']) ?></td>
							<td class="schedule-date"><time datetime="<?= htmlspecialchars($row['start_date']) ?>"><?= date('M j, Y', strtotime($row['start_date'])) ?></time></td>
							<td class="schedule-date"><time datetime="<?= htmlspecialchars($row['target_end_date']) ?>"><?= date('M j, Y', strtotime($row['target_end_date'])) ?></time></td>
							<?php $hasConflict = isset($conflictedRoadworkIds[(int) $row['id']]); ?>
							<td>
								<span class="coordination-status <?= $hasConflict ? 'coordination-status--potential' : 'coordination-status--clear' ?>">
									<?= $hasConflict ? 'Potential Coordination Conflict' : 'No Potential Conflict' ?>
								</span>
							</td>
							<td><span class="status-badge status-badge--<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(ucfirst($row['status'])) ?></span></td>
							<td><?= htmlspecialchars($row['review_status']) ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<section class="coordination-section" id="conflicts">
		<div class="page-heading">
			<div>
				<h2>Potential Coordination Conflicts</h2>
				<p>Potential conflicts require review by an authorized engineer or stakeholder.</p>
			</div>
		</div>

		<div class="card schedule-card">
			<div class="schedule-table-wrap">
				<table class="schedule-table conflict-table">
					<thead>
						<tr>
							<th>Road / segment</th>
							<th>Current roadwork</th>
							<th>Conflicting roadwork</th>
							<th>Overlapping period</th>
							<th>Reason</th>
						</tr>
					</thead>
					<tbody>
					<?php if (!$conflicts): ?>
						<tr>
							<td colspan="5" class="empty-state">No Potential Coordination Conflict identified.</td>
						</tr>
					<?php else: ?>
						<?php foreach ($conflicts as $conflict): ?>
							<tr>
								<td>
									<strong><?= htmlspecialchars($conflict['road_name']) ?></strong>
									<span class="conflict-segment"><?= $conflict['segment_name'] !== null ? htmlspecialchars($conflict['segment_name']) : 'N/A' ?></span>
								</td>
								<td>
									<strong><?= htmlspecialchars($conflict['project_name']) ?></strong>
									<span class="conflict-meta"><?= htmlspecialchars($conflict['organization_name']) ?></span>
									<span class="conflict-meta"><?= htmlspecialchars($conflict['start_date']) ?> to <?= htmlspecialchars($conflict['target_end_date']) ?></span>
								</td>
								<td>
									<?php if ($conflict['details_restricted']): ?>
										<strong>Details restricted</strong>
										<span class="conflict-meta">Another authorized organization has a roadwork on this segment.</span>
									<?php else: ?>
										<strong><?= htmlspecialchars($conflict['conflicting_project_name']) ?></strong>
										<span class="conflict-meta"><?= htmlspecialchars($conflict['conflicting_organization_name']) ?></span>
										<span class="conflict-meta"><?= htmlspecialchars($conflict['conflicting_start_date']) ?> to <?= htmlspecialchars($conflict['conflicting_target_end_date']) ?></span>
									<?php endif; ?>
								</td>
								<td class="schedule-date"><?= date('M j, Y', strtotime($conflict['overlap_start'])) ?> to <?= date('M j, Y', strtotime($conflict['overlap_end'])) ?></td>
								<td>Same road segment with overlapping work schedules.</td>
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
