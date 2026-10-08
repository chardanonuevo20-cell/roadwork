<?php

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/roadworks.php';

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

$pageTitle = 'Roadworks';
$cssFile = 'roadworks.css';
$activeNav = 'roadworks';

require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <div>
            <h1>Roadworks</h1>
            <p>Existing roadwork records from the database.</p>
        </div>
    </section>

    <section class="card table-card">
        <div class="table-wrap">
            <table>
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
                            <td>
                                <div><?= htmlspecialchars($row['project_name']) ?></div>
                                <a class="details-link" href="view.php?id=<?= (int) $row['id'] ?>">View Details</a>
                            </td>
                            <td><?= htmlspecialchars($row['road_name']) ?></td>
                            <td><?= $row['segment_name'] !== null ? htmlspecialchars($row['segment_name']) : 'N/A' ?></td>
                            <td><?= htmlspecialchars($row['organization_name']) ?></td>
                            <td><?= htmlspecialchars($row['work_type']) ?></td>
                            <td><time datetime="<?= htmlspecialchars($row['start_date']) ?>"><?= date('M j, Y', strtotime($row['start_date'])) ?></time></td>
                            <td><time datetime="<?= htmlspecialchars($row['target_end_date']) ?>"><?= date('M j, Y', strtotime($row['target_end_date'])) ?></time></td>
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
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
