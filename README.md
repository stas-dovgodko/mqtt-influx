# mqtt-influx

PHP-React based mqtt to influxdb gateway.

For IoT diy stuff to upload and visualize data in grafana

Please add local config just like _config/default.yml_ somewhere near and run:
 
 `php daemon.phar --config=my_config.yml`

or put config to _config.local.yml_

`php daemon.phar`

Can use plain daemon.php instead of phar too

Config example:

```
influxdb:
  database: test_db
  server:
    host: influxdb
    port: 8086
    
mqtt:
  broker:
    host: m12.cloudmqtt.com
    port: 14775
    user: zxcsec
    password: xcvdx5xcv

subscribe:
   - topic: "#"
     qos: 0
     topics:
       - {pattern: "'(.*?\/vq_0)'", measurement: "$1", tags: {}, type: percent}
       - {pattern: "'(.*?\/rms_0)'", measurement: "$1", tags: {}, type: int}
       - {pattern: "'(.*?\/trms_0)'", measurement: "$1", tags: {}, type: int}
       - {pattern: "'(.*?\/wifi-signal)'", measurement: "$1", tags: {}, type: float}
```
