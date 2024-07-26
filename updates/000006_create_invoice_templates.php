<?php namespace Responsiv\Pay\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::create('responsiv_pay_invoice_templates', function($table) {
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
        Schema::dropIfExists('responsiv_pay_invoice_templates');
    }
};
