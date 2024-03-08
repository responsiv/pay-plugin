<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        $updater = App::make('db.updater');
        $updater->setUp(__DIR__.'/000009_create_methods_user_groups.php');

        if (!Schema::hasColumn('responsiv_pay_methods', 'is_enabled_edit')) {
            Schema::table('responsiv_pay_methods', function(Blueprint $table) {
                $table->boolean('is_enabled_edit')->default(false);
            });
        }

        if (!Schema::hasColumn('responsiv_pay_taxes', 'is_system')) {
            Schema::table('responsiv_pay_taxes', function(Blueprint $table) {
                $table->boolean('is_system')->default(false);
            });
        }

        if (!Schema::hasColumn('responsiv_pay_invoice_logs', 'updated_user_id')) {
            Schema::table('responsiv_pay_invoice_logs', function(Blueprint $table) {
                $table->mediumText('card_data')->nullable();
                $table->bigInteger('updated_user_id')->unsigned()->nullable();
                $table->bigInteger('created_user_id')->unsigned()->nullable();
            });
        }
    }

    public function down()
    {
    }
};
