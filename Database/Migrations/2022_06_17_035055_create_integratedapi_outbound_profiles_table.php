<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIntegratedapiOutboundProfilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('integratedapi_outbound_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            if (env('DB_CONNECTION', false) == 'pgsql') {
                $table->jsonb('profile')->default('{}'); //data diri customer
            } else {
                $table->json('profile')->default('{}');
            }
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integratedapi_outbound_profiles');
    }
}