<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_items', function($table) {
            $table->increments('id');
            $table->string('description')->nullable();
            $table->integer('quantity')->default(0);
            $table->bigInteger('price')->nullable();
            $table->bigInteger('price_less_tax')->nullable();
            $table->bigInteger('price_with_tax')->nullable();
            $table->bigInteger('discount')->nullable();
            $table->bigInteger('discount_less_tax')->nullable();
            $table->bigInteger('discount_with_tax')->nullable();
            $table->bigInteger('subtotal')->nullable();
            $table->bigInteger('tax')->nullable();
            $table->bigInteger('total')->nullable();
            $table->integer('sort_order')->nullable();
            $table->string('related_id')->index()->nullable();
            $table->string('related_type')->index()->nullable();
            $table->integer('invoice_id')->unsigned()->nullable()->index();
            $table->integer('tax_class_id')->unsigned()->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('responsiv_pay_invoice_items');
    }
};
