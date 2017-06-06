<?php require __DIR__ . '/vendor/autoload.php';

use InfluxDB\Database;
use InfluxDB\Database\RetentionPolicy;
use InfluxDB\Point;
use MI\PublishReader;
use Noodlehaus\Config;
use oliverlorenz\reactphpmqtt\Connector;
use oliverlorenz\reactphpmqtt\packet\ConnectionOptions;
use oliverlorenz\reactphpmqtt\packet\Publish;
use oliverlorenz\reactphpmqtt\protocol\Version4;


$options = getopt('c:vvv', ['config:']);

$config_file = array_key_exists('c', $options) ? $options['c'] : (array_key_exists('config', $options) ? $options['config'] : null);
$verbose_level = array_key_exists('v', $options) ? count($options['v']) : 0;

// load config
$conf_spec = [__DIR__ . '/config/default.yml', '?config.local.yml'];
if ($config_file) {
    $conf_spec[] = $config_file;
}
$conf = new Config($conf_spec);

// influx
$influx_client = new InfluxDB\Client($conf->get('influxdb.server.host'), $conf->get('influxdb.server.port'));
// check if a database exists then create it if it doesn't
$influx_database = $influx_client->selectDB($conf->get('influxdb.database'));

//$influx_database->drop();
if (!$influx_database->exists()) {
    $influx_database->create(new RetentionPolicy('test', '1d', 2, true));
}

$loop = React\EventLoop\Factory::create();

$dnsResolverFactory = new React\Dns\Resolver\Factory();
$resolver = $dnsResolverFactory->createCached($conf->get('mqtt.nameserver'), $loop);

$connector = new Connector($loop, $resolver, new Version4());

$options = new ConnectionOptions([
    'username' => $conf->get('mqtt.broker.user'),
    'password' => $conf->get('mqtt.broker.password')
]);


$points = [];

$connector
    ->create($conf->get('mqtt.broker.host'), $conf->get('mqtt.broker.port'), $options)
    ->then(function (\React\Stream\Stream $stream) use ($connector, $conf, &$points) {

        $subscribe_config = (array)$conf->get('subscribe');
        foreach ($subscribe_config as $data) {
            $connector->subscribe($stream, $data['topic'], $data['qos'])->then(function (\React\Stream\Stream $stream) use ($connector, $data, &$points) {
                $stream->on('PUBLISH', function (Publish $message) use ($data, &$points) {

                    $topics_config = (array)$data['topics'];

                    $reader = new PublishReader($message);

                    foreach ($topics_config as $topic) {

                        if (preg_match($topic['pattern'], $reader->getMeasurement())) {
                            $measurement = trim(preg_replace($topic['pattern'], $topic['measurement'], $reader->getMeasurement()));

                            if ($measurement) {
                                $value = $reader->getValue();

                                if ($topic['type'] == 'int') {
                                    $value = (int)$value;
                                }

                                $points[] =
                                    new Point(
                                        $measurement,
                                        $value,
                                        (array)$topic['tags'],
                                        [],
                                        $reader->getTimestamp()
                                    );
                            }
                        }
                    }
                });
            });
        }
    });


$loop->addPeriodicTimer(1, function () use ($influx_database, &$points) {

    if (count($points) > 0) {
        $copy_points = $points;
        $points = [];
        if ($influx_database->writePoints($copy_points, Database::PRECISION_SECONDS)) {
            echo '+' . count($copy_points) . "\n";
        }
    }
});
$loop->run();