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
            $table->integer('template_id')->unsigned()->nullable()->index();
            $table->integer('user_id')->unsigned()->nullable()->index();
            $table->string('user_ip', 15)->nullable();
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
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_discount', 15, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->text('tax_data')->nullable();
            $table->integer('payment_method_id')->unsigned()->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('hash', 40)->nullable()->index();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->string('return_page')->nullable();
            $table->timestamps();
        });

        Schema::create('responsiv_pay_invoice_logs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->string('payment_method_name')->nullable();
            $table->boolean('is_success')->default(false);
            $table->string('message')->nullable();
            $table->text('raw_response')->nullable();
            $table->text('request_data')->nullable();
            $table->text('response_data')->nullable();
            $table->integer('admin_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });

        Schema::create('responsiv_pay_invoice_items', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('tax_class_id')->unsigned()->nullable()->index();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->decimal('price', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0)->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->boolean('is_tax_exempt')->default(false);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('tax_discount', 15, 2)->default(0);
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

        Schema::create('responsiv_pay_invoice_status_logs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('status_id')->unsigned()->nullable()->index();
            $table->text('comment')->nullable();
            $table->integer('admin_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });

        Schema::create('responsiv_pay_invoice_templates', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->text('content_html')->nullable();
            $table->text('content_css')->nullable();
            $table->text('syntax_data')->nullable();
            $table->text('syntax_fields')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoices');
        Schema::dropIfExists('responsiv_pay_invoice_items');
        Schema::dropIfExists('responsiv_pay_invoice_statuses');
        Schema::dropIfExists('responsiv_pay_invoice_status_logs');
        Schema::dropIfExists('responsiv_pay_invoice_templates');
        Schema::dropIfExists('responsiv_pay_invoice_logs');
    }

}
