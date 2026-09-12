<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_plans', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
        });

        // Data Migration
        $plans = DB::table('delivery_plans')->whereNotNull('driver')->orWhereNotNull('armada')->get();
        foreach ($plans as $plan) {
            $driverId = null;
            $vehicleId = null;

            if ($plan->driver) {
                $driver = DB::table('drivers')->where('name', $plan->driver)->first();
                if (!$driver) {
                    $driverId = DB::table('drivers')->insertGetId([
                        'name' => $plan->driver,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $driverId = $driver->id;
                }
            }

            if ($plan->armada) {
                $vehicle = DB::table('vehicles')->where('police_number', $plan->armada)->first();
                if (!$vehicle) {
                    $vehicleId = DB::table('vehicles')->insertGetId([
                        'vehicle_type' => $plan->armada,
                        'police_number' => $plan->armada,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $vehicleId = $vehicle->id;
                }
            }

            DB::table('delivery_plans')->where('id', $plan->id)->update([
                'driver_id' => $driverId,
                'vehicle_id' => $vehicleId,
            ]);
        }

        Schema::table('delivery_plans', function (Blueprint $table) {
            $table->dropColumn(['driver', 'armada']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_plans', function (Blueprint $table) {
            $table->string('driver')->nullable();
            $table->string('armada')->nullable();
        });

        $plans = DB::table('delivery_plans')->whereNotNull('driver_id')->orWhereNotNull('vehicle_id')->get();
        foreach ($plans as $plan) {
            $driverName = $plan->driver_id ? DB::table('drivers')->where('id', $plan->driver_id)->value('name') : null;
            $armadaName = $plan->vehicle_id ? DB::table('vehicles')->where('id', $plan->vehicle_id)->value('vehicle_type') : null;
            DB::table('delivery_plans')->where('id', $plan->id)->update([
                'driver' => $driverName,
                'armada' => $armadaName,
            ]);
        }

        Schema::table('delivery_plans', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropForeign(['vehicle_id']);
            $table->dropColumn(['driver_id', 'vehicle_id']);
        });
    }
};
