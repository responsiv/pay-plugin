<?php namespace RainLab\Pay\Console;

use Schema;
use Illuminate\Console\Command;

/**
 * MigrateV1Command
 */
class MigrateV1Command extends Command
{
    /**
     * @var string name
     */
    protected $name = 'pay:migratev1';

    /**
     * @var string description
     */
    protected $description = 'Drops unused database tables and columns from Pay plugin v1 and v2';

    /**
     * handle
     */
    public function handle()
    {
        $columnsToPrune = [
            'tax_discount',
        ];

        foreach ($columnsToPrune as $column) {
            if (Schema::hasColumn('responsiv_pay_invoice_items', $column)) {
                Schema::table('responsiv_pay_invoice_items', function(Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        $columnsToPrune = [
            'admin_id',
        ];

        foreach ($columnsToPrune as $column) {
            if (Schema::hasColumn('responsiv_pay_invoice_status_logs', $column)) {
                Schema::table('responsiv_pay_invoice_status_logs', function(Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }

        $this->info("Successfully cleaned up payment table data");
    }

    /**
     * getArguments
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * getOptions
     */
    protected function getOptions()
    {
        return [];
    }
}
