window.initRequestTruckMap = function () {

    const destinationInput = document.getElementById('destinationInput');
    const destLatInput = document.getElementById('destinationLatInput');
    const destLngInput = document.getElementById('destinationLngInput');

    const pickupLocationInput = document.getElementById('pickupLocationInput');
    const pickupLatInput = document.getElementById('pickupLatInput');
    const pickupLngInput = document.getElementById('pickupLngInput');

    // Destination autocomplete (unchanged from before).
    if (destinationInput && window.google && google.maps.places) {
        const destAutocomplete = new google.maps.places.Autocomplete(destinationInput);

        destAutocomplete.addListener('place_changed', () => {
            const place = destAutocomplete.getPlace();
            if (place.geometry && place.geometry.location) {
                destLatInput.value = place.geometry.location.lat();
                destLngInput.value = place.geometry.location.lng();
            }
        });
    }

    // Pickup autocomplete — same suggest-as-you-type behavior as destination.
    if (pickupLocationInput && window.google && google.maps.places) {
        const pickupAutocomplete = new google.maps.places.Autocomplete(pickupLocationInput);

        pickupAutocomplete.addListener('place_changed', () => {
            const place = pickupAutocomplete.getPlace();
            if (place.geometry && place.geometry.location) {
                pickupLatInput.value = place.geometry.location.lat();
                pickupLngInput.value = place.geometry.location.lng();
            }
        });
    }

    const useMyLocationBtn = document.getElementById('useMyLocationBtn');

    function fillPickupFromPosition(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;

        pickupLatInput.value = lat;
        pickupLngInput.value = lng;

        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: { lat, lng } }, (results, status) => {
            if (status === 'OK' && results[0]) {
                pickupLocationInput.value = results[0].formatted_address;
            }
        });
    }

    /**
     * A single getCurrentPosition() call often returns a coarse,
     * network-based fix before the device's GPS chip has locked on.
     * This samples several readings over a few seconds via
     * watchPosition and keeps only the most accurate one (smallest
     * accuracy radius in meters) — giving a precise pickup pin
     * instead of a rough neighborhood-level guess.
     */
    function requestPreciseLocation() {
        if (!navigator.geolocation) return;

        let bestPosition = null;
        let readingsTaken = 0;
        let finished = false;
        const maxReadings = 5;
        const maxWaitMs = 6000;

        if (useMyLocationBtn) {
            useMyLocationBtn.disabled = true;
            useMyLocationBtn.dataset.originalHtml = useMyLocationBtn.innerHTML;
            useMyLocationBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        }

        const watchId = navigator.geolocation.watchPosition(
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
                console.warn('Geolocation denied or failed', err);
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
            navigator.geolocation.clearWatch(watchId);

            if (bestPosition) {
                fillPickupFromPosition(bestPosition);
            }

            if (useMyLocationBtn) {
                useMyLocationBtn.disabled = false;
                useMyLocationBtn.innerHTML = useMyLocationBtn.dataset.originalHtml;
            }
        }
    }

    if (useMyLocationBtn) {
        useMyLocationBtn.addEventListener('click', requestPreciseLocation);
    }

    // Prompt once automatically on load, using the same precise approach.
    requestPreciseLocation();
};