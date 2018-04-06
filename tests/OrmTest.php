<?php
require_once __DIR__ . '/classes.php';

use Faker\Generator;
use Illuminate\Database\Eloquent\Collection as CollectIll;
use Illuminate\Database\Query\Builder;
use Octo\Caching;
use Octo\Elegant;
use Octo\Factory;
use Octo\Inflector;
use Octo\Orm;
use Octo\Ormmodel;
use Octo\Record;
use Octo\Strings;
use Tests\Comment;
use Tests\Post;
use Tests\Postuser;
use Tests\User;

function testWithModel(UserModel $user)
{
    return $user->id * 5;
}

class CommentModel extends Ormmodel
{
    protected $table = 'comment';

    public function commentable()
    {
        return $this->morphTo();
    }
}

class UserModel extends Ormmodel
{
    protected $table = 'user';

    public function posts()
    {
        return $this->hasMany(PostModel::class, 'user_id');
    }

    public function pivots()
    {
        return $this->belongsToMany(PostModel::class, 'postuser', 'user_id', 'post_id');
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}

class PostModel extends Ormmodel
{
    protected $table = 'post';

    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function pivots()
    {
        return $this->belongsToMany(UserModel::class, 'postuser', 'post_id', 'user_id');
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}

class Posts extends Elegant
{
    protected $table = 'post';

    public function user()
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function pivots()
    {
        return $this->belongsToMany(UserModel::class, 'postuser', 'post_id', 'user_id');
    }

    public function comments()
    {
        return $this->morphMany(CommentModel::class, 'commentable');
    }
}

class OrmTest extends TestCase
{
   /** @var  PDO */
    protected $pdo;

    /** @var  Orm */
    protected $db;

    /**
     * @throws ReflectionException
     */
    public function setUp()
    {
        parent::setUp();

        $this->factories();

        $this->db = new Orm($this->getPdo());
    }

    /**
     * @throws ReflectionException
     */
    public function testInstanciator()
    {
        $app = $this->getContainer();

        $request = $app->fromGlobals();
        $app->setRequest($request->withAttribute('id', 1));

        $result = \Octo\instanciator()->callMethod('testWithModel');

        $this->assertSame(5, $result);
    }

    /**
     * @throws Exception
     */
    public function testElegant()
    {
        $this->assertInstanceOf(UserModel::class, Posts::first()->user);
    }

    /**
     * @throws ReflectionException
     */
    public function testCustomModel()
    {
        $model = new \Octo\Custom(Posts::class);
        $this->assertEquals($model::find(1), Posts::find(1));

        $model->override('find', function ($id, $row) {
            return $row->toArray();
        });

        $this->assertArrayHasKey('id', $model->withNative("find", 2));
    }

    /**
     * @test
     * @throws Exception
     */
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

