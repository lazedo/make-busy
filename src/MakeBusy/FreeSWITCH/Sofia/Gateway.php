<?php

namespace MakeBusy\FreeSWITCH\Sofia;

use \DOMDocument;
use \MakeBusy\Common\Log;

class Gateway
{
    private $profile;
    private $name;
    private $params;

    public function __construct(Profile $profile, $name) {
        $this->profile = $profile;
        $this->name = $name;
        $this->setParam("retry-seconds", 5);
        $this->getProfile()->getGateways()->add($this);
    }

    public function getEsl() {
        return $this->profile->getEsl();
    }

    public function getProfile() {
        return $this->profile;
    }

    public function getProfileName() {
        return $this->getProfile()->getName();
    }

    public function getName() {
        return $this->name;
    }

    public function getParam($param) {
        if (isset($this->params[$param])) {
            return $this->params[$param];
        }

        return null;
    }

    public function setParam($param, $value) {
        $this->params[$param] = $value;
        return $this;
    }

    public function originate($uri, $on_answer='&park', array $vars = array()) {
        $name = $this->getName();
        $channel_vars = $this->createChannelVariables($vars);
        $url = $channel_vars . "sofia/gateway/$name/$uri";
        $event = $this->getEsl()->bgapi("originate $url $on_answer");
        return $event->getHeader('Job-UUID');
    }

    private function createChannelVariables($args) {
        if (empty($args)) return "";

        $vars = "{";
        foreach($args as $key => $value) {
            $vars .= $key . "=" . $value;
        }
        return $vars . "}";
    }

    public function fromDevice($device, $realm) {
        if (!empty($device->sip->realm)) {
            $realm = $device->sip->realm;
        }

        $this->setParam('realm', $realm);
        $this->setParam('from-domain', $realm);

        if(!empty($device->sip->username)) {
            $this->setParam('username', $device->sip->username);
        }

        if(!empty($device->sip->password)) {
            $this->setParam('password', $device->sip->password);
        }

        if(!empty($device->makebusy->proxy)) {
            $this->setParam('proxy', $device->makebusy->proxy);
        }

        if(!empty($device->makebusy->register)) {
            $this->setParam('register', TRUE);
        } else {
            $this->setParam('register', FALSE);
        }
        
        return $this;
    }

    public function fromResource($resource, $realm){
        if (!empty($resource->sip->realm)) {
            $realm = $resource->sip->realm;
        }

        $this->setParam('realm', $realm);
        $this->setParam('from-domain', $realm);

        if(!empty($resource->sip->username)) {
            $this->setParam('username', $resource->sip->username);
        }

        if(!empty($resource->sip->password)) {
            $this->setParam('password', $resource->sip->password);
        }

        if(!empty($resource->makebusy->proxy)) {
            $this->setParam('proxy', $resource->makebusy->proxy);
        }

        if(!empty($resource->makebusy->register)) {
            $this->setParam('register', TRUE);
        } else {
            $this->setParam('register', FALSE);
        }
        return $this;
    }


    public function fromConnectivity($trunkstore, $realm){
        $this->setParam('realm', $realm);
        $this->setParam('from-domain', $realm);

        if ($trunkstore->auth->auth_method=="IP") {
            $this->setParam('username', "not-required");
            $this->setParam('password', "not-required");
        }

        if(!empty($trunkstore->auth->auth_user)) {
            $this->setParam('username', $trunkstore->auth->auth_user);
        }

        if(!empty($trunkstore->auth->auth_password)) {
            $this->setParam('password', $trunkstore->auth->auth_password);
        }

        if(!empty($trunkstore->makebusy->proxy)) {
            $this->setParam('proxy', $trunkstore->makebusy->proxy);
        }

        if(!empty($trunkstore->makebusy->transport)) {
            $this->setParam('register-transport', $trunkstore->makebusy->transport);
        }

        if(!empty($trunkstore->makebusy->port)) {
            $value=$trunkstore->makebusy->proxy.":".$trunkstore->makebusy->port;
            $this->setParam('proxy', $value);
        }

        if(!empty($trunkstore->makebusy->register)) {
            $this->setParam('register', TRUE);
        } else {
            $this->setParam('register', FALSE);
        }
        return $this;
    }

