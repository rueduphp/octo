<?php
    use Phinx\Migration\AbstractMigration;

    class CreatePostTable extends AbstractMigration
    {
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
