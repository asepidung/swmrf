<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$programmer = App\Models\User::where('username', 'programmer')->first();
$permissions = App\Models\Permission::pluck('id');
$programmer->permissions()->sync($permissions);
echo "Permissions synced to programmer.\n";
