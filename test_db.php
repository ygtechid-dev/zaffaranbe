<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';

// Force initialization of the database
$app->make('db');

try {
    $users = App\Models\User::all();
    if ($users->isEmpty()) {
        echo "No users found.\n";
    } else {
        foreach ($users as $user) {
            echo $user->email . " - " . $user->role . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
