<?php

$routes->get('/', 'Citationgenerator::index');
$routes->get('citation-generator', 'Citationgenerator::index');
$routes->post('citation-generator/lookup', 'Citationgenerator::lookup');
$routes->post('citation-generator/search', 'Citationgenerator::search');
$routes->post('citation-generator/format', 'Citationgenerator::format');
