<?php
    use Phinx\Migration\AbstractMigration;

    class CreatePostTable extends AbstractMigration
    {
        /**
         * Change Method.
         *
         * Write your reversible migrations using this method.
         *
         * More information on writing migrations is available here:
         * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
         *
         * The following commands can be used in this method and Phinx will
         * automatically reverse them when rolling back:
         *
         *    createTable
         *    renameTable
         *    addColumn
         *    renameColumn
         *    addIndex
         *    addForeignKey
         *
         * Remember to call "create()" or "update()" and NOT "save()" when working
         * with the Table class.
         */
        public function change()
        {
            $this->table('post')
            ->addColumn('content', 'text', ['null' => true])
            ->addColumn('title', 'string', ['null' => true])
            ->addColumn('user_id', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

            $this->table('user')
            ->addColumn('name', 'string', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

            $this->table('postuser')
            ->addColumn('user_id', 'integer')
            ->addColumn('post_id', 'integer')
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();

            $this->table('comment')
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('commentable_id', 'integer')
            ->addColumn('commentable_type', 'string', ['null' => true])
            ->addColumn('morph_id', 'integer')
            ->addColumn('morph_type', 'string', ['null' => true])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->create();
        }
    }