<?php

namespace MakeBusy\Kazoo\Applications\Crossbar;

use \stdClass;

use \MakeBusy\Kazoo\Applications\Callflow\FeatureCodes;
use \MakeBusy\Kazoo\AbstractTestAccount;

use \CallflowBuilder\Node\Resource as CfResource;
use \CallflowBuilder\Builder;

use \MakeBusy\Common\Log;

class TestAccount extends AbstractTestAccount
{

    public function setup() {
        parent::setup();
        FeatureCodes::create($this);
        $this->createOffnetNoMatch();
        $this->createAccountMetaflow();
    }

    public function createCallflow($data) {
        $callflow = $this->getAccount()->Callflow();
        $callflow->fromBuilder($data);
        $callflow->makebusy = new stdClass();
        $callflow->makebusy->test = TRUE;
        $callflow->save();
        Log::info("created callflow %s", $callflow->getId());
        foreach($callflow->numbers as $number) {
            Log::debug("  number: %s", $number);
        }
        foreach($callflow->patterns as $pattern) {
            Log::debug("  pattern: %s", $pattern);
        }
        return $callflow;
    }

    public function createOffnetNoMatch(){
         $callflow = $this->getAccount()->Callflow();
         $resource = new CfResource($this->getAccount()->getID());
         $resource->useLocalResources(true);
         $builder  = new Builder(array('no_match'));
         $data = $builder->build($resource);
         $callflow->fromBuilder($data);
         $callflow->makebusy = new stdClass();
         $callflow->makebusy->test = TRUE;
         Log::debug("attempting to create offnet no-match callflow");
         return $callflow->save();
    }

    public function createAccountMetaflow() {
        $account = $this->getAccount();
        $account->metaflows = new stdClass();
        $account->metaflows->patterns = new stdClass();

        $account->metaflows->patterns->{"^4([0-9]+)$"} = new stdClass();
        $account->metaflows->patterns->{"^4([0-9]+)$"}->{"module"} = "transfer";
        $account->metaflows->patterns->{"^4([0-9]+)$"}->{"data"} = new stdClass();

        $account->metaflows->numbers = new stdClass();
        $account->metaflows->numbers->{"404"} = new stdClass();
        $account->metaflows->numbers->{"404"}->{"module"} = "hangup";
        $account->metaflows->numbers->{"404"}->{"data"} = new stdClass();

        Log::debug("attempting to create metaflows for transfer and hangup");
        $account->save();
    }

    public function setAccountRealm($realm) {
        $account = $this->getAccount();
        $account->realm = $realm;
        $account->save();
    }

    public function listItems($item_name) {
        $account = $this->getAccount();
        return $account->$item_name();
    }
}