    public function register() {
        $this->getEsl()->api_f('sofia profile %s register %s', $this->getProfileName(), $this->getName());
        return $this->waitForRegister();
    }

    public function unregister() {
        $this->getEsl()->api_f('sofia profile %s unregister %s', $this->getProfileName(), $this->getName());
        return $this->waitForUnRegister();
    }

    public function statusRegistry() {
        $data = $this->getEsl()->api_f('sofia status gateway %s::%s', $this->getProfileName(), $this->getName());
        if (preg_match('/State\s+REGED/i',$data->getBody(),$match) !== 0) { // search State REGED in output command sofia status gateway profile::gateway_id
            return TRUE;
        }
        return FALSE;
    }

    public function waitForRegister($timeout = 30){
        $gateway_name = $this->getName();
        $this->getEsl()->events("CUSTOM sofia::gateway_state");
        $start = time();

        while(1){
            $event = $this->getEsl()->recvEvent();
            if ((time() - $start) >= $timeout){
                Log::info("timeout waiting for %s gateway %s with username %s to register", $this->getProfileName(), $gateway_name, $this->getParam('username'));
                return null;
            }

            if (!$event) {
                continue;
            }

             if ($event->getHeader("Event-Name") == "CUSTOM"
                 && $event->getHeader("Event-Subclass") == "sofia%3A%3Agateway_state"
                 && $event->getHeader("Gateway") == $gateway_name
                )
            {
                if ($event->getHeader("State") == "REGED"){
                    Log::debug("registered %s gateway %s with username %s", $this->getProfileName(), $gateway_name, $this->getParam('username'));
                    return TRUE;
                }
                elseif ($event->getHeader("State") == "FAIL_WAIT" || $event->getHeader("State") == "UNREGED")
                {
                    Log::info("failed to register %s gateway %s with username %s: %s",
                              $this->getProfileName(), $gateway_name, $this->getParam('username'), $event->getHeader("State"));
                    return FALSE;
                }
             }
         }
    }

    public function waitForUnRegister($timeout = 30){
        $gateway_name = $this->getName();
        $this->getEsl()->events("CUSTOM sofia::gateway_state");
        $start = time();
        while(1){
            $event = $this->getEsl()->recvEvent();
            if ((time() - $start) >= $timeout){
                Log::info("timeout waiting to unregister %s gateway %s with username %s", $this->getProfileName(), $gateway_name, $this->getParam('username'));
                return null;
            }
            if (!$event) {
               continue;
            }
            if ($event->getHeader("Event-Name") == "CUSTOM"
                && $event->getHeader("Event-Subclass") == "sofia%3A%3Agateway_state"
                && $event->getHeader("Gateway") == $gateway_name
               )
            {
               if ($event->getHeader("State") == "NOREG") {
                   Log::debug("unregistered %s gateway %s with username %s", $this->getProfileName(), $gateway_name, $this->getParam('username'));
                   return TRUE;
               }
               elseif ($event->getHeader("State") == "REGED") {
                   Log::info("unable to unregistered %s gateway %s with username %s", $this->getProfileName(), $gateway_name, $this->getParam('username'));
                   return FALSE;
               }
            }
        }
   }

    public function kill() {
        return $this->getEsl()->api_f('sofia profile %s killgw %s', $this->getProfileName(), $this->getName());
    }

    public function asXml() {
        $dom = new DOMDocument('1.0', 'utf-8');
        $gateway = $this->asDomDocument($dom);
        $dom->appendChild($gateway);
        return $dom->saveXML();
    }

    public function asDomDocument(DOMDocument $dom = null) {
        if (!$dom) {
            $dom = new DOMDocument('1.0', 'utf-8');
        }

        $root = $dom->createElement('gateway');
        $root->setAttribute('name', $this->getName());

        foreach($this->params as $param => $value) {
            $child = $dom->createElement('param');
            $child->setAttribute('name', $param);
            $child->setAttribute('value', $value);
            $root->appendChild($child);
        }

        return $root;
    }
}
