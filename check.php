<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$u = App\Models\User::where('username', 'ruby')->first();
if ($u) {
    echo 'Permissions: ' . implode(',', $u->permissions->pluck('name')->toArray()) . "\n";
    echo 'Push Subs: ' . $u->pushSubscriptions()->count() . "\n";
} else {
    echo "No user ruby\n";
}
