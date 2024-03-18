<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_items', function($table) {
            $table->increments('id');
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('tax_class_id')->unsigned()->nullable()->index();
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->bigInteger('price')->nullable();
            $table->bigInteger('total')->nullable();
            $table->bigInteger('discount')->nullable();
            $table->bigInteger('subtotal')->nullable();
            $table->boolean('is_tax_exempt')->default(false)->nullable();
            $table->bigInteger('tax')->nullable();
            $table->bigInteger('tax_discount')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_items');
    }
};
