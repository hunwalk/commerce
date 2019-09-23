<?php

namespace craft\commerce\migrations;

use Craft;
use craft\commerce\elements\Order;
use craft\db\Migration;
use yii\db\Expression;

/**
 * m190923_132226_update_paidStatuses migration.
 */
class m190923_132226_update_paidStatuses extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->update('{{%commerce_orders}}', [
            'paidStatus' => Order::PAID_STATUS_OVERPAID,
        ], [
            'and',
            ['isCompleted' => true],
            ['>', 'totalPaid', 0],
            new Expression('[[totalPaid]] > [[totalPrice]]'),
        ], [], false);
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        echo "m190923_132226_update_paidStatuses cannot be reverted.\n";
        return false;
    }
}
