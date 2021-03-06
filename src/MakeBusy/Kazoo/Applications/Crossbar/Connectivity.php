<?php

namespace MakeBusy\Kazoo\Applications\Crossbar;

use \stdClass;

use \MakeBusy\Kazoo\SDK;

use \MakeBusy\Common\Utils;
use \MakeBusy\Common\Configuration;
use \MakeBusy\Kazoo\Applications\Crossbar\TestAccount;
use \MakeBusy\Kazoo\Applications\Crossbar\SystemConfigs;
use \MakeBusy\Common\Log;

class Connectivity
{

    private $test_account;
    private $connectivity;
    const IP_PROFILE   = "pbx";
    const USER_PROFILE = "auth";

    public function __construct(TestAccount $test_account, array $options = array()) {
        $this->setTestAccount($test_account);

        $account = $this->getAccount();
        $connectivity = $account->Connectivity();
        $connectivity->account = new stdClass();
        $connectivity->account->trunks = 0;
        $connectivity->account->inbound_trunks = 0;
        $connectivity->account->auth_realm=$account->realm;
        $connectivity->DIDs_Unassigned = new stdClass();
        $connectivity->billing_account_id=$account->getId();
        $connectivity->servers =  array();
        $connectivity->makebusy = new stdClass();
        $connectivity->makebusy->test = TRUE;
        $connectivity->save();
        $this->setConnectivity($connectivity);
    }

    private function getTestAccount() {
         return $this->test_account;
    }

    private function setTestAccount(TestAccount $test_account) {
        $this->test_account = $test_account;
    }

    private function getAccount() {
        return $this->test_account->getAccount();
    }

    public function getConnectivity() {
        return $this->connectivity->fetch();
    }

    private function setConnectivity($connectivity) {
        $this->connectivity = $connectivity;
    }

    public function getId() {
        return $this->getConnectivity()->getId();
    }

    public function addGateway($type, $credential = null, $password = null) {
        $connectivity = $this->getConnectivity();
        $count = count($connectivity->servers);
        $arg_list = func_get_args();
        $name = "Pbx " .  $count;

        $element = new stdClass();
        $element->auth = new stdClass();
        $element->server_name=$name;
        $element->DIDs = new stdClass();
        $element->makebusy = new stdClass();

        switch ($type){
           case "Password":
                $element->auth->auth_method = "Password";
                $element->auth->auth_user = $credential;
                $element->auth->auth_password = $password;
                $element->makebusy->profile   = 'auth';
                $element->makebusy->register = TRUE;
                break;
           case 'IP':
                $element->auth->auth_method="IP";
                $element->auth->ip=$credential;
                $element->makebusy->profile   = 'pbx';
                $element->makebusy->register = FALSE;
                break;
           default:
                $element->auth->auth_method   = 'Password';
                $element->auth->auth_user     = 'noreg';
                $element->auth->auth_password = 'register';
                $element->makebusy->profile   = 'auth';
                $element->makebusy->register  = FALSE;
        }

        $element->server_type="FreeSWITCH";
        $element->monitor = new stdClass();
        $element->monitor->monitor_enabled = new stdClass();

        $element->options = new stdClass();
        $element->options->caller_id = new stdClass();
        $element->options->e911_info = new stdClass();
        $element->options->failover = new stdClass();
        $element->options->enabled = TRUE;
        $element->options->international = FALSE;
        $element->options->media_handling = 'bypass';

        $element->makebusy->test = TRUE;
        $element->makebusy->gateway = TRUE;
        $element->makebusy->proxy = Configuration::randomSipTarget();

        $element->makebusy->id = strtolower(Utils::randomString(28, "hex"));
        array_push($connectivity->servers, $element);
        $connectivity->save();
        return $count;
    }

    public function setAcl($name, $ip) {
        $test_account = $this->getTestAccount();
        $cidr = $ip . "/32";
        SystemConfigs::setCarrierAcl($test_account, $name, $cidr, "allow", "trusted");
        return $this;
    }

    public function removeAcl($name, $ip){
        $test_account = $this->getTestAccount();
        $cidr = $ip . "/32";
        SystemConfigs::removeCarrierAcl($test_account, $name, $cidr);
    }

    public function getGatewayId($gatewayid) {
        return $this->getConnectivity()->servers[$gatewayid]->makebusy->id;
    }

    public function getGatewayAuthParam($gatewayid,$param) {
        return $this->getConnectivity()->servers[$gatewayid]->auth->$param;
    }

    public function assignNumber($gatewayid,$number) {
        $connectivity = $this->getConnectivity();
        $number = '+' . $number;
        $connectivity->servers[$gatewayid]->DIDs->$number = new stdClass();
        $connectivity->save();
    }

    public function setInviteFormat($gateway_id, $format) {
        $connectivity = $this->getConnectivity();
        $connectivity->servers[$gateway_id]->options->inbound_format = $format;
        $connectivity->save();
    }

    public function resetInviteFormat($gateway_id) {
        $connectivity = $this->getConnectivity();
        unset($connectivity->servers[$gateway_id]->options->inbound_format);
        $connectivity->save();
    }

    public function setFailover($gatewayid, $failover_type, $destination) {
        $connectivity = $this->getConnectivity();
        $gateway = $connectivity->servers[$gatewayid];
        $gateway->options->failover->$failover_type = $destination;
        $connectivity->servers[(int) $gatewayid] = $gateway;
        $connectivity->save();
    }

    public function resetFailover($gateway_id) {
        $connectivity = $this->getConnectivity();
        $gateway = $connectivity->servers[$gateway_id];
        $gateway->options->failover = new stdClass();
        $connectivity->save();
    }

    public function setTransport($gatewayid,$transport) {
        $connectivity = $this->getConnectivity();
        $connectivity->servers[$gatewayid]->makebusy->transport=$transport;
        $connectivity->save();
    }

    public function setPort($gatewayid,$port) {
        $connectivity = $this->getConnectivity();
        $connectivity->servers[$gatewayid]->makebusy->port=$port;
        $connectivity->save();
    }

}
