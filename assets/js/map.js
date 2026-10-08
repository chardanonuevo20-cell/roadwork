
(() => {
    const mapElement = document.getElementById('study-road-map');
    const dataElement = document.getElementById('study-road-map-data');
    const panel = document.getElementById('selected-road-panel');

    if (!mapElement || !dataElement || !panel) {
        return;
    }

    const statusMessage = document.getElementById('map-status-message');
    const data = JSON.parse(dataElement.textContent);
    const roadById = new Map(data.roads.map(road => [Number(road.id), road]));
    const roadLayers = new Map();
    const colors = {
        clear: '#28683d',
        planned: '#936500',
        ongoing: '#a53636'
    };

    if (!window.L) {
        statusMessage.textContent = 'The map library could not be loaded. Road details remain available below.';
        statusMessage.hidden = false;
        return;
    }

    if (!Array.isArray(data.features) || data.features.length === 0) {
        statusMessage.textContent = 'No mapped study-road geometry is available. Road-segment boundaries have not been verified against official LGU records.';
        statusMessage.hidden = false;
        return;
    }

    const map = L.map(mapElement, {
        scrollWheelZoom: true,
        zoomControl: true,
        zoomSnap: 0.25,
        zoomDelta: 0.5
    });
    const tileLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap contributors</a>'
    }).addTo(map);
    map.createPane('study-road-casing');
    map.getPane('study-road-casing').style.zIndex = 410;
    map.createPane('study-road-highlight');
    map.getPane('study-road-highlight').style.zIndex = 420;
    const studyBounds = L.latLngBounds([]);

    const statusLabels = {
        clear: 'No Scheduled Work',
        planned: 'Upcoming / Scheduled',
        ongoing: 'Ongoing Work'
    };

    function createTextElement(tagName, className, text) {
        const element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        element.textContent = text ?? '';
        return element;
    }

    function createStatusBadge(status) {
        const safeStatus = ['clear', 'planned', 'ongoing', 'completed'].includes(status)
            ? status
            : 'clear';
        const label = statusLabels[safeStatus] ?? safeStatus.charAt(0).toUpperCase() + safeStatus.slice(1);
        return createTextElement('span', `map-status-pill map-state--${safeStatus}`, label);
    }

    function createProjectItem(project, className) {
        const projectId = project.id;
        const projectName = project.projectName ?? project.project_name;
        const organizationName = project.organizationName ?? project.organization_name;
        const workType = project.workType ?? project.work_type;
        const startDate = project.startDate ?? project.start_date;
        const targetEndDate = project.targetEndDate ?? project.target_end_date;
        const item = createTextElement('div', className);
        const title = document.createElement('strong');
        const link = document.createElement('a');
        link.href = `roadworks/view.php?id=${encodeURIComponent(projectId)}`;
        link.textContent = projectName;
        title.append(link);
        item.append(title);
        item.append(createTextElement('span', '', `${organizationName} · ${workType}`));
        item.append(createTextElement('span', '', `${startDate} to ${targetEndDate}`));
        item.append(createStatusBadge(project.status));
        return item;
    }

    function createConflict(conflict) {
        const projectName = conflict.projectName ?? conflict.project_name;
        const organizationName = conflict.organizationName ?? conflict.organization_name;
        const conflictingProjectName = conflict.conflictingProjectName ?? conflict.conflicting_project_name;
        const conflictingOrganizationName = conflict.conflictingOrganizationName ?? conflict.conflicting_organization_name;
        const detailsRestricted = conflict.detailsRestricted ?? conflict.details_restricted;
        const overlapStart = conflict.overlapStart ?? conflict.overlap_start;
        const overlapEnd = conflict.overlapEnd ?? conflict.overlap_end;
        const item = createTextElement('div', 'map-conflict');
        const details = document.createElement('dl');
        const rows = [
            ['Project', `${projectName} · ${organizationName}`],
            [
                'Potentially overlapping project',
                detailsRestricted
                    ? 'Details restricted'
                    : `${conflictingProjectName} · ${conflictingOrganizationName}`
            ],
            ['Overlap', `${overlapStart} to ${overlapEnd}`]
        ];

        for (const [label, value] of rows) {
            const row = document.createElement('div');
            row.append(createTextElement('dt', '', label));
            row.append(createTextElement('dd', '', value));
            details.append(row);
        }

        item.append(details);
        item.append(createTextElement('p', '', 'For coordination/review.'));
        return item;
    }

    function renderRoadPanel(road) {
        panel.replaceChildren();

        const heading = createTextElement('div', 'map-details-heading');
        heading.append(createTextElement('span', 'map-panel-kicker', 'Selected road'));
        heading.append(createTextElement('h2', '', road.roadName));
        heading.append(createTextElement('p', '', road.segmentName || 'Segment not specified'));
        panel.append(heading);

        const facts = document.createElement('dl');
        facts.className = 'map-road-facts';
        const classification = document.createElement('div');
        classification.append(createTextElement('dt', '', 'Road classification'));
        classification.append(createTextElement('dd', '', road.roadClassification || 'Not specified'));
        facts.append(classification);
        const state = document.createElement('div');
        state.append(createTextElement('dt', '', 'Current roadwork status'));
        const stateValue = document.createElement('dd');
        stateValue.append(createStatusBadge(road.state));
        facts.append(state);
        state.append(stateValue);
        panel.append(facts);

        const workSection = createTextElement('section', 'map-detail-section');
        workSection.append(createTextElement('h3', '', 'Current / Upcoming Roadwork'));
        if (road.currentRoadworks.length === 0) {
            workSection.append(createTextElement('p', 'map-muted-note', 'No scheduled or ongoing roadwork in the records you are authorized to view.'));
        } else {
            for (const project of road.currentRoadworks) {
                workSection.append(createProjectItem(project, 'map-project'));
            }
        }
        panel.append(workSection);

        if (road.conflicts.length > 0) {
            const conflictSection = createTextElement('section', 'map-detail-section map-conflict-section');
            conflictSection.append(createTextElement('h3', '', 'Potential Coordination Conflict'));
            for (const conflict of road.conflicts) {
                conflictSection.append(createConflict(conflict));
            }
            panel.append(conflictSection);
        }

        const historySection = createTextElement('section', 'map-detail-section');
        historySection.append(createTextElement('h3', '', 'Roadwork History'));
        if (road.history.length === 0) {
            historySection.append(createTextElement('p', 'map-muted-note', 'No roadwork history in the records you are authorized to view.'));
        } else {
            const history = createTextElement('div', 'map-history-list');
            for (const project of road.history) {
                history.append(createProjectItem(project, 'map-history-item'));
            }
            historySection.append(history);
        }
        panel.append(historySection);
    }

    function styleFor(roadId, selected) {
        const road = roadById.get(roadId);
        return {
            color: colors[road.state] ?? colors.clear,
            weight: selected ? 8 : 6,
            opacity: 1,
            lineCap: 'round',
            lineJoin: 'round'
        };
    }

    function casingStyle(selected) {
        return {
            color: '#ffffff',
            weight: selected ? 10 : 8,
            opacity: 0.9,
            lineCap: 'round',
            lineJoin: 'round'
        };
    }

    function selectRoad(roadId, updateAddress = true) {
        const road = roadById.get(roadId);
        if (!road) {
            return;
        }

        for (const [id, layers] of roadLayers.entries()) {
            for (const layerPair of layers) {
                layerPair.highlight.setStyle(styleFor(id, id === roadId));
                layerPair.casing.setStyle(casingStyle(id === roadId));
            }
        }

        renderRoadPanel(road);
        if (updateAddress) {
            const url = new URL(window.location.href);
            url.searchParams.set('road_id', String(roadId));
            window.history.replaceState({}, '', url);
        }
    }

    for (const feature of data.features) {
        const roadId = Number(feature.properties.road_id);
        const road = roadById.get(roadId);
        if (!road) {
            continue;
        }

        const casing = L.geoJSON(feature, {
            pane: 'study-road-casing',
            interactive: false,
            style: () => casingStyle(roadId === Number(data.selectedRoadId))
        });
        casing.addTo(map);

    const highlight = L.geoJSON(feature, {
        pane: 'study-road-highlight',
        interactive: true,
        style: () => ({
            color: colors[road.state] ?? colors.clear,
            weight: 6,
            opacity: 1,
            lineCap: 'round',
            lineJoin: 'round'
        })
    });
        highlight.on('click', () => selectRoad(roadId));
        studyBounds.extend(highlight.getBounds());
        highlight.addTo(map);
        if (!roadLayers.has(roadId)) {
            roadLayers.set(roadId, []);
        }
        roadLayers.get(roadId).push({ highlight, casing });
    }

    if (studyBounds.isValid()) {
        map.fitBounds(studyBounds, { padding: [6, 6], maxZoom: 16 });
    } else {
        statusMessage.textContent = 'No valid study-road geometry is available to display. Check the GeoJSON and road-to-feature mappings.';
        statusMessage.hidden = false;
    }

    tileLayer.on('tileerror', () => {
        statusMessage.textContent = 'OpenStreetMap tiles could not be loaded. Locally stored study-road geometry may still display, but its official boundaries remain unverified.';
        statusMessage.hidden = false;
    });

    map.on('click', () => map.closePopup());
    const initialRoadId = Number(data.selectedRoadId);
    if (roadById.has(initialRoadId)) {
        selectRoad(initialRoadId, false);
    }
    window.addEventListener('resize', () => map.invalidateSize());
})();
