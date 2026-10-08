<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/roadworks.php';

$roads = $pdo->query(
    'SELECT id, road_name, segment_name, barangay, road_classification
     FROM roads
     ORDER BY road_name, segment_name, id'
)->fetchAll();
$visibleRoadworks = fetchVisibleRoadworks($pdo);
$visibleConflicts = fetchPotentialCoordinationConflicts($pdo);
$roadMapSources = require __DIR__ . '/config/road_map_sources.php';
$geometryPath = __DIR__ . '/assets/geojson/study-roads.geojson';
$geometryCollection = is_file($geometryPath)
    ? json_decode((string) file_get_contents($geometryPath), true)
    : null;
$geometryByKey = [];
foreach (($geometryCollection['features'] ?? []) as $feature) {
    $key = $feature['properties']['study_road_key'] ?? null;
    if (is_string($key)) {
        $geometryByKey[$key][] = $feature;
    }
}

$today = date('Y-m-d');
$workByRoad = [];
foreach ($visibleRoadworks as $work) {
    $workByRoad[(int) $work['road_id']][] = $work;
}

$stateLabels = [
    'clear' => 'No Scheduled Work',
    'planned' => 'Upcoming / Scheduled',
    'ongoing' => 'Ongoing Work',
];
$mapRoads = [];
$mapRoadsById = [];
$mapFeatures = [];

foreach ($roads as $road) {
    $roadId = (int) $road['id'];
    $source = null;
    foreach ($roadMapSources as $candidate) {
        if (
            $road['road_name'] === $candidate['road_name']
            && $road['barangay'] === $candidate['barangay']
            && strpos((string) $road['segment_name'], $candidate['segment_match']) !== false
        ) {
            $source = $candidate;
            break;
        }
    }

    $history = $workByRoad[$roadId] ?? [];
    $state = 'clear';
    foreach ($history as $work) {
        if ($work['status'] === 'ongoing') {
            $state = 'ongoing';
            break;
        }
        if ($work['status'] === 'planned' && $work['target_end_date'] >= $today) {
            $state = 'planned';
        }
    }

    $currentWorks = array_values(array_filter(
        $history,
        static fn (array $work): bool => $work['status'] === 'ongoing'
            || ($work['status'] === 'planned' && $work['target_end_date'] >= $today)
    ));
    usort($history, static function (array $first, array $second): int {
        return [$second['start_date'], $second['id']] <=> [$first['start_date'], $first['id']];
    });
    $roadConflicts = array_values(array_filter(
        $visibleConflicts,
        static fn (array $conflict): bool => $conflict['road_id'] === $roadId
    ));

    $roadData = [
        'id' => $roadId,
        'roadName' => $road['road_name'],
        'segmentName' => $road['segment_name'],
        'roadClassification' => $road['road_classification'],
        'state' => $state,
        'stateLabel' => $stateLabels[$state],
        'currentRoadworks' => $currentWorks,
        'history' => $history,
        'conflicts' => $roadConflicts,
    ];
    $mapRoads[] = $roadData;
    $mapRoadsById[$roadId] = $roadData;

    if ($source === null) {
        continue;
    }
    foreach ($geometryByKey[$source['key']] ?? [] as $feature) {
        $feature['properties']['road_id'] = $roadId;
        $feature['properties']['state'] = $state;
        $mapFeatures[] = $feature;
    }
}

