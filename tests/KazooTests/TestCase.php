<?php

namespace KazooTests;

use \PHPUnit_Framework_TestCase;

use \MakeBusy\Common\Configuration;
use \MakeBusy\Common\Utils;
use \MakeBusy\Common\Log;

use \MakeBusy\FreeSWITCH\Channels\Channels;
use \MakeBusy\FreeSWITCH\Channels\Channel;
use \MakeBusy\Kazoo\Applications\Crossbar\Device;
use \MakeBusy\Kazoo\Applications\Crossbar\Resource;
use \MakeBusy\Kazoo\AbstractTestAccount;
use \MakeBusy\FreeSWITCH\Esl\Connection as EslConnection;

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    /**
    * @dataProvider sipUriProvider
    */
    public function testMain($sipUri) {
        $this->main($sipUri);
    }

    // override this to run a test
    public function main($sip_uri) {
    }

    public function sipUriProvider() {
        $re = [];
        foreach(Configuration::sipTargets() as $sipUri) {
            $re[] = [$sipUri];
        }
        return $re;
    }

    public static function getEsl($type = "auth") {
        return EslConnection::getInstance($type);
    }

    public static function getProfile($profile) {
        return self::getEsl($profile)->getProfiles()->getProfile("profile");
    }

    public static function getGateways($profile) {
        return self::getProfile($profile)->getGateways();
    }

    public static function setUpBeforeClass() {
        if (isset($_ENV['CLEAN'])) {
            Log::debug("Reset Kazoo Makebusy config");
            AbstractTestAccount::nukeTestAccounts();
        } else {
            Log::debug("Use existing Kazoo Makebusy config");
        }
        Device::$instance_id = Utils::randomString(8);
        Resource::$instance_id = Utils::randomString(8);
        Log::truncateLog();
    }

    public static function sync_sofia_profile($profile, $loaded = false, $counter = 1) {
        if ($loaded) {
            if (isset($_ENV['REGISTER_PROFILE'])) {
                self::getProfile($profile)->register();
                self::getProfile($profile)->waitForRegister($counter);
            }
            if (isset($_ENV['RESTART_PROFILE'])) {
                self::getProfile($profile)->restart();
                self::getProfile($profile)->waitForRegister($counter);
            }
        } else {
            self::getProfile($profile)->restart();
            self::getProfile($profile)->waitForRegister($counter);
        }
    }

    public static function className($object) {
        $classNameWithNamespace = get_class($object);
        return substr($classNameWithNamespace, strrpos($classNameWithNamespace, '\\')+1);
    }

    public static function getSipTargets() {
        return Configuration::sipTargets();
    }

    public static function getSipGateways() {
        return Configuration::sipGateways();
    }

    public static function getRandomSipTarget() {
        return Configuration::randomSipTarget();
    }

    public static function ensureChannel($ch) {
        self::assertInstanceOf("\\MakeBusy\\FreeSWITCH\\Channels\\Channel", $ch, "Expected channel wasn't created");
        return $ch;
    }

    public static function ensureEvent($ev) {
        self::assertInstanceOf("\\MakeBusy\\FreeSWITCH\\ESL\\Event", $ev, "Expected evnet wasn't received");
        return $ev;
    }

    // channel a is calling (originating), channel b is ringing
    public static function ensureAnswer($ch_a, $ch_b) {
        $ch_b->answer();
        self::ensureEvent($ch_b->waitAnswer());
        self::ensureEvent($ch_a->waitAnswer());
        Log::info("call %s has answered call %s", $ch_b->getUuid(), $ch_a->getUuid());
    }

    public static function ensureTalking($first_channel, $second_channel, $freq = 600) {
        $first_channel->playTone($freq, 3000, 0, 5);
        $tone = $second_channel->detectTone($freq);
        $first_channel->breakout();
        self::assertEquals($freq, $tone, "Expected tone wasn't heard");
    }

    public static function ensureNotTalking($first_channel, $second_channel, $freq = 600) {
        $first_channel->playTone($freq, 3000, 0, 5);
        $tone = $second_channel->detectTone($freq);
        $first_channel->breakout();
        self::assertNotEquals($freq, $tone, "Unexpected tone was detected");
    }

    public static function ensureTwoWayAudio($a_channel, $b_channel) {
        self::ensureTalking($a_channel, $b_channel, 1600);
        self::ensureTalking($b_channel, $a_channel, 600);
    }

    public static function hangupBridged($a_channel, $b_channel) {
        $a_channel->hangup();
        self::ensureEvent($a_channel->waitDestroy());
        self::ensureEvent($b_channel->waitDestroy());
    }

    public static function hangupChannels() {
        foreach (func_get_args() as $channel) {
            $channel->hangup();
            self::ensureEvent($channel->waitDestroy());
        }
    }

    public static function expectPrompt($channel, $descriptor, $timeout = 10) {
        $tone = $channel->detectTone($descriptor, $timeout);
        $expected = strtolower($descriptor);
        self::assertEquals($expected, $tone);
    }

}
