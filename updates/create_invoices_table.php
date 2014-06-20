<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

class CreateInvoicesTable extends Migration
{

    public function up()
    {
        Schema::create('responsiv_pay_invoices', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->string('user_ip', 15);
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('company', 100)->nullable();
            $table->string('street_addr')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('zip', 20)->nullable();
            $table->integer('state_id')->unsigned()->nullable()->index();
            $table->integer('country_id')->unsigned()->nullable()->index();
            $table->float('total')->default(0);
            $table->float('subtotal')->default(0);
            $table->integer('discount')->default(0);
            $table->float('tax')->default(0);
            $table->float('tax_discount')->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->text('tax_data');
            $table->integer('payment_type_id')->unsigned()->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('hash', 40)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('responsiv_pay_invoice_items', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->float('price')->default(0);
            $table->float('total')->default(0);
            $table->integer('discount')->default(0);
            $table->float('subtotal')->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->float('tax')->default(0);
            $table->float('tax_discount')->default(0);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('responsiv_pay_invoice_statuses', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code', 30)->index()->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('notify_user')->default(false);
            $table->string('notify_template')->index()->nullable();
        });

        Schema::create('responsiv_pay_invoice_logs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->text('comment')->nullable();
            $table->integer('admin_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::drop('responsiv_pay_invoices');
        Schema::drop('responsiv_pay_invoice_items');
        Schema::drop('responsiv_pay_invoice_statuses');
        Schema::drop('responsiv_pay_invoice_logs');
    }

}
