window.initRequestTruckMap = function () {

    const destinationInput = document.getElementById('destinationInput');
    const destLatInput = document.getElementById('destinationLatInput');
    const destLngInput = document.getElementById('destinationLngInput');

    if (destinationInput && window.google && google.maps.places) {
        const autocomplete = new google.maps.places.Autocomplete(destinationInput);

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place.geometry && place.geometry.location) {
                destLatInput.value = place.geometry.location.lat();
                destLngInput.value = place.geometry.location.lng();
            }
        });
    }

    const useMyLocationBtn = document.getElementById('useMyLocationBtn');
    const pickupLocationInput = document.getElementById('pickupLocationInput');
    const pickupLatInput = document.getElementById('pickupLatInput');
    const pickupLngInput = document.getElementById('pickupLngInput');

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

    function requestLocation() {
        if (!navigator.geolocation) return;

        navigator.geolocation.getCurrentPosition(
            fillPickupFromPosition,
            (err) => console.warn('Geolocation denied or failed', err),
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }

    if (useMyLocationBtn) {
        useMyLocationBtn.addEventListener('click', requestLocation);
    }

    // Automatically prompt for location once on load. If the tenant denies
    // it, both fields stay manually editable — nothing is blocked.
    requestLocation();
};
