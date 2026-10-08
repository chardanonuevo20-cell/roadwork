<?php

require_once __DIR__ . '/auth.php';

function roadworkSelectQuery(): string
{
    return 'SELECT rw.id, rw.road_id, rw.project_name, rw.work_type, rw.description,
                   rw.start_date, rw.target_end_date, rw.status, rw.review_status,
                   o.organization_name,
                   r.road_name,
                   r.segment_name
            FROM roadworks rw
            INNER JOIN organizations o ON o.id = rw.organization_id
            INNER JOIN roads r ON r.id = rw.road_id';
}

function fetchVisibleRoadworks(PDO $pdo): array
{
    requireLogin();

    $orderBy = ' ORDER BY rw.start_date ASC, rw.target_end_date ASC, rw.id ASC';

    if (($_SESSION['role'] ?? '') === 'admin') {
        return $pdo->query(roadworkSelectQuery() . $orderBy)->fetchAll();
    }

    $organizationId = filter_var(
        $_SESSION['organization_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($organizationId === false || $organizationId === null) {
        http_response_code(403);
        exit('Access denied.');
    }

    $statement = $pdo->prepare(
        roadworkSelectQuery() . ' WHERE rw.organization_id = :organization_id' . $orderBy
    );
    $statement->execute(['organization_id' => $organizationId]);

    return $statement->fetchAll();
}

function fetchVisibleRoadworkById(PDO $pdo, int $roadworkId): ?array
{
    requireLogin();

    if ($roadworkId < 1) {
        return null;
    }

    $query = roadworkSelectQuery() . ' WHERE rw.id = :roadwork_id';
    $parameters = ['roadwork_id' => $roadworkId];

    if (($_SESSION['role'] ?? '') !== 'admin') {
        $organizationId = filter_var(
            $_SESSION['organization_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false || $organizationId === null) {
            http_response_code(403);
            exit('Access denied.');
        }

        $query .= ' AND rw.organization_id = :organization_id';
        $parameters['organization_id'] = $organizationId;
    }

    $statement = $pdo->prepare($query . ' LIMIT 1');
    $statement->execute($parameters);
    $roadwork = $statement->fetch();

    return $roadwork ?: null;
}

function fetchPotentialCoordinationConflicts(PDO $pdo): array
{
    requireLogin();

    $isAdmin = ($_SESSION['role'] ?? '') === 'admin';
    $parameters = [];

    if ($isAdmin) {
        $visibleColumns = '
            a.id AS a_id,
            a.project_name AS a_project_name,
            organization_a.organization_name AS a_organization_name,
            a.start_date AS a_start_date,
            a.target_end_date AS a_target_end_date,
            b.id AS b_id,
            b.project_name AS b_project_name,
            organization_b.organization_name AS b_organization_name,
            b.start_date AS b_start_date,
            b.target_end_date AS b_target_end_date';
        $organizationFilter = '';
    } else {
        $organizationId = filter_var(
            $_SESSION['organization_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($organizationId === false || $organizationId === null) {
            http_response_code(403);
            exit('Access denied.');
        }

        $visibleColumns = '
            CASE WHEN a.organization_id = :a_id_org THEN a.id END AS a_id,
            CASE WHEN a.organization_id = :a_project_org THEN a.project_name END AS a_project_name,
            CASE WHEN a.organization_id = :a_name_org THEN organization_a.organization_name END AS a_organization_name,
            CASE WHEN a.organization_id = :a_start_org THEN a.start_date END AS a_start_date,
            CASE WHEN a.organization_id = :a_end_org THEN a.target_end_date END AS a_target_end_date,
            CASE WHEN b.organization_id = :b_id_org THEN b.id END AS b_id,
            CASE WHEN b.organization_id = :b_project_org THEN b.project_name END AS b_project_name,
            CASE WHEN b.organization_id = :b_name_org THEN organization_b.organization_name END AS b_organization_name,
            CASE WHEN b.organization_id = :b_start_org THEN b.start_date END AS b_start_date,
            CASE WHEN b.organization_id = :b_end_org THEN b.target_end_date END AS b_target_end_date';
        $organizationFilter = ' AND (a.organization_id = :filter_a_org OR b.organization_id = :filter_b_org)';
        $parameters = [
            'a_id_org' => $organizationId,
            'a_project_org' => $organizationId,
            'a_name_org' => $organizationId,
            'a_start_org' => $organizationId,
            'a_end_org' => $organizationId,
            'b_id_org' => $organizationId,
            'b_project_org' => $organizationId,
            'b_name_org' => $organizationId,
            'b_start_org' => $organizationId,
            'b_end_org' => $organizationId,
            'filter_a_org' => $organizationId,
            'filter_b_org' => $organizationId,
        ];
    }

    $query = 'SELECT ' . $visibleColumns . ',
                     road.id AS road_id,
                     road.road_name,
                     road.segment_name,
                     GREATEST(a.start_date, b.start_date) AS overlap_start,
                     LEAST(a.target_end_date, b.target_end_date) AS overlap_end
              FROM roadworks a
              INNER JOIN roadworks b ON b.road_id = a.road_id AND a.id < b.id
              INNER JOIN roads road ON road.id = a.road_id
              INNER JOIN organizations organization_a ON organization_a.id = a.organization_id
              INNER JOIN organizations organization_b ON organization_b.id = b.organization_id
              WHERE a.status IN (\'planned\', \'ongoing\')
                AND b.status IN (\'planned\', \'ongoing\')
                AND a.start_date <= b.target_end_date
                AND b.start_date <= a.target_end_date' .
                $organizationFilter . '
              ORDER BY road.road_name, overlap_start, a.id, b.id';

    if ($isAdmin) {
        $rows = $pdo->query($query)->fetchAll();
    } else {
        $statement = $pdo->prepare($query);
        $statement->execute($parameters);
        $rows = $statement->fetchAll();
    }

    $conflicts = [];
    foreach ($rows as $row) {
        $sideA = [
            'id' => $row['a_id'] === null ? null : (int) $row['a_id'],
            'project_name' => $row['a_project_name'],
            'organization_name' => $row['a_organization_name'],
            'start_date' => $row['a_start_date'],
            'target_end_date' => $row['a_target_end_date'],
        ];
        $sideB = [
            'id' => $row['b_id'] === null ? null : (int) $row['b_id'],
            'project_name' => $row['b_project_name'],
            'organization_name' => $row['b_organization_name'],
            'start_date' => $row['b_start_date'],
            'target_end_date' => $row['b_target_end_date'],
        ];

        if (!$isAdmin && $sideA['id'] === null) {
            [$sideA, $sideB] = [$sideB, $sideA];
        }

        $conflicts[] = [
            'roadwork_id' => $sideA['id'],
            'project_name' => $sideA['project_name'],
            'organization_name' => $sideA['organization_name'],
            'start_date' => $sideA['start_date'],
            'target_end_date' => $sideA['target_end_date'],
            'conflicting_roadwork_id' => $sideB['id'],
            'conflicting_project_name' => $sideB['project_name'],
            'conflicting_organization_name' => $sideB['organization_name'],
            'conflicting_start_date' => $sideB['start_date'],
            'conflicting_target_end_date' => $sideB['target_end_date'],
            'details_restricted' => $sideB['project_name'] === null,
            'road_name' => $row['road_name'],
            'road_id' => (int) $row['road_id'],
            'segment_name' => $row['segment_name'],
            'overlap_start' => $row['overlap_start'],
            'overlap_end' => $row['overlap_end'],
        ];
    }

    return $conflicts;
}