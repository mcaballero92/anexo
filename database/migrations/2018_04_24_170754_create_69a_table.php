<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Create69aTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('69a', function (Blueprint $table) {
            //$table->increments('id');
            $table->string('rfc', 13)->index();
            $table->string('razon_social')->default('');
            $table->string('tipo_persona')->default('');
            $table->string('supuesto')->default('');
            $table->dateTime('fecha_primera_publicacion')->nullable(true);
            $table->string('monto')->nullable(true);
            $table->dateTime('fecha_publicacion')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('69a');
    }
}
