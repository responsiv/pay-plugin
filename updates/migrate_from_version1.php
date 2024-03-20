<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        $updater = App::make('db.updater');
        if (!Schema::hasTable('responsiv_pay_methods_user_groups')) {
            $updater->setUp(__DIR__.'/000009_create_methods_user_groups.php');
        }

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

        if (!Schema::hasColumn('responsiv_pay_invoice_statuses', 'user_message_template')) {
            Schema::table('responsiv_pay_invoice_statuses', function(Blueprint $table) {
                $table->string('user_message_template')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('responsiv_pay_invoice_logs', 'updated_user_id')) {
            Schema::table('responsiv_pay_invoice_logs', function(Blueprint $table) {
                $table->mediumText('card_data')->nullable();
                $table->bigInteger('updated_user_id')->unsigned()->nullable();
                $table->bigInteger('created_user_id')->unsigned()->nullable();
            });
        }

        if (!Schema::hasColumn('responsiv_pay_invoices', 'currency_id')) {
            Schema::table('responsiv_pay_invoices', function(Blueprint $table) {
                $table->boolean('prices_include_tax')->default(false)->nullable();
                $table->integer('currency_id')->unsigned()->nullable()->index();
                $table->renameColumn('currency', 'currency_code');
                $table->renameColumn('vat_id', 'tax_id_number');
            });
        }

        if (!Schema::hasColumn('responsiv_pay_invoice_items', 'price_less_tax')) {
            Schema::table('responsiv_pay_invoice_items', function(Blueprint $table) {
                $table->bigInteger('price_less_tax')->nullable();
                $table->bigInteger('price_with_tax')->nullable();
                $table->bigInteger('discount_less_tax')->nullable();
                $table->bigInteger('discount_with_tax')->nullable();
            });
        }
    }

    public function down()
    {
    }
};
