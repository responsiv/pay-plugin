<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_statuses', function($table) {
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code', 30)->index()->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('notify_user')->default(false);
            $table->string('notify_template')->index()->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_statuses');
    }
};
