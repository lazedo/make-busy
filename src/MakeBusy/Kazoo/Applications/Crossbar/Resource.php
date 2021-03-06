<?php

namespace MakeBusy\Kazoo\Applications\Crossbar;

use \stdClass;

use \MakeBusy\FreeSWITCH\Sofia\Profile;
use \MakeBusy\Common\Configuration;
use \MakeBusy\FreeSWITCH\Esl\Connection as EslConnection;
use \MakeBusy\Kazoo\SDK;
use \MakeBusy\FreeSWITCH\Sofia\Gateway;
use \MakeBusy\Common\Utils;
use \MakeBusy\Common\Log;

class Resource
{
    private $resource;
    private $test_account;
    private $name;
    private $profile;
    static private $counter = 1;
    static public $instance_id = 1;
    static private $call_counter = 1;

    public function __construct(TestAccount $test_account, $profile, array $rules, $prefix = null, $emergency = FALSE, $register = FALSE, $global = FALSE) {
        $name = "Resource " . self::$counter++;
        $this->test_account = $test_account;
        $this->profile = $profile;
        $this->name = $name;

        $kazoo_resource = $test_account->getKazooResource($name);

        if (is_null($kazoo_resource)) {
            if ($global) {
               $kazoo_resource = SDK::getInstance()->Resource();
               $kazoo_resource->makebusyresource = true;
            } else {
               $kazoo_resource = $this->getAccount()->Resource();
            }
            $kazoo_resource->name = $name;
            $this->initialize($test_account, $kazoo_resource, $profile, $rules, $prefix, $emergency, $register, $global);
            $gateway = new Gateway($this->getProfile(), $kazoo_resource->id);
            $gateway->fromResource($kazoo_resource, $test_account->getAccount()->realm);
        } else {
            $this->setResource($kazoo_resource);
            $this->loaded = true;
        }
    }

    public function initialize(TestAccount $test_account, $resource, $profile, array $rules, $prefix = null, $emergency = FALSE, $register = FALSE, $global = FALSE) {

        $resource->makebusy = new stdClass();
        $resource->makebusy->test = TRUE;
        $resource->makebusy->gateway = TRUE;
        $resource->makebusy->proxy = Configuration::randomSipTarget();
        $resource->makebusy->profile = $profile;
        $resource->makebusy->register = (bool) $register;
        
        //TODO: make "rules" and "prefixes" definable in constructor
        $resource->rules = $rules;

        if ($emergency){
            $resource->emergency = TRUE;
        }

        $gateways = array();
        $gateway = new stdClass();

        $gateway->enabled = TRUE;
        $gateway->server = EslConnection::getInstance($profile)->getIpAddress();

        if (isset($prefix)){
            $gateway->prefix = $prefix;
        }

        if ($emergency){
            $gateway->emergency = TRUE;
        }

        $gateway->progress_timeout = "30";
        array_push($gateways, $gateway);

        $resource->gateways = $gateways;

        $resource->save();

        $this->setResource($resource);
    }

    public function getProfile() {
        return EslConnection::getInstance($this->profile)->getProfiles()->getProfile("profile");
    }

    public function getGateway() {
        return $this->getProfile()->getGateway($this->resource->id);
    }

    // returns Channel or null
    public function waitForInbound($number, $timeout = 5, $header = 'Caller-Destination-Number') {
        return $this->getGateway()->getEsl()->getChannels()->waitForInbound($number, $timeout, $header);
    }

    public function getId(){
        return $this->getResource()->getId();
    }

    public function setResource($resource){
        $this->resource = $resource;
    }

    public function getResource(){
        return $this->resource->fetch();
    }

    private function getTestAccount() {
        return $this->test_account;
    }

    private function setAccount(TestAccount $test_account) {
        $this->account = $test_account;
    }

    private function getAccount() {
        return $this->test_account->getAccount();
    }

    public function createCallflow(array $numbers, array $options = array()) {
        if ($this->loaded) {
            return;
        }
        $flow = self::callflowNode($this, $options);
        return $this->getAccount()->createCallflow($flow, $numbers);
    }

    public static function callflowNode(Resource $device, array $options = array()) {
        foreach ($options as $key => $value) {
            if (is_null($value)) {
                unset($options[$key]);
            }
        }

        $flow = new stdClass();
        $flow->module = "resources";
        $flow->data = new stdClass();
        $flow->children = new stdClass();

        return $flow;
    }
}
