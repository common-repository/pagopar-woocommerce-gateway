

var markers = [];
function createMarker(coords) {
    var id
    if (markers.length < 1)
        id = 0
    else
        id = markers[markers.length - 1]._id + 1
    var popupContent =
            '<p>Quiero la entrega en esta posición</p>';
    myMarker = L.marker(coords, {
        draggable: false
    });
    myMarker._id = id
    var myPopup = myMarker.bindPopup(popupContent, {
        closeButton: false
    });
    map.addLayer(myMarker)
    markers.push(myMarker)
}


function clearMarkers(id) {
    console.log(markers)
    var new_markers = []
    markers.forEach(function (marker) {
        map.removeLayer(marker)
    })
}

function onMapClick(e) {
    clearMarkers();
    createMarker(e.latlng);
    console.log("########");
    console.log(e.latlng);
    jQuery("#billing_coordenadas").val(e.latlng.lat+","+e.latlng.lng);
}


var pagopar_mobi = jQuery("#billing_coordenadas").length;
pagopar_mobi = 1;

if (pagopar_mobi) {

    jQuery("#billing_coordenadas").parent().after("<div id=\"map\" style=\"width:100% !important;;height:200px !important;;\"></div>");
    jQuery("#billing_coordenadas").hide();



    map = L.map('map').setView([-25.3136249, -57.5733558], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://osm.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    map.on('click', onMapClick);
    //map.on('click', function() { alert('Clicked on a member of the group!'); })
    createMarker([-25.3136249, -57.5733558]);
    //MOBI


}