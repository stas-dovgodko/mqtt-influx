<?php require __DIR__ . '/vendor/autoload.php';

use InfluxDB\Database;
use InfluxDB\Database\RetentionPolicy;
use InfluxDB\Point;
use MI\PublishReader;
use Noodlehaus\Config;
use oliverlorenz\reactphpmqtt\Connector;
use oliverlorenz\reactphpmqtt\protocol\Version4;
use oliverlorenz\reactphpmqtt\packet\Publish;


$options = getopt('c:vvv', ['config:']);

$config_file = array_key_exists('c', $options) ? $options['c'] : (array_key_exists('config', $options) ? $options['config'] : null);
$verbose_level = array_key_exists('v', $options) ? count($options['v']) : 0;

// load config
$conf_spec = [__DIR__ . '/config/default.yml', '?' . __DIR__ . '/config/local.yml'];
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

$connector->create($conf->get('mqtt.broker.host'), $conf->get('mqtt.broker.port'), $conf->get('mqtt.broker.user'), $conf->get('mqtt.broker.password'));

$subscribe_config = (array)$conf->get('subscribe');
$connector->onConnected(function () use ($connector, $subscribe_config) {
    foreach ($subscribe_config as $data) {
        $connector->subscribe($data['topic'], $data['qos']);
    }
});


$topics_config = (array)$conf->get('topics');

$points = [];
$connector->onPublishReceived(function (Publish $message) use ($topics_config, &$points) {

    $reader = new PublishReader($message);

    foreach ($topics_config as $topic) {

        if (preg_match($topic['pattern'], $reader->getMeasurement())) {
            $measurement = preg_replace($topic['pattern'], $topic['measurement'], $reader->getMeasurement());
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