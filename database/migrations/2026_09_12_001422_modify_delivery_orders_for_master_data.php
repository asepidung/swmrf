<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });

        $orders = DB::table('delivery_orders')->whereNotNull('driver')->orWhereNotNull('police_number')->get();
        foreach ($orders as $order) {
            $driverId = null;
            $vehicleId = null;

            if ($order->driver) {
                $driver = DB::table('drivers')->where('name', $order->driver)->first();
                if (!$driver) {
                    $driverId = DB::table('drivers')->insertGetId([
                        'name' => $order->driver,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $driverId = $driver->id;
                }
            }

            if ($order->police_number) {
                $vehicle = DB::table('vehicles')->where('police_number', $order->police_number)->first();
                if (!$vehicle) {
                    $vehicleId = DB::table('vehicles')->insertGetId([
                        'vehicle_type' => 'UNKNOWN',
                        'police_number' => $order->police_number,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $vehicleId = $vehicle->id;
                }
            }

            DB::table('delivery_orders')->where('id', $order->id)->update([
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
            ]);
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn(['driver', 'police_number']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('driver')->nullable();
            $table->string('police_number')->nullable();
        });

        $orders = DB::table('delivery_orders')->whereNotNull('driver_id')->orWhereNotNull('vehicle_id')->get();
        foreach ($orders as $order) {
            $driverName = $order->driver_id ? DB::table('drivers')->where('id', $order->driver_id)->value('name') : null;
            $policeNumber = $order->vehicle_id ? DB::table('vehicles')->where('id', $order->vehicle_id)->value('police_number') : null;
            DB::table('delivery_orders')->where('id', $order->id)->update([
                'driver' => $driverName,
                'police_number' => $policeNumber,
            ]);
        }

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn(['driver_id', 'vehicle_id']);
        });
    }
};
