// ======================================
// LUX EMPIRE DRIVER LIVE TRACKER
// ======================================

console.log("LUX EMPIRE DRIVER TRACKER ACTIVE");

// ELEMENTS
const latitudeElement = document.getElementById("latitude");
const longitudeElement = document.getElementById("longitude");
const trackingStatus = document.getElementById("trackingStatus");

// MAP VARIABLES
let map;
let marker;
let accuracyCircle;
let watchId;

// ======================================
// INITIALIZE
// ======================================

function initializeTracker()
{
    if (!navigator.geolocation)
    {
        trackingStatus.innerHTML = "GPS NOT SUPPORTED";
        trackingStatus.style.color = "red";
        return;
    }

    trackingStatus.innerHTML = "CONNECTING GPS...";

    watchId = navigator.geolocation.watchPosition(

        successLocation,

        errorLocation,

        {
            enableHighAccuracy: true,
            maximumAge: 0,
            timeout: 10000
        }

    );
}

// ======================================
// SUCCESS
// ======================================

function successLocation(position)
{
    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;
    const accuracy = position.coords.accuracy;

    // UPDATE UI
    latitudeElement.innerHTML = latitude.toFixed(6);
    longitudeElement.innerHTML = longitude.toFixed(6);

    trackingStatus.innerHTML = "LIVE";
    trackingStatus.style.color = "lightgreen";

    // INIT MAP ONCE
    if (!map)
    {
        initializeMap(latitude, longitude);
    }

    // UPDATE DRIVER POSITION
    updateDriverPosition(latitude, longitude, accuracy);

    // SEND TO SERVER
    sendLocationToServer(latitude, longitude);
}

// ======================================
// ERROR
// ======================================

function errorLocation(error)
{
    console.error(error);

    trackingStatus.innerHTML = "GPS ERROR";
    trackingStatus.style.color = "red";
}

// ======================================
// MAP INITIALIZATION
// ======================================

function initializeMap(latitude, longitude)
{
    const currentPos = {
        lat: latitude,
        lng: longitude
    };

    map = new google.maps.Map(
        document.getElementById("map"),
        {
            center: currentPos,

            zoom: 17,

            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,

            gestureHandling: "greedy",

            clickableIcons: false,

            tilt: 0,

            styles: []
        }
    );

    // DRIVER MARKER
    marker = new google.maps.Marker({

        position: currentPos,

        map: map,

        title: "Driver Location",

        optimized: true,

        icon: {
            url: "https://maps.google.com/mapfiles/ms/icons/blue-dot.png"
        }
    });

    // ACCURACY CIRCLE
    accuracyCircle = new google.maps.Circle({

        strokeColor: "#4285F4",
        strokeOpacity: 0.3,
        strokeWeight: 1,

        fillColor: "#4285F4",
        fillOpacity: 0.12,

        map: map,

        center: currentPos,

        radius: 20
    });
}

// ======================================
// UPDATE POSITION
// ======================================

function updateDriverPosition(latitude, longitude, accuracy)
{
    if (!marker || !map) return;

    const newPos = {
        lat: latitude,
        lng: longitude
    };

    // MOVE MARKER
    marker.setPosition(newPos);

    // MOVE ACCURACY CIRCLE
    accuracyCircle.setCenter(newPos);
    accuracyCircle.setRadius(accuracy);

    // SMOOTH CAMERA FOLLOW
    map.panTo(newPos);
}

// ======================================
// SEND TO SERVER
// ======================================

function sendLocationToServer(latitude, longitude)
{
    fetch("../../api/maps/update_driver_location.php", {

        method: "POST",

        headers: {
            "Content-Type":
            "application/x-www-form-urlencoded"
        },

        body:
            "latitude=" + encodeURIComponent(latitude)
            +
            "&longitude=" + encodeURIComponent(longitude)

    })

    .then(response => response.json())

    .then(data => {

        console.log("GPS UPDATED", data);

    })

    .catch(error => {

        console.error("GPS UPDATE FAILED", error);

    });
}

// ======================================
// START TRACKER
// ======================================
