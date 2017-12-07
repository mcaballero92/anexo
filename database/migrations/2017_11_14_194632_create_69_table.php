<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class Create69Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('69', function (Blueprint $table) {
            //$table->increments('id');
            $table->string('rfc', 13)->index();
            $table->string('contribuyente')->default('');
            $table->string('tipo')->default('');
            $table->string('oficio')->default('');
            $table->dateTime('fecha_sat')->nullable(true);
            $table->dateTime('fecha_dof')->nullable(true);
            $table->string('url_oficio')->nullable(true);
            $table->string('url_anexo')->nullable(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('69');
    }
}
