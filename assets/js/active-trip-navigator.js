(function () {
    const cfg = window.LUX_TRIP_CONFIG;
    if (!cfg) return;

    let map, driverMarker, fullPathPolyline, remainingPolyline, directionsService;
    let lastSpokenIndex = -1;
    let watchId;
    let lastRouteCallAt = 0;

    function stripHtml(html) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return tmp.textContent || tmp.innerText || '';
    }

    function speak(text) {
        if (!('speechSynthesis' in window)) return;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(new SpeechSynthesisUtterance(text));
    }

    function haversineMeters(lat1, lng1, lat2, lng2) {
        const R = 6371000;
        const toRad = d => d * Math.PI / 180;
        const dLat = toRad(lat2 - lat1);
        const dLng = toRad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function buildPrecisePath(route) {
        const path = [];
        route.legs.forEach(leg => {
            leg.steps.forEach(step => {
                step.path.forEach(point => path.push(point));
            });
        });
        return path;
    }

    window.initActiveTripMap = function () {
        map = new google.maps.Map(document.getElementById('tripMap'), {
            zoom: 16,
            center: cfg.target,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true
        });

        directionsService = new google.maps.DirectionsService();

        fullPathPolyline = new google.maps.Polyline({
            strokeColor: '#888888',
            strokeOpacity: 0.6,
            strokeWeight: 6,
            map: map
        });

        remainingPolyline = new google.maps.Polyline({
            strokeColor: '#4285F4',
            strokeOpacity: 1,
            strokeWeight: 6,
            map: map
        });

        driverMarker = new google.maps.Marker({
            map: map,
            icon: { url: 'https://maps.google.com/mapfiles/ms/icons/blue-dot.png' },
            title: 'You'
        });

        const etaEl = document.getElementById('tripEta');
        const distEl = document.getElementById('tripDistance');
        if (etaEl) etaEl.textContent = 'Acquiring precise location...';
        if (distEl) distEl.textContent = '—';

        acquirePreciseFix();
    };

    /**
     * Sample several GPS readings over a few seconds and keep only the
     * most accurate one. This is what a fresh "Start Trip" position
     * should be based on, instead of whatever the first (often coarse)
     * watchPosition callback happens to return.
     */
    function acquirePreciseFix() {
        if (!navigator.geolocation) return;

        let bestPosition = null;
        let readingsTaken = 0;
        let finished = false;
        const maxReadings = 5;
        const maxWaitMs = 6000;

        const acquisitionWatchId = navigator.geolocation.watchPosition(
            (position) => {
                readingsTaken++;

                if (!bestPosition || position.coords.accuracy < bestPosition.coords.accuracy) {
                    bestPosition = position;
                }

                if (readingsTaken >= maxReadings) {
                    finish();
                }
            },
            (err) => {
                console.error('GPS acquisition error', err);
                finish();
            },
            {
                enableHighAccuracy: true,
                maximumAge: 0,
                timeout: maxWaitMs
            }
        );

        const timeoutId = setTimeout(finish, maxWaitMs);

        function finish() {
            if (finished) return;
            finished = true;

            clearTimeout(timeoutId);
            navigator.geolocation.clearWatch(acquisitionWatchId);

            if (bestPosition) {
                onPosition(bestPosition);
            }

            // Continuous tracking begins only after the precise initial fix.
            startWatching();
        }
    }

    function startWatching() {
        if (!navigator.geolocation) return;

        watchId = navigator.geolocation.watchPosition(onPosition, (err) => {
            console.error('GPS error', err);
        }, {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 10000
        });
    }

    function onPosition(position) {
        const driverPos = { lat: position.coords.latitude, lng: position.coords.longitude };

        driverMarker.setPosition(driverPos);
        map.panTo(driverPos);

        sendLocationToServer(driverPos);
        recalculateRoute(driverPos);
    }

    function sendLocationToServer(pos) {
        const formData = new URLSearchParams();
        formData.append('latitude', pos.lat);
        formData.append('longitude', pos.lng);

        fetch(`${cfg.baseUrl}/api/maps/update_driver_location.php`, { method: 'POST', body: formData })
            .catch(err => console.error('Location update failed', err));
    }

    function recalculateRoute(driverPos) {
        const now = Date.now();
        if (now - lastRouteCallAt < 3000) return;
        lastRouteCallAt = now;

        const isFirstRoute = fullPathPolyline.getPath().getLength() === 0;

        directionsService.route({
            origin: driverPos,
            destination: cfg.target,
            travelMode: google.maps.TravelMode.DRIVING
        }, (result, status) => {
            if (status !== 'OK') return;

            const route = result.routes[0];
            const leg = route.legs[0];
            const precisePath = buildPrecisePath(route);

            if (isFirstRoute) {
                fullPathPolyline.setPath(precisePath);
            }

            remainingPolyline.setPath(precisePath);

            const etaEl = document.getElementById('tripEta');
            const distEl = document.getElementById('tripDistance');
            if (etaEl) etaEl.textContent = leg.duration.text;
            if (distEl) distEl.textContent = leg.distance.text;

            maybeAnnounceNextStep(driverPos, leg.steps);
        });
    }

    function maybeAnnounceNextStep(driverPos, legSteps) {
        if (!legSteps || legSteps.length === 0) return;

        for (let i = 0; i < legSteps.length; i++) {
            const stepEnd = legSteps[i].end_location;
            const distance = haversineMeters(driverPos.lat, driverPos.lng, stepEnd.lat(), stepEnd.lng());

            if (distance < 40 && i !== lastSpokenIndex) {
                const nextStep = legSteps[i + 1] || null;
                if (nextStep) speak(stripHtml(nextStep.instructions));
                lastSpokenIndex = i;
                break;
            }
        }
    }

    window.addEventListener('beforeunload', () => {
        if (watchId) navigator.geolocation.clearWatch(watchId);
        if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    });
})();