    /**
     * @test
     * @throws Exception
     */
    public function checkSelect()
    {
        $q = $this->db->insert([
            'content'       => 'Lorem ipsum',
            'title'         => 'truc',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ])->into('post');

        $q->run();

        $id = $this->db->lastId();

        $row = $this->db->from('post')->find($id);

        $this->assertEquals('Lorem ipsum', $row['content']);

        $this->db->insert([
            'content'       => 'Lorem ipsum 2',
            'title'         => 'truc',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ])->into('post')->run();

        $id = $this->db->lastId();

        $row = $this->db->from('post')->find($id);

        $this->assertEquals('Lorem ipsum 2', $row['content']);
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkUpdate()
    {
        $q = $this->db->insert([
            'content' => 'Lorem ipsum'
        ])->into('post');

        $q->run();

        $this->db->update([
            'content' => 'Lorem ipsum 2'
        ])->table('post')
        ->where('id', 1)
        ->run();

        $this->assertEquals('Lorem ipsum 2', $this->db->from('post')->find(1)['content']);
    }

    /**
     * @test
     * @throws Exception
     */
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

    /**
     * @test
     * @throws Exception
     */
    public function checkCollection()
    {
        $list   = Post::collection()->pluck('id');
        $list2  = Post::pluck('id');

        $this->assertEquals($list, $list2);
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkMorph()
    {
        $comment    = Comment::first();
        $user       = $comment->commentable;

        $this->assertEquals(User::class, get_class($user->entity()));
        $this->assertGreaterThanOrEqual(1, $user->comments->count());
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkPivots()
    {
        $user   = Postuser::first()->user;
        $pivots = $user->pivots(Post::class);

        $this->assertEquals($pivots->first()->user->id, $user->id);

        $count = $pivots->count();

        $this->assertEquals(Postuser::where('user_id', $user->id)->count(), $count);
    }

    /**
     * @throws Exception
     */
    public function testOne()
    {
        $this->assertEquals(User::first(), User::one());
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkScopes()
    {
        $countScope = Post::test()->count();
        $countQuery = Post::where('id', '>', 5)->count();

        $this->assertSame($countQuery, $countScope);
    }

    /**
     * @test
     * @throws Exception
     */
    public function fnsWith()
    {
        $post = Post::findWith(1, 'user');

        $this->assertTrue($post->user instanceof Octo\Record);
        $this->assertEquals($post->user_id, $post->user->id);

        $post = Post::where('id', '>', 2)->firstWith('user');

        $this->assertTrue($post->id > 2);
        $this->assertTrue($post->user instanceof Octo\Record);
        $this->assertEquals($post->user_id, $post->user->id);
        $this->assertTrue(is_array($post->array()['user']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkBuilder()
    {
        /** @var Builder $query */
        $query = Post::builder();
        $query->where('id', '>', 2);

        $this->assertEquals(8, $query->count());
        $this->assertEquals('select * from "post" where "id" > ?', $query->toSql());
        $this->assertEquals(3, $query->first()->id);
    }

    /**
     * @test
     * @throws Exception
     */
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

    /**
     * @test
     * @throws Exception
     */
    public function checkRaw()
    {
        $row = $this->db->raw('SELECT 1 + 1 AS sum');

        $this->assertEquals(2, $row->fetch()['sum']);

        $row = $this->db->raw('SELECT date("now") AS datenow');

        $this->assertEquals(date('Y-m-d'), $row->fetch()['datenow']);
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkChunk()
    {
        $this->context('app')->test_value = 0;

        User::chunk(3, function ($rows) {
            $this->context('app')->test_value += count($rows);
        });

        $this->assertEquals(10, $this->context('app')->test_value);
    }

    /**
     * @test
     * @throws Exception
     */
    public function checkSplit()
    {
        $this->context('app')->test_value = 0;

        User::where('id', '>', 0)->split(3, function ($rows) {
            $this->context('app')->test_value += count($rows);
        });

        $this->assertEquals(10, $this->context('app')->test_value);
    }

    /**
     * @test
     * @throws Exception
     */
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

    /**
     * @test
     * @throws Exception
     */
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

    /**
     * @test
     * @throws Exception
     * @throws ReflectionException
     */
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

        $this->assertEquals(
            $this->making()->factory(User::class)(1),
            User::find(1)
        );
    }

    /**
     * @test
     * @throws Exception
     */
    public function ormmodel()
    {
        UserModel::create(['name' => 'test']);

        $this->assertCount(10, PostModel::all());
        $this->assertEquals('test', UserModel::find(11)->name);

        $this->assertEquals(66, UserModel::sum('id'));
        $this->assertEquals(6, UserModel::avg('id'));
        $this->assertEquals(1, UserModel::min('id'));
        $this->assertEquals(11, UserModel::max('id'));

        $this->assertEquals(UserModel::class, get_class(UserModel::first()));
        $this->assertEquals(CollectIll::class, get_class(UserModel::all()));

        $this->assertEquals(11, UserModel::all()->count());
        $this->assertEquals(UserModel::all()->count(), UserModel::count());

        $this->assertEquals(UserModel::class, get_class(UserModel::all()->first()));

        $this->assertEquals(UserModel::first(), UserModel::all()->first());

        UserModel::where('id', '>', 10)->update(['name' => 'test2']);
        $this->assertEquals('test2', UserModel::find(11)->name);

        UserModel::where('id', '>', 10)->delete();

        $this->assertEquals(10, UserModel::count());

        UserModel::where('id', '>', 5)->delete();

        $this->assertEquals(5, UserModel::count());

        $user = UserModel::first();

        $user->name = 'updated';

        $user->save();

        $user = UserModel::find($user->id);

        $this->assertEquals('updated', $user->name);

        $posts = $user->posts();

        if ($post = $posts->first()) {
            $u = $post->user;

            $this->assertEquals(UserModel::class, get_class($u));
            $this->assertEquals($user, $u);
        }

        foreach ($user->pivots as $pivot) {
            $this->assertEquals(PostModel::class, get_class($pivot));
            $this->assertEquals($pivot->user_id, $user->id);
            $this->assertEquals($pivot->user->id, $user->id);
        }

        if ($comment = $user->comments()->first()) {
            $this->assertEquals(CommentModel::class, get_class($comment));
            $this->assertEquals(UserModel::class, get_class($comment->commentable));
        }
    }

    /**
     * @throws Exception
     */
    public function testFactories()
    {
        Factory::save(Post::class, 15);
        $this->assertEquals(25, Post::count());

        $model = new PostModel;

        Factory::save(PostModel::class, 15);
        $this->assertEquals(40, $model->newQuery()->count());
    }

    /**
     * @throws Exception
     */
    public function testCaching()
    {
        /** @var Caching $cache */
        $cache = $this->caching();

        $this->assertNull($cache->get('test'));

        $cache->set('test', 'foo');
        $this->assertSame('foo', $cache->get('test'));

        $cache->set('test', '1');
        $this->assertSame('1', $cache->get('test'));

        $cache->set('test', 1);
        $this->assertSame(1, $cache->get('test'));

        $this->assertTrue($cache->has('test'));
        $cache->del('test');
        $this->assertFalse($cache->has('test'));

        $cache->set('test', [['name' => 'foo'], ['name' => 'bar']]);
        $this->assertCount(2, $cache->get('test'));

        $cache->set('test', new Inflector);
        $this->assertInstanceOf(Inflector::class, $cache->get('test'));

        $cache->set('foo', 'bar');

        $this->assertCount(1, $cache->keys('t*'));
        $this->assertCount(1, $cache->keys('f*'));
        $this->assertCount(2, $cache->all());

        $this->assertSame(2, $cache->flush());
        $this->assertCount(0, $cache->keys('t*'));
        $this->assertCount(0, $cache->keys('f*'));
        $this->assertCount(0, $cache->all());
    }

    private function factories()
    {
        Factory::for(Post::class, function (Generator $faker) {
            return [
                'content'   => $t = $faker->sentence,
                'user_id'   => $u = rand(1, 10),
                'title'     => Strings::urlize($t)
            ];
        });

        Factory::for(PostModel::class, function (Generator $faker) {
            return [
                'content'   => $t = $faker->sentence,
                'user_id'   => $u = rand(1, 10),
                'title'     => Strings::urlize($t)
            ];
        });
    }
}
