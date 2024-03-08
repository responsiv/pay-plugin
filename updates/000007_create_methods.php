<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_methods', function($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->index()->nullable();
            $table->string('class_name')->nullable();
            $table->text('description')->nullable();
            $table->text('config_data')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_methods');
    }
};
