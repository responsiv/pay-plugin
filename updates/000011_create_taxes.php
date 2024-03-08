<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_taxes', function($table)
        {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->mediumText('rates')->nullable();
            $table->string('code', 30)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_taxes');
    }
};