$requestedId = isset($_GET['road_id']) && is_string($_GET['road_id'])
    ? filter_var($_GET['road_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
$selectedRoadId = $requestedId !== false && isset($mapRoadsById[$requestedId])
    ? (int) $requestedId
    : ($mapFeatures ? (int) $mapFeatures[0]['properties']['road_id'] : null);
$selectedRoad = $selectedRoadId === null ? null : $mapRoadsById[$selectedRoadId];
$mapPayload = json_encode([
    'features' => $mapFeatures,
    'roads' => $mapRoads,
    'selectedRoadId' => $selectedRoadId,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);

$includeLeaflet = true;
$pageTitle = 'Road Map';
$cssFile = 'map.css';
$activeNav = 'map';
require __DIR__ . '/includes/header.php';
?>
<main class="container map-page">
    <section class="page-heading">
        <div>
            <h1>Road Map</h1>
            <p>Selected study roads highlighted using geographic road data.</p>
        </div>
    </section>

    <section class="map-legend card" aria-label="Roadwork status legend">
        <span class="map-legend-title">Road status</span>
        <span class="map-legend-item"><i class="map-legend-swatch map-state--clear"></i>No Scheduled Work</span>
        <span class="map-legend-item"><i class="map-legend-swatch map-state--planned"></i>Upcoming / Scheduled</span>
        <span class="map-legend-item"><i class="map-legend-swatch map-state--ongoing"></i>Ongoing Work</span>
    </section>

    <p class="map-data-note">Study roads highlighted using geographic road data.</p>
    <div class="map-status-message" id="map-status-message" role="status" aria-live="polite" hidden></div>

    <section class="map-layout">
        <div class="map-canvas-wrap">
            <div class="card map-canvas" id="study-road-map" role="region" aria-label="Interactive OpenStreetMap with highlighted study roads"></div>
            <p class="map-attribution">Map data: &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></p>
        </div>

        <aside class="card map-details" id="selected-road-panel" aria-live="polite">
            <?php if ($selectedRoad === null): ?>
                <h2>Selected Road</h2>
                <p class="map-muted-note">No verified road geometry is currently available for the study roads.</p>
            <?php else: ?>
                <div class="map-details-heading">
                    <span class="map-panel-kicker">Selected road</span>
                    <h2><?= htmlspecialchars($selectedRoad['roadName']) ?></h2>
                    <p><?= $selectedRoad['segmentName'] !== null ? htmlspecialchars($selectedRoad['segmentName']) : 'Segment not specified' ?></p>
                </div>
                <div id="selected-road-content">
                    <dl class="map-road-facts">
                        <div>
                            <dt>Road classification</dt>
                            <dd><?= htmlspecialchars($selectedRoad['roadClassification'] ?? 'Not specified') ?></dd>
                        </div>
                        <div>
                            <dt>Current roadwork status</dt>
                            <dd><span class="map-status-pill map-state--<?= htmlspecialchars($selectedRoad['state']) ?>"><?= htmlspecialchars($selectedRoad['stateLabel']) ?></span></dd>
                        </div>
                    </dl>

                    <section class="map-detail-section">
                        <h3>Current / Upcoming Roadwork</h3>
                        <?php if (!$selectedRoad['currentRoadworks']): ?>
                            <p class="map-muted-note">No scheduled or ongoing roadwork in records available to your account.</p>
                        <?php else: ?>
                            <?php foreach ($selectedRoad['currentRoadworks'] as $work): ?>
                                <div class="map-project">
                                    <strong><a href="roadworks/view.php?id=<?= (int) $work['id'] ?>"><?= htmlspecialchars($work['project_name']) ?></a></strong>
                                    <span><?= htmlspecialchars($work['organization_name']) ?> · <?= htmlspecialchars($work['work_type']) ?></span>
                                    <span><?= date('M j, Y', strtotime($work['start_date'])) ?> to <?= date('M j, Y', strtotime($work['target_end_date'])) ?></span>
                                    <span class="status-badge status-badge--<?= htmlspecialchars($work['status']) ?>"><?= htmlspecialchars(ucfirst($work['status'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </section>

                    <?php if ($selectedRoad['conflicts']): ?>
                        <section class="map-detail-section map-conflict-section">
                            <h3>Potential Coordination Conflict</h3>
                            <?php foreach ($selectedRoad['conflicts'] as $conflict): ?>
                                <div class="map-conflict">
                                    <dl>
                                        <div>
                                            <dt>Project</dt>
                                            <dd><?= htmlspecialchars($conflict['project_name']) ?> · <?= htmlspecialchars($conflict['organization_name']) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Potentially overlapping project</dt>
                                            <dd><?= $conflict['details_restricted'] ? 'Details restricted' : htmlspecialchars($conflict['conflicting_project_name']) . ' · ' . htmlspecialchars($conflict['conflicting_organization_name']) ?></dd>
                                        </div>
                                        <div>
                                            <dt>Overlap</dt>
                                            <dd><?= date('M j, Y', strtotime($conflict['overlap_start'])) ?> to <?= date('M j, Y', strtotime($conflict['overlap_end'])) ?></dd>
                                        </div>
                                    </dl>
                                    <p>For coordination/review.</p>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    <?php endif; ?>

                    <section class="map-detail-section">
                        <h3>Roadwork History</h3>
                        <?php if (!$selectedRoad['history']): ?>
                            <p class="map-muted-note">No roadwork history in records available to your account.</p>
                        <?php else: ?>
                            <div class="map-history-list">
                                <?php foreach ($selectedRoad['history'] as $work): ?>
                                    <div class="map-history-item">
                                        <strong><a href="roadworks/view.php?id=<?= (int) $work['id'] ?>"><?= htmlspecialchars($work['project_name']) ?></a></strong>
                                        <span><?= htmlspecialchars($work['organization_name']) ?> · <?= htmlspecialchars($work['work_type']) ?></span>
                                        <span><?= date('M j, Y', strtotime($work['start_date'])) ?> to <?= date('M j, Y', strtotime($work['target_end_date'])) ?></span>
                                        <span class="status-badge status-badge--<?= htmlspecialchars($work['status']) ?>"><?= htmlspecialchars(ucfirst($work['status'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>
            <?php endif; ?>
        </aside>
    </section>
</main>
<script type="application/json" id="study-road-map-data"><?= $mapPayload ?: '{"features":[],"roads":[],"selectedRoadId":null}' ?></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="/roadwork-platform/assets/js/map.js?v=<?= filemtime(__DIR__ . '/assets/js/map.js') ?>" defer></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
