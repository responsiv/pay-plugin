<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_methods_user_groups', function(Blueprint $table) {
            $table->integer('payment_method_id')->unsigned();
            $table->integer('user_group_id')->unsigned();
            $table->primary(['payment_method_id', 'user_group_id'], 'responsiv_pay_method_user_group');
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_methods_user_groups');
    }
};
