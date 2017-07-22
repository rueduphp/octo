<?php
    require_once __DIR__ . '/classes.php';

    use Tests\Post;
    use Tests\User;
    use Tests\Postuser;
    use Octo\Orm;
    use Octo\Record;
    use Octo\Entity;
    use Phinx\Config\Config;
    use Phinx\Migration\Manager;
    use Phinx\Migration\Manager\Environment;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;
    use Symfony\Component\Console\Output\StreamOutput;

    date_default_timezone_set('Europe/Paris');

    class OrmTest extends TestCase
    {
        protected $pdo;
        protected $db;
        protected $manager;

        public function setUp()
        {
            parent::setUp();

            $PDOoptions = [
                PDO::ATTR_CASE                 => PDO::CASE_NATURAL,
                PDO::ATTR_ERRMODE              => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS         => PDO::NULL_NATURAL,
                PDO::ATTR_DEFAULT_FETCH_MODE   => PDO::FETCH_ASSOC,
                PDO::ATTR_STRINGIFY_FETCHES    => false,
                PDO::ATTR_EMULATE_PREPARES     => false
            ];

            $this->pdo = new PDO('sqlite::memory:', null, null, $PDOoptions);

            $this->db = new Orm($this->pdo);

            context('app')->pdo = $this->pdo;

            $config = new Config([
                'paths' => [
                    'migrations' => __DIR__ . '/migrations',
                    'seeds'      => __DIR__ . '/seeds'
                ],
                'environments' => [
                    'default_database' => 'testing',
                    'testing'      => [
                        'name'       => 'testing',
                        'connection' => $this->pdo
                    ]
                ]
            ]);

            $input  = new ArrayInput([]);
            $output = new StreamOutput(fopen('php://memory', 'a', false));
            $output->setDecorated(false);

            $this->manager = new Manager($config, $input, $output);

            $this->manager->migrate('testing');
            $this->manager->seed('testing');
        }

        public function tearDown()
        {
            parent::tearDown();
        }

        /** @test */
        public function checkInsert()
        {
            $q = $this->db->insert([
                'content' => 'Lorem ipsum'
            ])->into('post');

            $sql = $q->getQuery();

            $stmt = $q->run();

            $this->assertEquals('INSERT INTO post (content) VALUES (?)', $sql);
            $this->assertEquals($stmt->queryString, $sql);
            $this->assertEquals(11, $this->db->from('post')->count());
        }

        /** @test */
        public function checkSelect()
        {
            $q = $this->db->insert([
                'content' => 'Lorem ipsum',
                'title' => 'truc',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ])->into('post');

            $stmt = $q->run();

            $id = $this->db->lastId();

            $row = $this->db->from('post')->find($id);

            $this->assertEquals('Lorem ipsum', $row['content']);

            $q = $this->db->insert([
                'content'       => 'Lorem ipsum 2',
                'title'         => 'truc',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ])->into('post')->run();

            $id = $this->db->lastId();

            $row = $this->db->from('post')->find($id);

            $this->assertEquals('Lorem ipsum 2', $row['content']);
        }

        /** @test */
        public function checkUpdate()
        {
            $q = $this->db->insert([
                'content' => 'Lorem ipsum'
            ])->into('post');

            $stmt = $q->run();

            $update = $this->db->update([
                'content' => 'Lorem ipsum 2'
            ])->table('post')
            ->where('id', 1)
            ->run();

            $this->assertEquals('Lorem ipsum 2', $this->db->from('post')->find(1)['content']);
        }

        /** @test */
        public function checkModel()
        {
            $new = Post::create([
                'content'   => 'Lorem ipsum',
                'fake'      => 'Lorem ipsum'
            ]);

            $id = Post::lastId();

            $this->assertEquals($new->content, Post::find($id)->content);

            $new->setContent('Lorem ipsum 2')->setFake('machin')->saveOrFail();

            $this->assertEquals('Lorem ipsum 2', Post::find($id)->content);
            $this->assertEquals(11, Post::count());
            $this->assertEquals(1, Post::like('content', '%2')->count());
            $this->assertEquals(1, Post::likeContent('%2')->count());

            $new->delete();

            $this->assertEquals(10, Post::count());
            $this->assertEquals(1, Post::firstOrFail()->id);
            $this->assertEquals(10, Post::last()->id);

            $user = Post::first()->user();

            $posts = $user->posts();

            $count = Post::where('user_id', $user->id)->count();

            $this->assertEquals($count, $posts->count());
        }

        /** @test */
        public function checkCollection()
        {
            $list   = Post::collection()->pluck('id');
            $list2  = Post::pluck('id');

            $this->assertEquals($list, $list2);
        }

        /** @test */
        public function checkPivots()
        {
            $user   = Postuser::first()->user;
            $pivots = $user->pivots(Post::class);

            $this->assertEquals($pivots->first()->user->id, $user->id);

            $count = $pivots->count();

            $this->assertEquals(Postuser::where('user_id', $user->id)->count(), $count);
        }

        /** @test */
        public function checkScopes()
        {
            $countScope = Post::test()->count();
            $countQuery = Post::where('id', '>', 5)->count();

            $this->assertEquals($countQuery, $countScope);
        }

        /** @test */
        public function checkRelations()
        {
            $users = User::with('posts');

            $posts = $users->first()->posts;

            $this->assertTrue($this->arrayable($posts->getNative()));

            if (!$posts->isEmpty()) {
                $this->assertTrue(0 < $posts->count());
            } else {
                $this->assertEquals(0, $posts->count());
            }

            $posts = Post::with('user');

            $first = $posts->first();

            $this->assertEquals($first->user_id, $first->user->id);
        }

        /** @test */
        public function checkRaw()
        {
            $row = $this->db->raw('SELECT 1 + 1 AS sum');

            $this->assertEquals(2, $row->fetch()['sum']);

            $row = $this->db->raw('SELECT date("now") AS datenow');

            $this->assertEquals(date('Y-m-d'), $row->fetch()['datenow']);
        }

        /** @test */
        public function checkChunk()
        {
            $this->context('app')->test_value = 0;

            $users = User::chunk(3, function ($rows) {
                $this->context('app')->test_value += count($rows);
            });

            $this->assertEquals(10, $this->context('app')->test_value);
        }

        /** @test */
        public function checkSplit()
        {
            $this->context('app')->test_value = 0;

            User::where('id', '>', 0)->split(3, function ($rows) {
                $this->context('app')->test_value += count($rows);
            });

            $this->assertEquals(10, $this->context('app')->test_value);
        }

        /** @test */
        public function checkEach()
        {
            $this->context('app')->test_value = 0;

            $status = User::where('id', '>', 0)->each(function ($row) {
                $getter = $this->getter($row->entity()->pk());
                $this->context('app')->test_value += intval($row->$getter());
            });

            $this->assertEquals(55, $this->context('app')->test_value);
            $this->assertTrue($status);
        }

        /** @test */
        public function checkResults()
        {
            $users = User::all();

            $this->assertEquals(10, $users->count());

            $user = $users->first();

            $entity = $user->entity();

            $this->assertEquals(Record::class, get_class($user));
            $this->assertEquals(User::class, get_class($entity));
            $this->assertEquals('id', $entity->pk());
            $this->assertEquals('user', $entity->table());

            $chunks = $users->chunk(2);

            $this->assertEquals(5, $chunks->count());

            $chunks = $users->chunk(3);

            $this->assertEquals(4, $chunks->count());

            $chunks = $users->chunk(4);

            $this->assertEquals(3, $chunks->count());

            $chunks = $users->chunk(5);

            $this->assertEquals(2, $chunks->count());
        }

        /** @test */
        public function checkAggregates()
        {
            $count = User::count();
            $this->assertEquals(10, $count);

            $min = User::min('id');
            $this->assertEquals(1, $min);

            $max = User::max('id');
            $this->assertEquals(10, $max);

            $sum = User::sum('id');
            $this->assertEquals(55, $sum);

            $avg = User::avg('id');
            $this->assertEquals(5.5, $avg);
        }
    }
