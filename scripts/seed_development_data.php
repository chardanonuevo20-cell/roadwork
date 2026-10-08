<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

require_once __DIR__ . '/../config/database.php';

$developmentMarker = '[DEV TEST DATA]';
$passwords = [
    'admin' => 'RoadworkAdmin!2026',
    'lgu' => 'DasmarinasLGU!2026',
    'water' => 'WaterUtility!2026',
    'electric' => 'ElectricUtility!2026',
];

function findOrCreateDevelopmentOrganization(PDO $pdo, string $name, string $type): int
{
    $statement = $pdo->prepare(
        'SELECT id, organization_type
         FROM organizations
         WHERE organization_name = :organization_name'
    );
    $statement->execute(['organization_name' => $name]);

    $existingOrganizations = $statement->fetchAll();
    foreach ($existingOrganizations as $organization) {
        if ($organization['organization_type'] === $type) {
            return (int) $organization['id'];
        }
    }

    if ($existingOrganizations !== []) {
        throw new RuntimeException(
            "An organization named '{$name}' already exists without the development marker; refusing to duplicate it."
        );
    }

    $insert = $pdo->prepare(
        'INSERT INTO organizations (organization_name, organization_type, status)
         VALUES (:organization_name, :organization_type, :status)'
    );
    $insert->execute([
        'organization_name' => $name,
        'organization_type' => $type,
        'status' => 'active',
    ]);

    return (int) $pdo->lastInsertId();
}

