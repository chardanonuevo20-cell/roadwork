<?php

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/roadworks.php';

$idParameter = $_GET['id'] ?? null;
$roadworkId = is_string($idParameter)
    ? filter_var($idParameter, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;

if ($roadworkId === false) {
    http_response_code(404);
    exit('Roadwork not found.');
}

$roadwork = fetchVisibleRoadworkById($pdo, $roadworkId);
if ($roadwork === null) {
    http_response_code(404);
    exit('Roadwork not found.');
}

$pageTitle = 'Roadwork Details';
$cssFile = 'roadworks.css';
$activeNav = 'roadworks';
$description = trim((string) ($roadwork['description'] ?? ''));

require __DIR__ . '/../includes/header.php';
?>
<main class="container">
    <section class="page-heading">
        <div>
            <h1><?= htmlspecialchars($roadwork['project_name']) ?></h1>
            <p>Roadwork details</p>
        </div>
        <a class="button" href="index.php">Back to Roadworks</a>
    </section>

    <dl class="card roadwork-details">
        <div>
            <dt>Road name</dt>
            <dd><?= htmlspecialchars($roadwork['road_name']) ?></dd>
        </div>
        <div>
            <dt>Segment name</dt>
            <dd><?= $roadwork['segment_name'] !== null ? htmlspecialchars($roadwork['segment_name']) : 'N/A' ?></dd>
        </div>
        <div>
            <dt>Organization</dt>
            <dd><?= htmlspecialchars($roadwork['organization_name']) ?></dd>
        </div>
        <div>
            <dt>Work type</dt>
            <dd><?= htmlspecialchars($roadwork['work_type']) ?></dd>
        </div>
        <div class="roadwork-detail-description">
            <dt>Description</dt>
            <dd><?= $description !== '' ? htmlspecialchars($description) : 'No description provided.' ?></dd>
        </div>
        <div>
            <dt>Start date</dt>
            <dd><time datetime="<?= htmlspecialchars($roadwork['start_date']) ?>"><?= date('M j, Y', strtotime($roadwork['start_date'])) ?></time></dd>
        </div>
        <div>
            <dt>Target end date</dt>
            <dd><time datetime="<?= htmlspecialchars($roadwork['target_end_date']) ?>"><?= date('M j, Y', strtotime($roadwork['target_end_date'])) ?></time></dd>
        </div>
        <div>
            <dt>Status</dt>
            <dd><span class="status-badge status-badge--<?= htmlspecialchars($roadwork['status']) ?>"><?= htmlspecialchars(ucfirst($roadwork['status'])) ?></span></dd>
        </div>
        <div>
            <dt>Review status</dt>
            <dd><?= htmlspecialchars($roadwork['review_status']) ?></dd>
        </div>
    </dl>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>