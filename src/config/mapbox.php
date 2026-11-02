<?php

function get_mapbox_token() {
    return getenv('MAPBOX_ACCESS_TOKEN') ?: '';
}

function mapbox_js_url() {
    $token = get_mapbox_token();
    return 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.js';
}

function mapbox_css_url() {
    return 'https://api.mapbox.com/mapbox-gl-js/v2.15.0/mapbox-gl.css';
}
