<?php

namespace Hemp\Tests\Presentable;

use Hemp\Presenter\Presenter;
use PHPUnit_Framework_TestCase;
use Hemp\Presenter\Presentable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager;

class PresenterTest extends PHPUnit_Framework_TestCase
{
    function setupDatabase()
    {
        $capsule = new Manager;

        $capsule->addConnection([
            'driver'    => 'sqlite',
            'host'      => 'localhost',
            'database'  => ':memory:',
            'username'  => '',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Manager::schema()->create('test_models', function($table) {
            $table->increments('id');
            $table->string('first_name');
            $table->string('last_name');
            $table->timestamps();
        });
    }

    function registerCollectionMacro()
    {
        Collection::macro('present', function ($class) {
            return $this->map(function ($object) use ($class) {
                return present($object, $class);
            });
        });
    }

    function setUp()
    {
        parent::setUp();
        $this->setUpDatabase();
        $this->registerCollectionMacro();
    }

    function createModel()
    {
        return new TestModel([
            'first_name' => 'David',
            'last_name' => 'Hemphill',
        ]);
    }

    /** @test */
    function it_decorates_objects()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertInstanceOf(SamplePresenter::class, $presenter);
    }

    /** @test */
    function you_can_get_the_decorated_model()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertSame($sampleModel, $presenter->getModel());
    }

    /** @test */
    function it_can_have_its_own_methods()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertEquals('David Hemphill', $presenter->name());
    }

    /** @test */
    function it_can_call_the_decorated_objects_methods()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertEquals(90210, $presenter->timeStamp());
    }

    /** @test */
    function it_can_return_the_decorated_objects_properties()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertEquals('David', $presenter->first_name);
    }

    /** @test */
    function it_can_overload_the_decorated_objects_methods()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertEquals('This is your decorator speaking', $presenter->overloadedMethod());
    }

    /** @test */
    function it_can_have_its_own_magic_properties()
    {
        $sampleModel = $this->createModel();
        $presenter = new SamplePresenter($sampleModel);

        $this->assertEquals('David Lee Hemphill', $presenter->full_name);
    }

   /** @test */
   function you_can_call_present_on_an_eloquent_model_using_the_trait()
   {
       $presentedModel = $this->createModel()->present(SamplePresenter::class);

       $this->assertInstanceOf(Presenter::class, $presentedModel);
   }

   /** @test */
   function you_can_use_a_helper_function_to_decorate_a_model()
   {
       $presentedModel = present($this->createModel(), SamplePresenter::class);

       $this->assertInstanceOf(Presenter::class, $presentedModel);
   }

    /** @test */
    function you_can_wrap_a_collection_of_eloquent_models()
    {
        $sampleModel = $this->createModel();

        $users = collect([$sampleModel])->present(SamplePresenter::class);

        $firstUser = $users->first();

        $this->assertNotNull($firstUser);
        $this->assertEquals('David Hemphill', $firstUser->name());
        $this->assertEquals('David Lee Hemphill', $firstUser->full_name);
    }

    /** @test */
    function it_can_be_converted_to_json_and_arrays()
    {
        $now = '2016-10-14 12:00:00';
        $later = '2016-12-14 12:00:00';

        $model = TestModel::create([
            'first_name' => 'David',
            'last_name' => 'Hemphill',
            'created_at' => $now,
            'updated_at' => $later,
        ]);

        $desiredArray = [
            'full_name' => 'David Lee Hemphill',
            'first_name' => 'David',
            'last_name' => 'Hemphill',
            'created_at' => $now,
            'updated_at' => $later,
            'id' => 1,
        ];

        $mutatorArray = [
            'full_name' => 'David Lee Hemphill',
        ];

        $desired = json_encode($desiredArray);

        $decorated = new SamplePresenter($model);

        $this->assertEquals($desired, $decorated->toJson());
        $this->assertEquals($desiredArray, $decorated->toArray());
        $this->assertEquals($mutatorArray, $decorated->mutatorsToArray());
        $this->assertEquals(['fullName'], $decorated->getMutatedAttributes());
    }

    /** @test */
    function you_can_present_a_collection_multiple_times()
    {
        $now = '2015-10-14 12:00:00';
        $later = '2019-12-14 10:30:00';

        $sampleModel = TestModel::create([
            'first_name' => 'David',
            'last_name' => 'Hemphill',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sampleModel2 = TestModel::create([
            'first_name' => 'Tess',
            'last_name' => 'Rowlett',
            'created_at' => $later,
            'updated_at' => $later,
        ]);

        $users = TestModel::all()
            ->present(SamplePresenter::class)
            ->present(OtherSamplePresenter::class);

        $this->assertEquals(2015, $users->first()->published_year);
        $this->assertEquals(2015, $users->first()->publishedYear());

        $this->assertInstanceOf(OtherSamplePresenter::class, $users->first());
    }

    /** @test */
    function a_collection_of_decorated_eloquent_models_will_still_return_json()
    {
        $now = '2015-10-14 12:00:00';
        $later = '2019-12-14 10:30:00';

        $sampleModel = TestModel::create([
            'first_name' => 'David',
            'last_name' => 'Hemphill',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sampleModel2 = TestModel::create([
            'first_name' => 'Tess',
            'last_name' => 'Rowlett',
            'created_at' => $later,
            'updated_at' => $later,
        ]);

        $users = TestModel::all()->present(SamplePresenter::class);

        $desired = json_encode([
            [
                'full_name' => 'David Lee Hemphill',
                'id' => 1,
                'first_name' => 'David',
                'last_name' => 'Hemphill',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'full_name' => 'Tess Lee Rowlett',
                'id' => 2,
                'first_name' => 'Tess',
                'last_name' => 'Rowlett',
                'created_at' => $later,
                'updated_at' => $later,
            ]
        ]);

        $this->assertEquals($desired, $users->toJson());
    }
}

class TestModel extends Model
{
    use Presentable;

    protected $guarded = [];

    public function overloadedMethod()
    {
        return 'This is the original method!';
    }

    public function timeStamp()
    {
        return 90210;
    }
}

class SamplePresenter extends Presenter
{
    public function overloadedMethod()
    {
        return 'This is your decorator speaking';
    }

    public function name()
    {
        return $this->model->first_name . ' ' . $this->model->last_name;
    }

    public function getFullNameAttribute()
    {
        return $this->model->first_name . ' Lee ' . $this->model->last_name;
    }
}

class OtherSamplePresenter extends Presenter
{
    public function publishedYear()
    {
        return $this->model->created_at->format('Y');
    }

    public function getPublishedYearAttribute()
    {
        return $this->model->created_at->format('Y');
    }
}