function findOrCreateDevelopmentUser(PDO $pdo, array $account): int
{
    $statement = $pdo->prepare(
        'SELECT id, organization_id, full_name, password_hash, role, status
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute(['email' => $account['email']]);
    $existingUser = $statement->fetch();

    if ($existingUser) {
        $existingOrganizationId = $existingUser['organization_id'] === null
            ? null
            : (int) $existingUser['organization_id'];

        if (
            $existingUser['full_name'] !== $account['full_name']
            || $existingOrganizationId !== $account['organization_id']
            || $existingUser['role'] !== $account['role']
            || $existingUser['status'] !== 'active'
            || !password_verify($account['password'], $existingUser['password_hash'])
        ) {
            throw new RuntimeException(
                "The account email '{$account['email']}' already exists with different data; refusing to modify it."
            );
        }

        return (int) $existingUser['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (organization_id, full_name, email, password_hash, role, status)
         VALUES (:organization_id, :full_name, :email, :password_hash, :role, :status)'
    );
    $insert->execute([
        'organization_id' => $account['organization_id'],
        'full_name' => $account['full_name'],
        'email' => $account['email'],
        'password_hash' => password_hash($account['password'], PASSWORD_DEFAULT),
        'role' => $account['role'],
        'status' => 'active',
    ]);

    return (int) $pdo->lastInsertId();
}

function findOrCreateDevelopmentRoad(PDO $pdo, string $marker, array $road): int
{
    $statement = $pdo->prepare(
        'SELECT id, notes
         FROM roads
         WHERE road_name = :road_name AND segment_name = :segment_name
         LIMIT 1'
    );
    $statement->execute([
        'road_name' => $road['road_name'],
        'segment_name' => $road['segment_name'],
    ]);
    $existingRoad = $statement->fetch();

    if ($existingRoad) {
        if (strpos((string) $existingRoad['notes'], $marker) === 0) {
            return (int) $existingRoad['id'];
        }

        throw new RuntimeException(
            "Road '{$road['road_name']}' / '{$road['segment_name']}' already exists without the development marker; refusing to modify it."
        );
    }

    $insert = $pdo->prepare(
        'INSERT INTO roads (road_name, segment_name, barangay, road_classification, notes)
         VALUES (:road_name, :segment_name, :barangay, :road_classification, :notes)'
    );
    $insert->execute([
        'road_name' => $road['road_name'],
        'segment_name' => $road['segment_name'],
        'barangay' => $road['barangay'],
        'road_classification' => $road['road_classification'],
        'notes' => $marker . ' Dasmarinas City sample road.',
    ]);

    return (int) $pdo->lastInsertId();
}

function findOrCreateDevelopmentRoadwork(PDO $pdo, string $marker, array $roadwork): int
{
    $statement = $pdo->prepare(
        'SELECT id, organization_id, road_id, description
         FROM roadworks
         WHERE project_name = :project_name
         LIMIT 1'
    );
    $statement->execute(['project_name' => $roadwork['project_name']]);
    $existingRoadwork = $statement->fetch();

    if ($existingRoadwork) {
        if (
            strpos((string) $existingRoadwork['description'], $marker) === 0
            && (int) $existingRoadwork['organization_id'] === $roadwork['organization_id']
            && (int) $existingRoadwork['road_id'] === $roadwork['road_id']
        ) {
            return (int) $existingRoadwork['id'];
        }

        throw new RuntimeException(
            "Roadwork '{$roadwork['project_name']}' already exists without matching development data; refusing to modify it."
        );
    }

    $insert = $pdo->prepare(
        'INSERT INTO roadworks (
            organization_id, road_id, project_name, work_type, description,
            start_date, target_end_date, status, review_status, created_by
         ) VALUES (
            :organization_id, :road_id, :project_name, :work_type, :description,
            :start_date, :target_end_date, :status, :review_status, :created_by
         )'
    );
    $insert->execute([
        'organization_id' => $roadwork['organization_id'],
        'road_id' => $roadwork['road_id'],
        'project_name' => $roadwork['project_name'],
        'work_type' => $roadwork['work_type'],
        'description' => $marker . ' ' . $roadwork['description'],
        'start_date' => $roadwork['start_date'],
        'target_end_date' => $roadwork['target_end_date'],
        'status' => 'planned',
        'review_status' => 'approved',
        'created_by' => $roadwork['created_by'],
    ]);

    return (int) $pdo->lastInsertId();
}

$pdo->beginTransaction();

try {
    $organizations = [
        'lgu' => findOrCreateDevelopmentOrganization(
            $pdo,
            'Dasmariñas LGU',
            'development_test_lgu'
        ),
        'water' => findOrCreateDevelopmentOrganization(
            $pdo,
            'Water Utility',
            'development_test_water'
        ),
        'electric' => findOrCreateDevelopmentOrganization(
            $pdo,
            'Electric Utility',
            'development_test_electric'
        ),
    ];

    $accounts = [
        'admin' => [
            'email' => 'admin.dev@example.test',
            'password' => $passwords['admin'],
            'full_name' => '[DEV TEST] Development Admin',
            'role' => 'admin',
            'organization_id' => null,
        ],
        'lgu' => [
            'email' => 'lgu.dev@example.test',
            'password' => $passwords['lgu'],
            'full_name' => '[DEV TEST] Dasmarinas LGU User',
            'role' => 'organization',
            'organization_id' => $organizations['lgu'],
        ],
        'water' => [
            'email' => 'water.dev@example.test',
            'password' => $passwords['water'],
            'full_name' => '[DEV TEST] Water Utility User',
            'role' => 'organization',
            'organization_id' => $organizations['water'],
        ],
        'electric' => [
            'email' => 'electric.dev@example.test',
            'password' => $passwords['electric'],
            'full_name' => '[DEV TEST] Electric Utility User',
            'role' => 'organization',
            'organization_id' => $organizations['electric'],
        ],
    ];

    $users = [];
    foreach ($accounts as $key => $account) {
        $users[$key] = findOrCreateDevelopmentUser($pdo, $account);
    }

    $roads = [
        'aguinaldo' => findOrCreateDevelopmentRoad($pdo, $developmentMarker, [
            'road_name' => 'Aguinaldo Highway',
            'segment_name' => '[DEV TEST] Downtown Segment',
            'barangay' => 'Zone I',
            'road_classification' => 'National Road',
        ]),
        'congressional' => findOrCreateDevelopmentRoad($pdo, $developmentMarker, [
            'road_name' => 'Congressional Road',
            'segment_name' => '[DEV TEST] Paliparan Junction',
            'barangay' => 'Paliparan I',
            'road_classification' => 'City Road',
        ]),
        'salitran' => findOrCreateDevelopmentRoad($pdo, $developmentMarker, [
            'road_name' => 'Salitran Road',
            'segment_name' => '[DEV TEST] Salitran Proper',
            'barangay' => 'Salitran II',
            'road_classification' => 'City Road',
        ]),
    ];

    $roadworks = [
        [
            'organization_id' => $organizations['lgu'],
            'road_id' => $roads['aguinaldo'],
            'project_name' => '[DEV TEST] LGU resurfacing - Aguinaldo Highway',
            'work_type' => 'Road resurfacing',
            'description' => 'LGU resurfacing sample for schedule visibility.',
            'start_date' => '2026-11-01',
            'target_end_date' => '2026-11-20',
            'created_by' => $users['admin'],
        ],
        [
            'organization_id' => $organizations['lgu'],
            'road_id' => $roads['congressional'],
            'project_name' => '[DEV TEST] LGU drainage - Congressional Road',
            'work_type' => 'Drainage work',
            'description' => 'LGU drainage sample for schedule visibility.',
            'start_date' => '2026-12-01',
            'target_end_date' => '2026-12-15',
            'created_by' => $users['admin'],
        ],
        [
            'organization_id' => $organizations['water'],
            'road_id' => $roads['aguinaldo'],
            'project_name' => '[DEV TEST] Water main replacement - Aguinaldo Highway',
            'work_type' => 'Water main replacement',
            'description' => 'Water utility sample for schedule visibility.',
            'start_date' => '2026-11-10',
            'target_end_date' => '2026-11-30',
            'created_by' => $users['admin'],
        ],
        [
            'organization_id' => $organizations['water'],
            'road_id' => $roads['salitran'],
            'project_name' => '[DEV TEST] Water valve upgrade - Salitran Road',
            'work_type' => 'Valve replacement',
            'description' => 'Water utility sample for schedule visibility.',
            'start_date' => '2026-12-05',
            'target_end_date' => '2026-12-18',
            'created_by' => $users['admin'],
        ],
        [
            'organization_id' => $organizations['electric'],
            'road_id' => $roads['aguinaldo'],
            'project_name' => '[DEV TEST] Electric line upgrade - Aguinaldo Highway',
            'work_type' => 'Power line upgrades',
            'description' => 'Electric utility sample for schedule visibility.',
            'start_date' => '2026-11-15',
            'target_end_date' => '2026-11-25',
            'created_by' => $users['admin'],
        ],
        [
            'organization_id' => $organizations['electric'],
            'road_id' => $roads['congressional'],
            'project_name' => '[DEV TEST] Electric pole replacement - Congressional Road',
            'work_type' => 'Pole replacement',
            'description' => 'Electric utility sample for schedule visibility.',
            'start_date' => '2026-12-10',
            'target_end_date' => '2026-12-20',
            'created_by' => $users['admin'],
        ],
    ];

    foreach ($roadworks as $roadwork) {
        findOrCreateDevelopmentRoadwork($pdo, $developmentMarker, $roadwork);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'Seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo "Development seed complete. Test accounts:\n";
foreach ($accounts as $account) {
    echo $account['email'], ' / ', $account['password'], PHP_EOL;
}