<?php
header('Content-Type: text/yaml; charset=utf-8');
readfile(__DIR__ . '/openapi.yaml');
