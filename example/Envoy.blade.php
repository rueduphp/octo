@setup
require __DIR__.'/vendor/autoload.php';
(new \Dotenv\Dotenv(__DIR__, '.env'))->load();

$server = "murze.be";
$userAndServer = 'forge@'. $server;
$repository = "spatie/murze.be";
$baseDir = "/home/forge/murze.be";
$releasesDir = "{$baseDir}/releases";
$persistentDir = "{$baseDir}/persistent";
$currentDir = "{$baseDir}/current";
$newReleaseName = date('Ymd-His');
$newReleaseDir = "{$releasesDir}/{$newReleaseName}";
$user = get_current_user();

function logMessage($message) {
    return "echo '\033[32m" .$message. "\033[0m';\n";
}

@endsetup

@servers(['local' => '127.0.0.1', 'remote' => $userAndServer])

@macro('deploy')
startDeployment
cloneRepository
runComposer
runYarn
generateAssets
updateSymlinks
optimizeInstallation
backupDatabase
migrateDatabase
blessNewRelease
cleanOldReleases
finishDeploy
@endmacro

@macro('deploy-code')
deployOnlyCode
@endmacro

@task('startDeployment', ['on' => 'local'])
{{ logMessage("Starting deployment…") }}
git checkout master
git pull origin master
@endtask

@task('cloneRepository', ['on' => 'remote'])
{{ logMessage("Cloning repository…") }}
[ -d {{ $releasesDir }} ] || mkdir {{ $releasesDir }};
[ -d {{ $persistentDir }} ] || mkdir {{ $persistentDir }};
[ -d {{ $persistentDir }}/uploads ] || mkdir {{ $persistentDir }}/uploads;
[ -d {{ $persistentDir }}/storage ] || mkdir {{ $persistentDir }}/storage;
cd {{ $releasesDir }};

# Create the release dir
mkdir {{ $newReleaseDir }};

# Clone the repo
git clone --depth 1 git@github.com:{{ $repository }} {{ $newReleaseName }}

# Mark release
cd {{ $newReleaseDir }}
echo "{{ $newReleaseName }}" > public/release-name.txt
@endtask

@task('runComposer', ['on' => 'remote'])
{{ logMessage("Running Composer…") }}
cd {{ $newReleaseDir }};
composer install --prefer-dist --no-scripts --no-dev -q -o;
@endtask

@task('runYarn', ['on' => 'remote'])
{{ logMessage("Running Yarn…") }}
cd {{ $newReleaseDir }};
yarn config set ignore-engines true
yarn
@endtask

@task('generateAssets', ['on' => 'remote'])
{{ logMessage("Generating assets…") }}
cd {{ $newReleaseDir }};
yarn run production -- --progress false
@endtask

@task('updateSymlinks', ['on' => 'remote'])
{{ logMessage("Updating symlinks to persistent data…") }}
# Remove the storage directory and replace with persistent data
rm -rf {{ $newReleaseDir }}/app/storage;
cd {{ $newReleaseDir }};
ln -nfs {{ $baseDir }}/persistent/storage storage;

# Remove the public/uploads directory and replace with persistent data
rm -rf {{ $newReleaseDir }}/public/uploads;
cd {{ $newReleaseDir }};
ln -nfs {{ $baseDir }}/persistent/uploads public/uploads;

# Import the environment config
cd {{ $newReleaseDir }};
ln -nfs {{ $baseDir }}/.env .env;
@endtask

@task('optimizeInstallation', ['on' => 'remote'])
{{ logMessage("Optimizing installation…") }}
cd {{ $newReleaseDir }};
php artisan clear-compiled;
@endtask

@task('backupDatabase', ['on' => 'remote'])
{{ logMessage("Backing up database…") }}
cd {{ $newReleaseDir }}
php artisan backup:run
@endtask

@task('migrateDatabase', ['on' => 'remote'])
{{ logMessage("Migrating database…") }}
cd {{ $newReleaseDir }};
./cli migrate;
@endtask

@task('blessNewRelease', ['on' => 'remote'])
{{ logMessage("Blessing new release…") }}
ln -nfs {{ $newReleaseDir }} {{ $currentDir }};
cd {{ $newReleaseDir }}
@endtask

@task('cleanOldReleases', ['on' => 'remote'])
{{ logMessage("Cleaning up old releases…") }}
# Delete all but the 5 most recent.
cd {{ $releasesDir }}
ls -dt {{ $releasesDir }}/* | tail -n +6 | xargs -d "\n" sudo chown -R forge .;
ls -dt {{ $releasesDir }}/* | tail -n +6 | xargs -d "\n" rm -rf;
@endtask

@task('finishDeploy', ['on' => 'local'])
{{ logMessage("Application deployed!") }}
@endtask

@task('deployOnlyCode',['on' => 'remote'])
{{ logMessage("Deploying code changes…") }}
cd {{ $currentDir }}
git pull origin master
@endtask