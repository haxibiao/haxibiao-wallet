<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExchangeConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(Schema::hasTable('exchange_configs')){
            return;
        }
        Schema::create('exchange_configs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('num')->index();
            $table->string('name')->index();
            $table->string('value');
            $table->string('status')->comment('0:禁用，1启用');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('exchange_configs');
    }
}
