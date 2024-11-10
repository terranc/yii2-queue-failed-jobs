<?php

use yii\db\Migration;

class m240321_000001_create_failed_jobs_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%failed_jobs}}', [
            'id' => $this->primaryKey(),
            'connection' => $this->string()->notNull(),
            'queue' => $this->string()->notNull(),
            'payload' => $this->text()->notNull(),
            'exception' => $this->text()->notNull(),
            'failed_at' => $this->timestamp()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);
    }

    public function down()
    {
        $this->dropTable('{{%failed_jobs}}');
    }
}
