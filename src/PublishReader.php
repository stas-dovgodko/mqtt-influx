<?php
namespace MI;

use oliverlorenz\reactphpmqtt\packet\Publish;

class PublishReader extends Publish
{
    protected $publish;

    public function __construct(Publish $publish)
    {
        parent::__construct($publish->version);

        $this->publish = $publish;

    }

    public function getMeasurement()
    {
        return $this->publish->topic;
    }

    public function getValue()
    {
        return $this->publish->message;
    }

    public function getTimestamp()
    {
        return $this->publish->receiveTimestamp->getTimestamp();
    }
}